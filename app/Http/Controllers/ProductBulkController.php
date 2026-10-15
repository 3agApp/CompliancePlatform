<?php

namespace App\Http\Controllers;

use App\Actions\Products\ReviewProduct;
use App\Enums\ProductEventType;
use App\Http\Requests\Products\BulkUpdateProductsRequest;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductDocument;
use App\Support\ProductChanges;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

/**
 * The product list's selection: one change, or one deletion, applied to
 * many products at once.
 *
 * Nothing here is new about a product. Each one is authorized, written,
 * recorded in its history and withdrawn from review exactly as it would be
 * from its own page -- the list only saves the trip to each of them.
 */
class ProductBulkController extends Controller
{
    public function __construct(protected ReviewProduct $review)
    {
        //
    }

    /**
     * Apply one change to every selected product.
     */
    public function update(BulkUpdateProductsRequest $request, Organization $currentOrganization): RedirectResponse
    {
        $products = $this->selected($currentOrganization, $request->validated('ids'));

        foreach ($products as $product) {
            Gate::authorize('update', $product);
        }

        $attributes = collect($request->validated())->except('ids')->all();

        $changed = DB::transaction(function () use ($products, $attributes, $request, $currentOrganization) {
            $changed = 0;

            foreach ($products as $product) {
                $product->update($this->attributesFor($product, $attributes));

                $changes = ProductChanges::of($product);

                if ($changes === []) {
                    continue;
                }

                $product->recordEvent(ProductEventType::Updated, $request->user(), $currentOrganization, changes: $changes);

                $this->review->withdrawAfterEdit($product, $request->user(), $currentOrganization);

                $changed++;
            }

            return $changed;
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => trans_choice('{0} Nothing to change.|{1} 1 product updated.|[2,*] :count products updated.', $changed),
        ]);

        return back();
    }

    /**
     * Delete every selected product.
     */
    public function destroy(Request $request, Organization $currentOrganization): RedirectResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:'.BulkUpdateProductsRequest::MAX_PRODUCTS],
            'ids.*' => ['integer', 'distinct'],
        ]);

        $products = $this->selected($currentOrganization, $validated['ids']);

        foreach ($products as $product) {
            Gate::authorize('delete', $product);
        }

        foreach ($products as $product) {
            /**
             * The foreign key takes the document rows, but a cascade fires
             * no model event and so leaves the files behind. They go first.
             */
            Storage::disk(ProductDocument::DISK)->deleteDirectory(ProductDocument::directoryFor($product));

            $product->delete();
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => trans_choice('{1} 1 product deleted.|[2,*] :count products deleted.', $products->count()),
        ]);

        return back();
    }

    /**
     * Get the selected products, as far as the organization can see them.
     *
     * Read through the same side the list is, so an id from somebody else's
     * catalogue is not found rather than refused. A selection that is not
     * entirely visible is not acted on at all: half a change would leave
     * the list in a state nobody chose.
     *
     * @param  array<int, int|string>  $ids
     * @return Collection<int, Product>
     */
    protected function selected(Organization $organization, array $ids): Collection
    {
        $relation = $organization->isSupplier()
            ? $organization->suppliedProducts()
            : $organization->products();

        $visible = $relation->whereIn('products.id', $ids)->pluck('products.id');

        abort_if($visible->count() !== count(array_unique($ids)), 404);

        return Product::query()
            ->whereKey($visible)
            ->with(['organization', 'supplierConnection.supplierOrganization'])
            ->get();
    }

    /**
     * Get what one product is to be written with.
     *
     * A brand belongs to the supplier it was named under, so a product
     * moved to another supplier cannot keep it: unless a brand was chosen
     * for the new one in the same change, it loses the old.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected function attributesFor(Product $product, array $attributes): array
    {
        $movesSupplier = array_key_exists('supplier_connection_id', $attributes)
            && (int) $attributes['supplier_connection_id'] !== $product->supplier_connection_id;

        if ($movesSupplier && ! array_key_exists('brand_id', $attributes)) {
            $attributes['brand_id'] = null;
        }

        return $attributes;
    }
}
