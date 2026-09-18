<?php

namespace App\Http\Controllers;

use App\Data\ProductFilters;
use App\Enums\CountryOfOrigin;
use App\Enums\SupplierConnectionStatus;
use App\Http\Requests\Products\SaveProductRequest;
use App\Models\Organization;
use App\Models\Product;
use App\Models\SupplierConnection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Products are shared by both sides of the platform: the distributor owns
 * them, and the supplier they are assigned to fills in their details. The
 * page is the same for both, with the counterparty column and the available
 * actions decided by the viewing organization's type and permissions.
 */
class ProductController extends Controller
{
    /**
     * Display a listing of the organization's products.
     */
    public function index(Request $request, Organization $currentOrganization): Response
    {
        Gate::authorize('viewAny', [Product::class, $currentOrganization]);

        $isSupplier = $currentOrganization->isSupplier();
        $filters = ProductFilters::fromRequest($request);

        $products = $this->products($currentOrganization, $isSupplier, $filters);

        return Inertia::render('products/index', [
            'products' => $products->map(fn (Product $product) => $this->toProductArray($product, $isSupplier)),
            'permissions' => $request->user()->toProductPermissions($currentOrganization),
            'availableCountries' => CountryOfOrigin::options(),
            'availableConnections' => $this->assignableConnections($currentOrganization),
            'counterparties' => $this->counterparties($currentOrganization, $isSupplier),
            'filters' => $filters,
            'hasProducts' => $products->isNotEmpty()
                || $this->visibleProducts($currentOrganization, $isSupplier)->exists(),
            'viewerType' => $currentOrganization->type->value,
        ]);
    }

    /**
     * Store a newly created product.
     */
    public function store(SaveProductRequest $request, Organization $currentOrganization): RedirectResponse
    {
        Gate::authorize('create', [Product::class, $currentOrganization]);

        $currentOrganization->products()->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Product created.')]);

        return to_route('products.index', ['current_organization' => $currentOrganization->slug]);
    }

    /**
     * Show the product edit page.
     */
    public function edit(Request $request, Organization $currentOrganization, Product $product): Response
    {
        Gate::authorize('view', $product);

        $isSupplier = $currentOrganization->isSupplier();

        $product->load(['supplierConnection.distributorOrganization', 'supplierConnection.supplierOrganization']);

        return Inertia::render('products/edit', [
            'product' => $this->toProductArray($product, $isSupplier),
            'permissions' => $request->user()->toProductPermissions($currentOrganization),
            'availableCountries' => CountryOfOrigin::options(),
            'availableConnections' => $this->assignableConnections($currentOrganization),
            'viewerType' => $currentOrganization->type->value,
        ]);
    }

    /**
     * Update the specified product.
     */
    public function update(SaveProductRequest $request, Organization $currentOrganization, Product $product): RedirectResponse
    {
        Gate::authorize('update', $product);

        $product->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Product updated.')]);

        return to_route('products.edit', [
            'current_organization' => $currentOrganization->slug,
            'product' => $product->uuid,
        ]);
    }

    /**
     * Remove the specified product.
     */
    public function destroy(Organization $currentOrganization, Product $product): RedirectResponse
    {
        Gate::authorize('delete', $product);

        $product->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Product deleted.')]);

        return to_route('products.index', ['current_organization' => $currentOrganization->slug]);
    }

    /**
     * Get the base query for the products the organization can see, from the
     * side it sits on.
     *
     * Both sides are already narrowed to the viewer here: a distributor by
     * ownership, a supplier by its active connections. Every filter applied
     * on top composes onto that, so no filter can widen what is visible.
     *
     * @return HasMany<Product, Organization>|HasManyThrough<Product, SupplierConnection, Organization>
     */
    protected function visibleProducts(Organization $organization, bool $asSupplier): HasMany|HasManyThrough
    {
        if ($asSupplier) {
            return $organization->suppliedProducts()
                ->with('supplierConnection.distributorOrganization');
        }

        return $organization->products()
            ->with('supplierConnection.supplierOrganization');
    }

    /**
     * Get the products the organization can see, narrowed by the filters.
     *
     * Columns are qualified because the supplier side joins products and
     * supplier_connections, which share id, uuid and both timestamps.
     *
     * @return Collection<int, Product>
     */
    protected function products(Organization $organization, bool $asSupplier, ProductFilters $filters): Collection
    {
        return $this->visibleProducts($organization, $asSupplier)
            ->when($filters->connection, fn ($query, string $uuid) => $query->assignedTo($uuid))
            ->when($filters->search, fn ($query, string $term) => $query->matching($term))
            ->orderBy('products.name')
            ->get();
    }

    /**
     * Get the counterparties the product list can be filtered by.
     *
     * Every connection that can hold a visible product is offered, including
     * a distributor's revoked ones: their products stay in the list, so they
     * have to stay reachable. The connection is named by its uuid rather than
     * by an organization, because a supplier a distributor has invited may
     * not have an organization yet.
     *
     * @return array<array{uuid: string, label: string}>
     */
    protected function counterparties(Organization $organization, bool $asSupplier): array
    {
        $connections = $asSupplier
            ? $organization->distributorConnections()
                ->where('status', SupplierConnectionStatus::Active)
                ->with('distributorOrganization')
                ->get()
            : $organization->supplierConnections()->with('supplierOrganization')->get();

        return $connections
            ->map(fn (SupplierConnection $connection) => [
                'uuid' => $connection->uuid,
                'label' => $asSupplier
                    ? $connection->distributorOrganization->name
                    : $this->connectionLabel($connection),
            ])
            ->sortBy('label')
            ->values()
            ->toArray();
    }

    /**
     * Get the suppliers a product may be assigned to.
     *
     * Pending connections are included so a distributor can assign products
     * to a supplier who has not accepted their invitation yet.
     *
     * @return array<array{id: int, label: string, isPending: bool}>
     */
    protected function assignableConnections(Organization $organization): array
    {
        if (! $organization->isDistributor()) {
            return [];
        }

        return $organization->supplierConnections()
            ->assignable()
            ->with('supplierOrganization')
            ->orderBy('company_name')
            ->get()
            ->map(fn (SupplierConnection $connection) => [
                'id' => $connection->id,
                'label' => $this->connectionLabel($connection),
                'isPending' => $connection->isPending(),
            ])
            ->values()
            ->toArray();
    }

    /**
     * Get the name to show for a connection's supplier.
     *
     * Until the supplier claims the invitation the only name we have is the
     * one the distributor typed.
     */
    protected function connectionLabel(SupplierConnection $connection): string
    {
        $supplier = $connection->supplierOrganization;

        return $supplier !== null ? $supplier->name : $connection->company_name;
    }

    /**
     * Transform the product for the frontend.
     *
     * @return array{uuid: string, name: string, ean: string|null, country_of_origin: string|null, country_of_origin_label: string|null, supplier_connection_id: int|null, counterparty: string|null, connection_status: string|null, created_at: string|null}
     */
    protected function toProductArray(Product $product, bool $asSupplier = false): array
    {
        $connection = $product->supplierConnection;

        return [
            'uuid' => $product->uuid,
            'name' => $product->name,
            'ean' => $product->ean,
            'country_of_origin' => $product->country_of_origin?->value,
            'country_of_origin_label' => $product->country_of_origin?->label(),
            'supplier_connection_id' => $connection?->id,
            'counterparty' => $connection === null
                ? null
                : ($asSupplier ? $connection->distributorOrganization->name : $this->connectionLabel($connection)),
            'connection_status' => $connection?->status->value,
            'created_at' => $product->created_at?->toISOString(),
        ];
    }
}
