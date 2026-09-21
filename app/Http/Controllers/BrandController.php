<?php

namespace App\Http\Controllers;

use App\Enums\SupplierConnectionStatus;
use App\Http\Requests\Brands\SaveBrandRequest;
use App\Models\Brand;
use App\Models\Organization;
use App\Models\SupplierConnection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The makers behind each trade. The brand used to be typed onto every
 * product, which spelled the same maker three ways across a catalog and
 * left nothing that could be counted or filtered by.
 *
 * A brand is named under one supplier connection, so the page is the same
 * for both sides: a distributor reads it grouped by the suppliers they buy
 * from, a supplier by the distributors they sell to.
 */
class BrandController extends Controller
{
    /**
     * Display the brands reachable from the current organization.
     */
    public function index(Request $request, Organization $currentOrganization): Response
    {
        Gate::authorize('viewAny', [Brand::class, $currentOrganization]);

        return Inertia::render('brands/index', [
            'connections' => $this->connections($request, $currentOrganization),
            'permissions' => $request->user()->toBrandPermissions($currentOrganization),
            'viewerType' => $currentOrganization->type->value,
        ]);
    }

    /**
     * Name a new maker under one of the organization's trades.
     */
    public function store(SaveBrandRequest $request, Organization $currentOrganization): RedirectResponse
    {
        $connection = SupplierConnection::query()
            ->whereKey($request->validated('supplier_connection_id'))
            ->sole();

        Gate::authorize('create', [Brand::class, $connection]);

        $connection->brands()->create($request->safe()->only('name'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Brand created.')]);

        /**
         * Back where the request came from rather than to the brands page.
         * A brand is named from two places -- its own list, and inline on a
         * product form -- and the product form must come back to itself
         * with what was already typed into it still there.
         */
        return back();
    }

    /**
     * Update the specified brand.
     */
    public function update(SaveBrandRequest $request, Organization $currentOrganization, Brand $brand): RedirectResponse
    {
        Gate::authorize('update', $brand);

        $brand->update($request->safe()->only('name'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Brand updated.')]);

        return to_route('brands.index', ['current_organization' => $currentOrganization->slug]);
    }

    /**
     * Remove the specified brand.
     *
     * A brand still on a product is kept. The column would take the null
     * happily, so refusing here is what stops a delete from quietly stripping
     * the maker off products the organization has to answer for. The count
     * goes back with the refusal because "reassign them first" is only
     * actionable if you know how many there are.
     */
    public function destroy(Organization $currentOrganization, Brand $brand): RedirectResponse
    {
        Gate::authorize('delete', $brand);

        $productCount = $brand->products()->count();

        if ($productCount > 0) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => trans_choice(
                    '{1}:name is still carried by one product. Move it to another brand first.|[2,*]:name is still carried by :count products. Move them to another brand first.',
                    $productCount,
                    ['name' => $brand->name, 'count' => $productCount],
                ),
            ]);

            return to_route('brands.index', ['current_organization' => $currentOrganization->slug]);
        }

        $brand->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Brand deleted.')]);

        return to_route('brands.index', ['current_organization' => $currentOrganization->slug]);
    }

    /**
     * Transform the organization's trades, each with the makers named under
     * it, for the frontend.
     *
     * Grouped rather than flat because a bare list of names would put two
     * suppliers' "Alpro" next to each other with nothing to tell them
     * apart. A trade with no brands yet is still listed: it is where the
     * first one gets added.
     *
     * @return array<array{id: int, label: string, status: string, canAddBrand: bool, brands: array<array{id: int, name: string, products_count: int}>}>
     */
    protected function connections(Request $request, Organization $organization): array
    {
        $isSupplier = $organization->isSupplier();

        return $this->visibleConnections($organization, $isSupplier)
            ->map(fn (SupplierConnection $connection) => [
                'id' => $connection->id,
                'label' => $isSupplier
                    ? $connection->distributorOrganization->name
                    : $this->counterpartyLabel($connection),
                'status' => $connection->status->value,
                'canAddBrand' => $request->user()->can('create', [Brand::class, $connection]),
                'brands' => $connection->brands
                    ->map(fn (Brand $brand) => [
                        'id' => $brand->id,
                        'name' => $brand->name,
                        'products_count' => (int) $brand->products_count,
                    ])
                    ->values()
                    ->toArray(),
            ])
            ->sortBy('label')
            ->values()
            ->toArray();
    }

    /**
     * Get the trades the organization can read brands through.
     *
     * A distributor sees every connection it holds, revoked ones included:
     * their products stay in the catalog and still carry these makers. A
     * supplier sees only its live ones, which is the same line its products
     * are drawn on.
     *
     * @return Collection<int, SupplierConnection>
     */
    protected function visibleConnections(Organization $organization, bool $asSupplier): Collection
    {
        $query = $asSupplier
            ? $organization->distributorConnections()
                ->where('status', SupplierConnectionStatus::Active)
                ->with('distributorOrganization')
            : $organization->supplierConnections()->with('supplierOrganization');

        return $query
            ->with(['brands' => fn ($brands) => $brands->withCount('products')])
            ->get();
    }

    /**
     * Get the name to show for a connection's supplier.
     *
     * Until the supplier claims the invitation the only name we have is the
     * one the distributor typed.
     */
    protected function counterpartyLabel(SupplierConnection $connection): string
    {
        $supplier = $connection->supplierOrganization;

        return $supplier !== null ? $supplier->name : $connection->company_name;
    }
}
