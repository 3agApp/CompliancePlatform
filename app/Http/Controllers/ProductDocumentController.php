<?php

namespace App\Http\Controllers;

use App\Actions\Products\GuessDocumentKinds;
use App\Actions\Products\ReviewProduct;
use App\Enums\ProductEventType;
use App\Http\Requests\Products\SaveProductDocumentRequest;
use App\Http\Requests\Products\SuggestProductDocumentKindsRequest;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductDocument;
use App\Support\HeicImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * The papers filed against a product: test reports, declarations of
 * conformity, manuals, certificates, images.
 *
 * Both sides of the trade file them and both read them, so every action here
 * authorizes against the product itself rather than against the viewing
 * organization. A supplier whose connection is revoked loses the papers along
 * with the product, because that is the same check.
 */
class ProductDocumentController extends Controller
{
    public function __construct(protected ReviewProduct $review, protected HeicImage $heic)
    {
        //
    }

    /**
     * File documents against the product.
     *
     * A batch is all or nothing. Validation has already refused the whole
     * request if any one file was wrong, so by here the only way to fail is
     * the database -- and a half-written batch would leave a product looking
     * documented when it is not.
     */
    public function store(SaveProductDocumentRequest $request, Organization $currentOrganization, Product $product): RedirectResponse
    {
        Gate::authorize('update', $product);

        $rows = [];

        try {
            foreach ($request->documents() as $document) {
                $file = $document['file'];

                $rows[] = [
                    'type' => $document['type'],
                    'title' => $document['title'],
                    'uploaded_by' => $request->user()->id,
                    ...$this->storeFile($file, $product),
                ];
            }

            DB::transaction(function () use ($product, $rows, $request, $currentOrganization) {
                $product->documents()->createMany($rows);

                /**
                 * One line of history per paper rather than one for the
                 * batch: they are filed together but they are read one at a
                 * time, and "who filed this certificate" is the question
                 * the history gets asked.
                 */
                foreach ($rows as $row) {
                    $product->recordEvent(
                        ProductEventType::DocumentUploaded,
                        $request->user(),
                        $currentOrganization,
                        changes: ['document' => ['from' => null, 'to' => $row['name']]],
                    );
                }
            });
        } catch (Throwable $exception) {
            /**
             * The bytes are written before the rows and cannot be rolled
             * back with them, so they are taken back by hand. Without this a
             * failed batch would leave files on the disk that nothing points
             * at and nothing will ever clean up.
             */
            Storage::disk(ProductDocument::DISK)->delete(array_column($rows, 'path'));

            throw $exception;
        }

        /**
         * Filing a paper is filling the product in, so it withdraws a
         * product the supplier had already offered up the same way editing
         * a field does.
         */
        $this->review->withdrawAfterEdit($product, $request->user(), $currentOrganization);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => trans_choice('Document uploaded.|:count documents uploaded.', count($rows)),
        ]);

        return $this->backToProduct($currentOrganization, $product);
    }

    /**
     * Guess what kind of paper each of a batch of files is.
     *
     * Answers with one guess per file, always, whatever happened. A provider
     * that is not configured, refuses the key or never answers is reported
     * in the body rather than in the status, because none of those is an
     * error as far as this page is concerned: the kinds are still there to
     * be picked by hand, which is how they were picked before any of this
     * existed.
     */
    public function suggest(SuggestProductDocumentKindsRequest $request, Organization $currentOrganization, Product $product, GuessDocumentKinds $guessDocumentKinds): JsonResponse
    {
        Gate::authorize('update', $product);

        /**
         * The viewing organization's provider, not the product owner's: a
         * supplier filing against a distributor's product spends its own
         * credit, on its own key.
         */
        $guesses = $guessDocumentKinds->handle($currentOrganization, $request->candidates());

        return response()->json($guesses->toArray());
    }

    /**
     * Hand over the file behind the document.
     *
     * The files sit on the private disk, so this is the only way to them:
     * there is no URL that works without the reader being allowed to see the
     * product first.
     */
    public function show(Organization $currentOrganization, Product $product, ProductDocument $document): StreamedResponse
    {
        Gate::authorize('view', $product);

        return Storage::disk(ProductDocument::DISK)->download($document->path, $document->name);
    }

    /**
     * Show the file behind the document without handing it over.
     *
     * The same authorization as a download, because it is the same bytes:
     * anyone who may read the product may read its papers, whichever way
     * they are put in front of them.
     *
     * A file the browser has no viewer for -- a manual still in Word -- is
     * not previewable and is not served here at all. The page never offers a
     * preview for one, so a request for it is a request for something that
     * does not exist.
     */
    public function preview(Organization $currentOrganization, Product $product, ProductDocument $document): StreamedResponse
    {
        Gate::authorize('view', $product);

        abort_unless($document->previewContentType() !== null, 404);

        return Storage::disk(ProductDocument::DISK)->response($document->path, $document->name, [
            /**
             * From the allowlist, not from the column: the stored type is
             * whatever the uploading browser said it was.
             */
            'Content-Type' => $document->previewContentType(),
            /** And no sniffing around it either, whatever the bytes look like. */
            'X-Content-Type-Options' => 'nosniff',
            /**
             * A PDF can carry scripts and links of its own. None of it gets
             * to reach anything: the file is shown, and that is all it does.
             *
             * Deliberately without `sandbox`, which Chrome's PDF viewer
             * refuses to render under.
             */
            'Content-Security-Policy' => "default-src 'none'; img-src 'self' data:; object-src 'none'; base-uri 'none'; form-action 'none'; frame-ancestors 'self'",
            /** Private papers; no shared cache may keep a copy. */
            'Cache-Control' => 'private, max-age=0, must-revalidate',
        ]);
    }

    /**
     * Give the document the title it is shown under.
     *
     * The uploaded filename is kept as it was: it is what the team filed,
     * and the title is only what readers see. An empty title hands the
     * name back to the kind of document.
     */
    public function update(Request $request, Organization $currentOrganization, Product $product, ProductDocument $document): RedirectResponse
    {
        Gate::authorize('update', $product);

        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
        ]);

        $title = filled($validated['title'] ?? null) ? trim((string) $validated['title']) : null;

        if ($document->title !== $title) {
            $previous = $document->title;

            $document->update(['title' => $title]);

            $product->recordEvent(
                ProductEventType::DocumentRenamed,
                $request->user(),
                $currentOrganization,
                note: $document->name,
                changes: ['document_title' => ['from' => $previous, 'to' => $title]],
            );
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Document title saved.')]);

        return $this->backToProduct($currentOrganization, $product);
    }

    /**
     * Remove the document, and the file with it.
     */
    public function destroy(Request $request, Organization $currentOrganization, Product $product, ProductDocument $document): RedirectResponse
    {
        Gate::authorize('update', $product);

        $document->delete();

        $product->recordEvent(
            ProductEventType::DocumentRemoved,
            $request->user(),
            $currentOrganization,
            changes: ['document' => ['from' => $document->name, 'to' => null]],
        );

        $this->review->withdrawAfterEdit($product, $request->user(), $currentOrganization);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Document deleted.')]);

        return $this->backToProduct($currentOrganization, $product);
    }

    /**
     * Write an uploaded file to the disk, a HEIC photo as a JPEG.
     *
     * The path on disk is named here, never by the uploader. The name they
     * gave the file is kept in a column and handed back on download, so a
     * filename is something we show, never something that decides a path.
     *
     * @return array{name: string, path: string, mime_type: string, size: int}
     */
    protected function storeFile(UploadedFile $file, Product $product): array
    {
        if (! $this->heic->isHeic($file)) {
            $path = $file->store(ProductDocument::directoryFor($product), ProductDocument::DISK);

            if ($path === false) {
                throw new RuntimeException('The uploaded file could not be written.');
            }

            return [
                'name' => $file->getClientOriginalName(),
                'path' => $path,
                'mime_type' => $file->getClientMimeType(),
                'size' => (int) $file->getSize(),
            ];
        }

        $jpeg = $this->heic->toJpeg((string) $file->get());
        $path = ProductDocument::directoryFor($product).'/'.Str::random(40).'.jpg';

        if (! Storage::disk(ProductDocument::DISK)->put($path, $jpeg)) {
            throw new RuntimeException('The converted photo could not be written.');
        }

        return [
            'name' => $this->heic->jpegName($file->getClientOriginalName()),
            'path' => $path,
            'mime_type' => 'image/jpeg',
            'size' => strlen($jpeg),
        ];
    }

    /**
     * Send the writer back to the page they were filing from.
     */
    protected function backToProduct(Organization $currentOrganization, Product $product): RedirectResponse
    {
        return to_route('products.edit', [
            'current_organization' => $currentOrganization->slug,
            'product' => $product->id,
        ]);
    }
}
