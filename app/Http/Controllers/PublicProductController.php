<?php

namespace App\Http\Controllers;

use App\Data\ProductSeal;
use App\Enums\DocumentPreviewKind;
use App\Enums\ProductDocumentType;
use App\Enums\ProductUnitStatus;
use App\Models\Product;
use App\Models\ProductDocument;
use App\Models\ProductUnit;
use App\Support\Captcha;
use App\Support\PublicImage;
use App\Support\UnitDevice;
use App\Support\UnitSerial;
use App\Support\Watermark;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The page a product answers to in public: what the article is, and where
 * its compliance check stands.
 *
 * Reachable by anyone holding the link and by nobody else guessing one,
 * which is what the uuid is for. Everything on it is chosen rather than
 * filtered out: the article's name, its maker, the numbers printed on the
 * box, its pictures, and the seal. Who supplies it, what it cost, how many
 * there are and every paper filed against it stay behind the login, because
 * none of that is the reader's business and some of it is the distributor's
 * trade to protect.
 */
class PublicProductController extends Controller
{
    /**
     * The session key a check's result is handed to the next page under.
     */
    protected const string RESULT_KEY = 'unit_check_result';

    /**
     * Show the product's public page.
     */
    public function show(Request $request, Product $product): Response
    {
        return Inertia::render('products/public', [
            ...$this->toPageArray($product),
            'serial' => null,
            'checkResult' => $this->pullResult($request, $product),
        ]);
    }

    /**
     * Show the product's public page, as read off one packet's label.
     *
     * The same page, with the label's serial filled into the check form and
     * nothing else: whether the packet is genuine is only ever the answer
     * to a check somebody asked for, never something a scan gives away.
     */
    public function unit(Request $request, Product $product, string $serial): Response
    {
        $normalized = UnitSerial::normalize($serial);

        return Inertia::render('products/public', [
            ...$this->toPageArray($product),
            'serial' => $normalized === null ? $serial : UnitSerial::format($normalized),
            'checkResult' => $this->pullResult($request, $product),
        ]);
    }

    /**
     * Check a serial against this product, and show what the check found.
     *
     * The captcha is checked before the serial is even looked at, so
     * walking through serials costs a typed code each. A serial issued for
     * some other product reads exactly like one nobody issued: a label
     * copied off another article is not genuine for this one, and saying
     * which article it does belong to would hand a counterfeiter a list of
     * working codes.
     *
     * The answer travels to the next page in the session rather than the
     * address, so it is shown once, to whoever asked, and a shared link
     * shows nothing.
     */
    public function check(Request $request, Product $product): RedirectResponse
    {
        $request->validate([
            'serial' => ['required', 'string', 'max:32'],
        ], [
            'serial.required' => __('Enter the serial printed beside the QR code.'),
        ]);

        if (! Captcha::check($request->session(), $request->string('captcha')->toString())) {
            throw ValidationException::withMessages([
                'captcha' => __('The code did not match. Type the new code shown in the picture.'),
            ]);
        }

        $typed = $request->string('serial')->toString();
        $unit = $this->findUnit($product, $typed);

        if ($unit === null) {
            $request->session()->flash(self::RESULT_KEY, [
                'product' => $product->uuid,
                'serial' => $typed,
                'status' => ProductUnitStatus::Unknown->value,
                'checkedAt' => null,
                'earlierChecks' => 0,
                'history' => [],
            ]);

            return back();
        }

        $result = $unit->check(UnitDevice::hash($request));

        $request->session()->flash(self::RESULT_KEY, [
            'product' => $product->uuid,
            'serial' => $unit->formattedSerial(),
            ...$result,
            'status' => $result['status']->value,
        ]);

        return redirect()->to($unit->setRelation('product', $product)->url());
    }

    /**
     * Take the result of the check that was just made, if it was made on
     * this product's page.
     *
     * Read back as it was written by check(), which is the only writer of
     * the key; the page is what gives it a shape.
     *
     * @return array<string, mixed>|null
     */
    protected function pullResult(Request $request, Product $product): ?array
    {
        $result = $request->session()->get(self::RESULT_KEY);

        if (! is_array($result) || ($result['product'] ?? null) !== $product->uuid) {
            return null;
        }

        unset($result['product']);

        return $result;
    }

    /**
     * Show a document the distributor released to the public page, inside
     * the page's own viewer.
     *
     * Only a PDF marked public, checked here rather than trusted from the
     * route, like the pictures. A Word or Excel file cannot carry the
     * watermark, so it is never handed out here even if it was released
     * before that rule.
     */
    public function document(Product $product, ProductDocument $document): StreamedResponse
    {
        return $this->publicPdf($product, $document, 'inline');
    }

    /**
     * Hand over a released document to keep, as an attachment.
     */
    public function downloadDocument(Product $product, ProductDocument $document): StreamedResponse
    {
        return $this->publicPdf($product, $document, 'attachment');
    }

    /**
     * Stream the watermarked copy of a released PDF.
     *
     * Served under the PDF type whatever the column says, and never the
     * original: a file that could not be stamped is a missing page.
     */
    protected function publicPdf(Product $product, ProductDocument $document, string $disposition): StreamedResponse
    {
        abort_unless($document->is_public && $document->previewKind() === DocumentPreviewKind::Pdf, 404);

        $path = Watermark::forPublic($product, $document);

        abort_if($path === null, 404);

        return Storage::disk(ProductDocument::DISK)->response($path, $document->name, [
            'Content-Type' => 'application/pdf',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'public, max-age=3600',
        ], $disposition);
    }

    /**
     * Show one of the product's pictures, or one of its warning images.
     *
     * The only bytes on the private disk that are served without a login,
     * and only ever the ones somebody filed as a picture of the article or
     * of its warning. The kind is checked here rather than trusted from the
     * route, so a test report filed under the wrong heading is still not
     * reachable this way.
     */
    public function image(Product $product, ProductDocument $document): StreamedResponse
    {
        abort_unless(
            $this->isPublicImage($document) || $this->isPublicImage($document, ProductDocumentType::SafetyImage),
            404,
        );

        /**
         * A picture of the article goes out watermarked. A warning image
         * does not: it is a small safety symbol, and a stamp across it
         * would only make it harder to read.
         */
        $path = $document->type === ProductDocumentType::ProductImage
            ? Watermark::forPublic($product, $document)
            : $document->path;

        abort_if($path === null, 404);

        /**
         * The type from the allowlist rather than from the column, for the
         * same reason the private preview does it: the stored type is
         * whatever the uploading browser claimed.
         */
        return PublicImage::response(
            ProductDocument::DISK,
            $path,
            $document->name,
            (string) $document->previewContentType(),
        );
    }

    /**
     * Get what the public page shows about the product itself.
     *
     * @return array{product: array{uuid: string, name: string, brand: string|null, ean: string|null, internal_article_number: string|null}, safety: array<string, string|null>, warningImages: array<array{id: int, url: string, name: string}>, seal: ProductSeal, images: array<array{id: int, url: string, name: string}>, documents: array<array{id: int, type: string, name: string, size: int, url: string, downloadUrl: string}>, importer: array<string, string|null>, locale: string, checkUrl: string, checkable: bool}
     */
    protected function toPageArray(Product $product): array
    {
        /**
         * The template and the papers come along because the seal is read
         * partly from the completeness score, which is scored against both.
         */
        $product->load(['brand', 'template', 'documents', 'organization']);

        return [
            'product' => [
                'uuid' => $product->uuid,
                'name' => $product->name,
                'brand' => $product->brand?->name,
                'ean' => $product->ean,
                'internal_article_number' => $product->internal_article_number,
            ],
            /**
             * What the reader needs to use the article safely. Printed on
             * the packet already, and repeated here because a packet gets
             * thrown away and a warning should not go with it.
             */
            'safety' => [
                'age_grading' => $product->age_grading,
                'warning_text' => $product->warning_text,
                'safety_notice' => $product->safety_notice,
                'safety_instructions' => $product->safety_instructions,
                'material_information' => $product->material_information,
                'usage_restrictions' => $product->usage_restrictions,
            ],
            /**
             * The symbols printed beside the warning, shown inside it rather
             * than among the downloads.
             */
            'warningImages' => $this->toImageArray($product, ProductDocumentType::SafetyImage),
            'seal' => $product->seal(),
            'images' => $this->toImageArray($product),
            'documents' => $this->toDocumentArray($product),
            /**
             * Who places the product on the market, as the packet already
             * has to say, with the company details it chose to publish.
             * Only the distributor: who supplies them stays theirs.
             */
            'importer' => $product->organization->toPublicProfile(),
            'locale' => App::getLocale(),
            'checkUrl' => route('products.public.check', ['product' => $product->uuid]),
            /**
             * Whether the product's packets carry serials at all, so the
             * page only offers to check one when there is one to check.
             */
            'checkable' => $product->units()->exists(),
        ];
    }

    /**
     * Find the packet a serial names, among this product's packets only.
     */
    protected function findUnit(Product $product, string $serial): ?ProductUnit
    {
        $serial = UnitSerial::normalize($serial);

        if ($serial === null) {
            return null;
        }

        return $product->units()->where('serial', $serial)->first();
    }

    /**
     * Get the documents the distributor released to the public page.
     *
     * Only PDFs, the one kind of paper that can carry the watermark.
     * Pictures of the article and of its warning are left out: they are
     * already shown in the gallery and in the warning.
     *
     * @return array<array{id: int, type: string, name: string, size: int, url: string, downloadUrl: string}>
     */
    protected function toDocumentArray(Product $product): array
    {
        return $product->documents
            ->filter(fn (ProductDocument $document) => $document->is_public
                && $document->previewKind() === DocumentPreviewKind::Pdf
                && ! $this->isPublicImage($document)
                && ! $this->isPublicImage($document, ProductDocumentType::SafetyImage))
            ->map(fn (ProductDocument $document) => [
                'id' => $document->id,
                'type' => $document->type->value,
                'name' => $document->name,
                'size' => $document->size,
                'url' => route('products.public.document', [
                    'product' => $product->uuid,
                    'document' => $document->id,
                ]),
                'downloadUrl' => route('products.public.document.download', [
                    'product' => $product->uuid,
                    'document' => $document->id,
                ]),
            ])
            ->values()
            ->toArray();
    }

    /**
     * Get the pictures the page can show.
     *
     * A picture that the browser cannot draw is no picture at all -- a
     * manual filed under the wrong heading, say -- so it is left out here
     * rather than rendered as a broken frame.
     *
     * @return array<array{id: int, url: string, name: string}>
     */
    protected function toImageArray(Product $product, ProductDocumentType $type = ProductDocumentType::ProductImage): array
    {
        return $product->documents
            ->filter(fn (ProductDocument $document) => $this->isPublicImage($document, $type))
            ->map(fn (ProductDocument $document) => [
                'id' => $document->id,
                'url' => route('products.public.image', [
                    'product' => $product->uuid,
                    'document' => $document->id,
                ]),
                'name' => $document->name,
            ])
            ->values()
            ->toArray();
    }

    /**
     * Determine if a document is a picture of the given kind that may be
     * shown to anyone: one switched on for the public page, like any
     * document.
     */
    protected function isPublicImage(ProductDocument $document, ProductDocumentType $type = ProductDocumentType::ProductImage): bool
    {
        return $document->type === $type
            && $document->is_public
            && $document->previewKind() === DocumentPreviewKind::Image;
    }
}
