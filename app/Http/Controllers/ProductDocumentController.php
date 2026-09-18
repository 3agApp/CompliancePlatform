<?php

namespace App\Http\Controllers;

use App\Http\Requests\Products\SaveProductDocumentRequest;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
    /**
     * File a document against the product.
     */
    public function store(SaveProductDocumentRequest $request, Organization $currentOrganization, Product $product): RedirectResponse
    {
        Gate::authorize('update', $product);

        $file = $request->uploadedFile();

        /**
         * store() names the file on disk itself. The name the uploader gave
         * it is kept in a column and handed back on download, so a filename
         * is something we show, never something that decides a path.
         */
        $path = $file->store(ProductDocument::directoryFor($product), ProductDocument::DISK);

        $product->documents()->create([
            'type' => $request->validated('type'),
            'name' => $file->getClientOriginalName(),
            'path' => $path,
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize(),
            'uploaded_by' => $request->user()->id,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Document uploaded.')]);

        return $this->backToProduct($currentOrganization, $product);
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
     * Remove the document, and the file with it.
     */
    public function destroy(Organization $currentOrganization, Product $product, ProductDocument $document): RedirectResponse
    {
        Gate::authorize('update', $product);

        $document->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Document deleted.')]);

        return $this->backToProduct($currentOrganization, $product);
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
