<?php

namespace App\Http\Controllers;

use App\Enums\DocumentPreviewKind;
use App\Enums\ProductDocumentType;
use App\Models\Product;
use App\Models\ProductDocument;
use Illuminate\Support\Facades\Storage;
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
     * Show the product's public page.
     */
    public function show(Product $product): Response
    {
        /**
         * The template and the papers come along because the seal is read
         * partly from the completeness score, which is scored against both.
         */
        $product->load(['brand', 'template', 'documents']);

        return Inertia::render('products/public', [
            'product' => [
                'uuid' => $product->uuid,
                'name' => $product->name,
                'brand' => $product->brand?->name,
                'ean' => $product->ean,
                'internal_article_number' => $product->internal_article_number,
            ],
            'seal' => $product->seal(),
            'images' => $this->toImageArray($product),
        ]);
    }

    /**
     * Show one of the product's pictures.
     *
     * The only bytes on the private disk that are served without a login,
     * and only ever the ones somebody filed as a picture of the article. The
     * kind is checked here rather than trusted from the route, so a test
     * report filed under the wrong heading is still not reachable this way.
     */
    public function image(Product $product, ProductDocument $document): StreamedResponse
    {
        abort_unless($this->isPublicImage($document), 404);

        /**
         * A row whose file is gone -- an old record, a restored database, a
         * disk that lost it -- is a picture that does not exist, which is a
         * 404 and not a crash. This address is open to anyone, so it never
         * gets to answer with a stack trace.
         */
        abort_unless(Storage::disk(ProductDocument::DISK)->exists($document->path), 404);

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
             * A picture of a product on a public page is as cacheable as
             * the page is public, and it never changes: a new picture is a
             * new row with a new address.
             */
            'Cache-Control' => 'public, max-age=86400',
        ]);
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
    protected function toImageArray(Product $product): array
    {
        return $product->documents
            ->filter(fn (ProductDocument $document) => $this->isPublicImage($document))
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
     * Determine if a document is a picture of the article that may be shown
     * to anyone.
     */
    protected function isPublicImage(ProductDocument $document): bool
    {
        return $document->type === ProductDocumentType::ProductImage
            && $document->previewKind() === DocumentPreviewKind::Image;
    }
}
