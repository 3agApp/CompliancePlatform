<?php

namespace App\Http\Controllers;

use App\Enums\ProductDocumentType;
use App\Enums\ProductEventType;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * Showing every picture of a product on its public page at once, or hiding
 * them all.
 *
 * Imported products arrive with a handful of photos each, and switching ten
 * of them one by one is a chore nobody should have. Each image that changes
 * still gets its own line in the history, as it would from its own switch.
 */
class ProductImageVisibilityController extends Controller
{
    /**
     * Set whether the product's images show on the public page.
     */
    public function __invoke(Request $request, Organization $currentOrganization, Product $product): RedirectResponse
    {
        Gate::authorize('publishDocuments', $product);

        $isPublic = (bool) $request->validate([
            'is_public' => ['required', 'boolean'],
        ])['is_public'];

        $changed = DB::transaction(function () use ($product, $isPublic, $request, $currentOrganization): int {
            $images = $product->documents()
                ->where('type', ProductDocumentType::ProductImage)
                ->where('is_public', ! $isPublic)
                ->get();

            $images->each(function (ProductDocument $image) use ($product, $isPublic, $request, $currentOrganization) {
                $image->forceFill(['is_public' => $isPublic])->save();

                $product->recordEvent(
                    $isPublic ? ProductEventType::DocumentPublished : ProductEventType::DocumentUnpublished,
                    $request->user(),
                    $currentOrganization,
                    note: $image->name,
                );
            });

            return $images->count();
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $isPublic
                ? trans_choice('{0} Every image is already on the public page.|{1} 1 image shown on the public page.|[2,*] :count images shown on the public page.', $changed)
                : trans_choice('{0} No image was on the public page.|{1} 1 image hidden from the public page.|[2,*] :count images hidden from the public page.', $changed),
        ]);

        return back();
    }
}
