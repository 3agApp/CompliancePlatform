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
use App\Support\PublicDocumentRenderer;
use App\Support\UnitDevice;
use App\Support\UnitSerial;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
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
     * Describe a document the distributor released to the public page.
     *
     * Only one marked public, and checked here rather than trusted from the
     * route, like the pictures. A PDF or a picture is never handed over as
     * it was uploaded: the reader is given the addresses of its pages, each
     * drawn as a watermarked image, so there is no clean copy to lift.
     *
     * A Word or Excel file cannot be drawn, so it is still handed over as a
     * download, as it always was.
     */
    public function document(Product $product, ProductDocument $document, PublicDocumentRenderer $renderer): JsonResponse|StreamedResponse
    {
        abort_unless($document->is_public, 404);
        abort_unless(Storage::disk(ProductDocument::DISK)->exists($document->path), 404);

        if ($document->previewKind() === null) {
            return Storage::disk(ProductDocument::DISK)->download($document->path, $document->name, [
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'public, max-age=3600',
            ]);
        }

        $pages = $this->pageCount($document, $renderer);

        return response()->json([
            'name' => $document->name,
            'pages' => collect(range(1, $pages))
                ->map(fn (int $page) => route('products.public.document.page', [
                    'product' => $product->uuid,
                    'document' => $document->id,
                    'page' => $page,
                ]))
                ->all(),
        ], headers: [
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    /**
     * Show one page of a released document, drawn as a watermarked image.
     */
    public function page(Product $product, ProductDocument $document, int $page, PublicDocumentRenderer $renderer): HttpResponse
    {
        abort_unless($document->is_public && $document->previewKind() !== null, 404);
        abort_unless(Storage::disk(ProductDocument::DISK)->exists($document->path), 404);
        abort_unless($page >= 1 && $page <= $this->pageCount($document, $renderer), 404);

        return $this->watermarkedResponse($product, $document, $page, $renderer);
    }

    /**
     * Show one of the product's pictures.
     *
     * The kind is checked here rather than trusted from the route, so a
     * test report filed under the wrong heading is not reachable this way.
     * Photographs of the article and its packaging are watermarked like the
     * documents are. A warning pictogram is not: it is a small standard
     * symbol, and a mark across it would only make it harder to read.
     */
    public function image(Product $product, ProductDocument $document, PublicDocumentRenderer $renderer): HttpResponse|StreamedResponse
    {
        abort_unless($this->isPublicImage($document), 404);

        /**
         * A row whose file is gone -- an old record, a restored database, a
         * disk that lost it -- is a picture that does not exist, which is a
         * 404 and not a crash. This address is open to anyone, so it never
         * gets to answer with a stack trace.
         */
        abort_unless(Storage::disk(ProductDocument::DISK)->exists($document->path), 404);

        if ($document->type !== ProductDocumentType::SafetyImage) {
            return $this->watermarkedResponse($product, $document, 1, $renderer);
        }

        return Storage::disk(ProductDocument::DISK)->response($document->path, $document->name, [
            /**
             * From the allowlist rather than from the column, for the same
             * reason the private preview does it: the stored type is
             * whatever the uploading browser claimed.
             */
            'Content-Type' => $document->previewContentType(),
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; img-src 'self' data:; object-src 'none'; base-uri 'none'; form-action 'none'",
            /**
             * A picture on a public page is as cacheable as the page is
             * public, and it never changes: a new picture is a new row with
             * a new address.
             */
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    /**
     * Count a released document's pages, or give up on it with a 404.
     *
     * A PDF poppler cannot read is reported, so somebody hears about it,
     * and shown to the reader as missing rather than as a server error.
     */
    protected function pageCount(ProductDocument $document, PublicDocumentRenderer $renderer): int
    {
        try {
            return $renderer->pageCount($document);
        } catch (RuntimeException $exception) {
            report($exception);

            abort(404);
        }
    }

    /**
     * Answer with one watermarked page of a document.
     */
    protected function watermarkedResponse(Product $product, ProductDocument $document, int $page, PublicDocumentRenderer $renderer): HttpResponse
    {
        try {
            $path = $renderer->page($document, $page, $this->watermarkFor($product));
        } catch (RuntimeException $exception) {
            report($exception);

            abort(404);
        }

        /**
         * Sent whole rather than streamed: a drawn page is a few hundred
         * kilobytes at most, and the length is then known up front.
         */
        return response((string) Storage::disk(ProductDocument::DISK)->get($path), 200, [
            'Content-Type' => 'image/webp',
            'Content-Disposition' => 'inline',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; img-src 'self' data:; object-src 'none'; base-uri 'none'; form-action 'none'",
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    /**
     * The words stamped across every public page: who released it, where,
     * and for which article.
     */
    protected function watermarkFor(Product $product): string
    {
        $product->loadMissing('organization');

        return implode(' · ', [
            $product->organization->name,
            (string) parse_url((string) config('app.url'), PHP_URL_HOST),
            substr($product->uuid, 0, 8),
        ]);
    }

    /**
     * Get what the public page shows about the product itself.
     *
     * @return array{product: array{uuid: string, name: string, brand: string|null, ean: string|null, internal_article_number: string|null}, safety: array<string, string|null>, seal: ProductSeal, images: array<array{id: int, url: string, name: string}>, warningPictograms: array<array{id: int, url: string, name: string}>, documents: array<array{id: int, type: string, name: string, size: int, url: string, viewable: bool}>, importer: array{name: string, profile: array<string, string|null>}, locale: string, checkUrl: string, checkable: bool}
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
            'seal' => $product->seal(),
            'images' => $this->toImageArray($product),
            'warningPictograms' => $this->toPictogramArray($product),
            'documents' => $this->toDocumentArray($product),
            /**
             * Who places the product on the market, as the packet already
             * has to say, with the imprint they keep about themselves. Never
             * who supplies them: that stays theirs.
             */
            'importer' => [
                'name' => $product->organization->name,
                'profile' => $product->organization->companyProfile(),
            ],
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
     * Pictures of the article are left out: they are already the gallery.
     *
     * @return array<array{id: int, type: string, name: string, size: int, url: string, viewable: bool}>
     */
    protected function toDocumentArray(Product $product): array
    {
        return $product->documents
            ->filter(fn (ProductDocument $document) => $document->is_public && ! $this->isPublicImage($document))
            ->map(fn (ProductDocument $document) => [
                'id' => $document->id,
                'type' => $document->type->value,
                'name' => $document->name,
                'size' => $document->size,
                'url' => route('products.public.document', [
                    'product' => $product->uuid,
                    'document' => $document->id,
                ]),
                /**
                 * Whether it opens in the page viewer as watermarked pages,
                 * or is a file that can only be downloaded.
                 */
                'viewable' => $document->previewKind() !== null,
            ])
            ->values()
            ->toArray();
    }

    /**
     * Get the pictures the gallery shows: the article, and close-ups of
     * its packaging.
     *
     * A picture that the browser cannot draw is no picture at all -- a
     * manual filed under the wrong heading, say -- so it is left out here
     * rather than rendered as a broken frame.
     *
     * @return array<array{id: int, url: string, name: string}>
     */
    protected function toImageArray(Product $product): array
    {
        return $this->toPublicImages($product, [ProductDocumentType::ProductImage, ProductDocumentType::PackagingPhoto]);
    }

    /**
     * Get the warning pictograms, shown beside the written warning.
     *
     * @return array<array{id: int, url: string, name: string}>
     */
    protected function toPictogramArray(Product $product): array
    {
        return $this->toPublicImages($product, [ProductDocumentType::SafetyImage]);
    }

    /**
     * Get the public pictures of the given kinds.
     *
     * @param  list<ProductDocumentType>  $types
     * @return array<array{id: int, url: string, name: string}>
     */
    protected function toPublicImages(Product $product, array $types): array
    {
        return $product->documents
            ->filter(fn (ProductDocument $document) => $this->isPublicImage($document) && in_array($document->type, $types, true))
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
     * Determine if a document is a picture that may be shown to anyone.
     *
     * Photographs of the article and its packaging always may: they are
     * what the shelf already shows. A warning pictogram only once the
     * distributor has released it, like any document.
     */
    protected function isPublicImage(ProductDocument $document): bool
    {
        if ($document->previewKind() !== DocumentPreviewKind::Image) {
            return false;
        }

        return match ($document->type) {
            ProductDocumentType::ProductImage => true,
            ProductDocumentType::PackagingPhoto, ProductDocumentType::SafetyImage => $document->is_public,
            default => false,
        };
    }
}
