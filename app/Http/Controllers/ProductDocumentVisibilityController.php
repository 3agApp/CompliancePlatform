<?php

namespace App\Http\Controllers;

use App\Enums\ProductEventType;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * Releasing a document to the product's public page, or taking it back.
 *
 * One document at a time and never by default: what a buyer may download
 * is a decision somebody makes, and the product's history keeps a line
 * about who made it.
 */
class ProductDocumentVisibilityController extends Controller
{
    /**
     * Set whether the document shows on the public page.
     */
    public function __invoke(Request $request, Organization $currentOrganization, Product $product, ProductDocument $document): RedirectResponse
    {
        Gate::authorize('publishDocuments', $product);

        $validated = $request->validate([
            'is_public' => ['required', 'boolean'],
        ]);

        $isPublic = (bool) $validated['is_public'];

        if ($document->is_public !== $isPublic) {
            $document->forceFill(['is_public' => $isPublic])->save();

            $product->recordEvent(
                $isPublic ? ProductEventType::DocumentPublished : ProductEventType::DocumentUnpublished,
                $request->user(),
                $currentOrganization,
                note: $document->name,
            );
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $isPublic ? __('Document shown on the public page.') : __('Document hidden from the public page.'),
        ]);

        return back();
    }
}
