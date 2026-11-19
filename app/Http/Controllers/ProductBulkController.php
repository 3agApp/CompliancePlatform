<?php

namespace App\Http\Controllers;

use App\Actions\Products\ReviewProduct;
use App\Enums\Country;
use App\Enums\ProductEventType;
use App\Http\Requests\Products\BulkUpdateProductsRequest;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductDocument;
use App\Models\SupplierConnection;
use App\Support\ProductChanges;
use BackedEnum;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
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

        $attributes = collect($request->validated())->except(['ids', 'only_empty'])->all();
        $onlyEmpty = $request->boolean('only_empty');

        $this->ensureCountryIsProduced($products, $attributes);

        $changed = DB::transaction(function () use ($products, $attributes, $onlyEmpty, $request, $currentOrganization) {
            $changed = 0;

            foreach ($products as $product) {
                $product->update($this->attributesFor($product, $attributes, $onlyEmpty));

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
     * Refuse a country a selected product's supplier does not produce in.
     *
     * Checked against the products already loaded for the change, so a
     * large selection is not read twice. A product that already carries
     * the country keeps it, the same as when it is saved on its own --
     * unless it is moving to another supplier, whose list then decides.
     *
     * @param  Collection<int, Product>  $products
     * @param  array<string, mixed>  $attributes
     */
    protected function ensureCountryIsProduced(Collection $products, array $attributes): void
    {
        $country = Country::tryFrom((string) ($attributes['country_of_origin'] ?? ''));

        if ($country === null) {
            return;
        }

        $connection = isset($attributes['supplier_connection_id'])
            ? SupplierConnection::with('supplierOrganization')->find((int) $attributes['supplier_connection_id'])
            : null;

        $refused = $products->first(function (Product $product) use ($country, $connection): bool {
            if ($connection === null && $product->country_of_origin === $country) {
                return false;
            }

            $allowed = ($connection ?? $product->supplierConnection)?->allowedCountriesOfOrigin();

            return $allowed !== null && ! $allowed->contains($country);
        });

        if ($refused !== null) {
            throw ValidationException::withMessages([
                'country_of_origin' => __(':product comes from a supplier that does not produce in this country.', [
                    'product' => $refused->name,
                ]),
            ]);
        }
    }

    /**
     * Get what one product is to be written with.
     *
     * A brand belongs to the supplier it was named under, so a product
     * moved to another supplier cannot keep it: unless a brand was chosen
     * for the new one in the same change, it loses the old.
     *
     * Filling only the empty ones leaves every detail a product already
     * holds alone, so a series answer never replaces a product's own.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected function attributesFor(Product $product, array $attributes, bool $onlyEmpty = false): array
    {
        if ($onlyEmpty) {
            $fillable = [...array_keys(BulkUpdateProductsRequest::COMPLIANCE_FIELDS), 'customs_tariff_number', 'country_of_origin'];

            $attributes = collect($attributes)
                ->reject(fn (mixed $value, string $key): bool => in_array($key, $fillable, true) && $this->isFilled($product->getAttribute($key)))
                ->all();
        }

        $movesSupplier = array_key_exists('supplier_connection_id', $attributes)
            && (int) $attributes['supplier_connection_id'] !== $product->supplier_connection_id;

        if ($movesSupplier && ! array_key_exists('brand_id', $attributes)) {
            $attributes['brand_id'] = null;
        }

        return $attributes;
    }

    /**
     * Determine if a product already holds something for a detail.
     */
    protected function isFilled(mixed $value): bool
    {
        if ($value instanceof BackedEnum) {
            $value = $value->value;
        }

        return $value !== null && trim((string) $value) !== '';
    }
}
