<?php

namespace App\Http\Controllers;

use App\Data\ProductFilters;
use App\Enums\CountryOfOrigin;
use App\Enums\ProductDocumentType;
use App\Enums\ProductRequirement;
use App\Enums\SupplierConnectionStatus;
use App\Http\Requests\Products\SaveProductRequest;
use App\Models\Brand;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductDocument;
use App\Models\ProductTemplate;
use App\Models\SupplierConnection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
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
    public function index(Request $request, Organization $currentOrganization): Response|RedirectResponse
    {
        Gate::authorize('viewAny', [Product::class, $currentOrganization]);

        $isSupplier = $currentOrganization->isSupplier();
        $filters = ProductFilters::fromRequest($request);

        $products = $this->products($currentOrganization, $isSupplier, $filters);

        /**
         * A page past the end is reachable by narrowing a filter from a deep
         * page, or by a stale bookmark. Sending the last page back beats
         * rendering an empty table that offers no way out but the browser's
         * back button.
         */
        if ($products->isEmpty() && $products->currentPage() > 1) {
            return redirect($products->url($products->lastPage()));
        }

        return Inertia::render('products/index', [
            'products' => $products->through(fn (Product $product) => $this->toProductArray($product, $isSupplier)),
            'pageSizes' => ProductFilters::PAGE_SIZES,
            'permissions' => $request->user()->toProductPermissions($currentOrganization),
            'availableConnections' => $this->assignableConnections($currentOrganization),
            /**
             * A product cannot be created without a template, so the list
             * has to be able to say so before sending anyone to a form they
             * would be stuck on. The flag rather than the templates: the
             * list itself has no use for them.
             */
            'hasTemplates' => $currentOrganization->isDistributor()
                && $currentOrganization->productTemplates()->exists(),
            'counterparties' => $this->counterparties($currentOrganization, $isSupplier),
            'filterableCategories' => $this->filterableCategories($currentOrganization, $isSupplier),
            'filterableBrands' => $this->filterableBrands($currentOrganization, $isSupplier),
            'filters' => $filters,
            'hasProducts' => $products->total() > 0
                || $this->visibleProducts($currentOrganization, $isSupplier)->exists(),
            'viewerType' => $currentOrganization->type->value,
        ]);
    }

    /**
     * Show the page a product is created from.
     *
     * A page rather than a dialog, because creating a product now begins
     * with classifying it -- supplier, legal family, template -- and only
     * then asks for the details, which the template has a say in. Every
     * template the organization keeps goes down with it, so choosing a
     * family narrows the sheets without another trip to the server.
     */
    public function create(Request $request, Organization $currentOrganization): Response
    {
        Gate::authorize('create', [Product::class, $currentOrganization]);

        return Inertia::render('products/create', [
            'availableCountries' => CountryOfOrigin::options(),
            'availableCategories' => $this->availableCategories($currentOrganization),
            'availableTemplates' => $this->availableTemplates($currentOrganization),
            'availableBrands' => $this->availableBrands($currentOrganization),
            'availableConnections' => $this->assignableConnections($currentOrganization),
            'availableRequirements' => ProductRequirement::options(),
            'canCreateBrand' => $request->user()->toBrandPermissions($currentOrganization)->canCreateBrand,
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

        $product->load(['organization', 'brand', 'category', 'template', 'supplierConnection.distributorOrganization', 'supplierConnection.supplierOrganization', 'documents.uploader']);

        return Inertia::render('products/edit', [
            'product' => [
                ...$this->toProductArray($product, $isSupplier),
                ...$this->toComplianceArray($product),
                'documents' => $this->toDocumentArray($product),
            ],
            'permissions' => $request->user()->toProductPermissions($currentOrganization),
            'availableCountries' => CountryOfOrigin::options(),
            'availableDocumentTypes' => ProductDocumentType::options(),
            'availableCategories' => $this->availableCategories($product->organization),
            'availableTemplates' => $this->availableTemplates($product->organization),
            'availableBrands' => $this->availableBrands($product->organization),
            'availableConnections' => $this->assignableConnections($currentOrganization),
            'availableRequirements' => ProductRequirement::options(),
            'canCreateBrand' => $request->user()->toBrandPermissions($currentOrganization)->canCreateBrand,
            'completeness' => $product->completeness(),
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
            'product' => $product->id,
        ]);
    }

    /**
     * Remove the specified product.
     */
    public function destroy(Organization $currentOrganization, Product $product): RedirectResponse
    {
        Gate::authorize('delete', $product);

        /**
         * The foreign key takes the document rows, but a cascade fires no
         * model event and so leaves the files behind. They go first.
         */
        Storage::disk(ProductDocument::DISK)->deleteDirectory(ProductDocument::directoryFor($product));

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
        /**
         * The template and the kinds of paper already filed come along on
         * every read, because each row is scored against its sheet before
         * it is sent. Without them that is two queries a product.
         */
        $scoring = ['template', 'documents:id,product_id,type'];

        if ($asSupplier) {
            return $organization->suppliedProducts()
                ->with([...$scoring, 'brand', 'category', 'supplierConnection.distributorOrganization']);
        }

        return $organization->products()
            ->with([...$scoring, 'brand', 'category', 'supplierConnection.supplierOrganization']);
    }

    /**
     * Get the products the organization can see, narrowed by the filters.
     *
     * Columns are qualified because the supplier side joins products and
     * supplier_connections, which share id and both timestamps.
     *
     * @return LengthAwarePaginator<int, Product>
     */
    protected function products(Organization $organization, bool $asSupplier, ProductFilters $filters): LengthAwarePaginator
    {
        return $this->visibleProducts($organization, $asSupplier)
            ->when($filters->connection, fn ($query, int $connectionId) => $query->assignedTo($connectionId))
            ->when($filters->category, fn ($query, int $categoryId) => $query->inCategory($categoryId))
            ->when($filters->brand, fn ($query, int $brandId) => $query->ofBrand($brandId))
            ->when($filters->search, fn ($query, string $term) => $query->matching($term))
            ->orderBy('products.name')
            ->paginate($filters->perPage)
            ->withQueryString();
    }

    /**
     * Get the counterparties the product list can be filtered by.
     *
     * Every connection that can hold a visible product is offered, including
     * a distributor's revoked ones: their products stay in the list, so they
     * have to stay reachable. The connection names itself rather than naming
     * an organization, because a supplier a distributor has invited may not
     * have an organization yet.
     *
     * @return array<array{id: int, label: string}>
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
                'id' => $connection->id,
                'label' => $asSupplier
                    ? $connection->distributorOrganization->name
                    : $this->connectionLabel($connection),
            ])
            ->sortBy('label')
            ->values()
            ->toArray();
    }

    /**
     * Get the legal families the product list can be filtered by.
     *
     * A distributor is offered its whole list, including families nothing is
     * filed under yet -- an empty result is itself an answer. A supplier has
     * no list of its own, so it is offered the families actually present on
     * the products assigned to it, each named with the distributor it came
     * from: two distributors both calling a family "Toy" are two different
     * rows, and a bare name would make them look like one.
     *
     * @return array<array{id: int, label: string}>
     */
    protected function filterableCategories(Organization $organization, bool $asSupplier): array
    {
        if (! $asSupplier) {
            return $this->availableCategories($organization);
        }

        $categoryIds = $organization->suppliedProducts()
            ->whereNotNull('products.product_category_id')
            ->distinct()
            ->pluck('products.product_category_id');

        return ProductCategory::query()
            ->whereIn('id', $categoryIds)
            ->with('organization')
            ->orderBy('name')
            ->get()
            ->map(fn (ProductCategory $category) => [
                'id' => $category->id,
                'label' => "{$category->name} ({$category->organization->name})",
            ])
            ->values()
            ->toArray();
    }

    /**
     * Get the brands the product list can be filtered by.
     *
     * Read the same way the categories are: a distributor is offered
     * everything its trades name, a supplier only the brands present on the
     * products assigned to it, each named with the distributor whose
     * catalog it sits in.
     *
     * @return array<array{id: int, label: string}>
     */
    protected function filterableBrands(Organization $organization, bool $asSupplier): array
    {
        if (! $asSupplier) {
            return $this->availableBrands($organization);
        }

        $brandIds = $organization->suppliedProducts()
            ->whereNotNull('products.brand_id')
            ->distinct()
            ->pluck('products.brand_id');

        return Brand::query()
            ->whereIn('id', $brandIds)
            ->with('supplierConnection.distributorOrganization')
            ->orderBy('name')
            ->get()
            ->map(fn (Brand $brand) => [
                'id' => $brand->id,
                'label' => "{$brand->name} ({$brand->supplierConnection->distributorOrganization->name})",
            ])
            ->values()
            ->toArray();
    }

    /**
     * Get the brands a product may carry.
     *
     * A maker is named under one trade, so a product may only carry one of
     * its own supplier's. The whole set the distributor can reach goes down
     * at once and each option carries its connection: the form narrows them
     * to the supplier already chosen, the same way it narrows the templates
     * to the family.
     *
     * The list always comes from the distributor that owns the product
     * rather than from the organization doing the looking -- a supplier
     * editing a product sees the brands of that trade, which is the trade
     * they are on either way.
     *
     * @return array<array{id: int, label: string, supplier_connection_id: int}>
     */
    protected function availableBrands(Organization $organization): array
    {
        if (! $organization->isDistributor()) {
            return [];
        }

        return $organization->brands()
            ->orderBy('brands.name')
            ->get()
            ->map(fn (Brand $brand) => $brand->toOption())
            ->values()
            ->toArray();
    }

    /**
     * Get the legal families a product may be filed under.
     *
     * The list always comes from the organization that owns the product, not
     * from the one doing the looking. A supplier editing a product assigned
     * to them is choosing from their distributor's families, because that is
     * whose catalog the answer ends up in.
     *
     * @return array<array{id: int, label: string}>
     */
    protected function availableCategories(Organization $organization): array
    {
        if (! $organization->isDistributor()) {
            return [];
        }

        return $organization->productCategories()
            ->orderBy('name')
            ->get()
            ->map(fn (ProductCategory $category) => $category->toOption())
            ->values()
            ->toArray();
    }

    /**
     * Get the homework sheets a product may be held to.
     *
     * The whole set for the organization, not one family's worth: the form
     * narrows them to the chosen family on the client, so switching family
     * does not cost a round trip. Each option carries its category and what
     * it asks for, which is what does the narrowing and the marking.
     *
     * Read from the owner's list for the same reason the categories are --
     * a supplier editing a product is choosing from their distributor's
     * sheets, because that is whose catalog the answer lands in.
     *
     * @return array<array{id: int, label: string, product_category_id: int, requirements: array<int, string>}>
     */
    protected function availableTemplates(Organization $organization): array
    {
        if (! $organization->isDistributor()) {
            return [];
        }

        return $organization->productTemplates()
            ->orderBy('product_templates.name')
            ->get()
            ->map(fn (ProductTemplate $template) => $template->toOption())
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
     * Transform the compliance details of the product for the frontend.
     *
     * Kept apart from the list payload on purpose: seven paragraphs of prose
     * on every row of a catalog would be carried across the wire by every
     * page that only ever shows a name.
     *
     * @return array{age_grading: string|null, safety_notice: string|null, warning_text: string|null, material_information: string|null, usage_restrictions: string|null, safety_instructions: string|null, additional_notes: string|null}
     */
    protected function toComplianceArray(Product $product): array
    {
        return [
            'age_grading' => $product->age_grading,
            'safety_notice' => $product->safety_notice,
            'warning_text' => $product->warning_text,
            'material_information' => $product->material_information,
            'usage_restrictions' => $product->usage_restrictions,
            'safety_instructions' => $product->safety_instructions,
            'additional_notes' => $product->additional_notes,
        ];
    }

    /**
     * Transform the papers filed against the product for the frontend.
     *
     * No address is sent with them. The files are private and the page builds
     * the download link itself, so nothing here is a URL that could be
     * mistaken for one that works on its own.
     *
     * @return array<array{id: int, type: string, type_label: string, name: string, size: int, uploaded_by: string|null, created_at: string|null}>
     */
    protected function toDocumentArray(Product $product): array
    {
        return $product->documents
            ->map(fn (ProductDocument $document) => [
                'id' => $document->id,
                'type' => $document->type->value,
                'type_label' => $document->type->label(),
                'name' => $document->name,
                'size' => $document->size,
                'uploaded_by' => $document->uploader?->name,
                'created_at' => $document->created_at?->toISOString(),
            ])
            ->values()
            ->toArray();
    }

    /**
     * Transform the product for the frontend.
     *
     * The completeness score travels with every row and the seven columns
     * it is read from do not: the prose is scored here and left here, so a
     * catalog listing names still carries nothing but the number.
     *
     * @return array{id: int, name: string, brand_id: int|null, brand_label: string|null, product_category_id: int, category_label: string, product_template_id: int, template_label: string, completeness_score: int, ean: string|null, internal_article_number: string|null, supplier_article_number: string|null, order_number: string|null, customs_tariff_number: string|null, country_of_origin: string|null, country_of_origin_label: string|null, supplier_connection_id: int|null, counterparty: string|null, connection_status: string|null, created_at: string|null}
     */
    protected function toProductArray(Product $product, bool $asSupplier = false): array
    {
        $connection = $product->supplierConnection;

        return [
            'id' => $product->id,
            'name' => $product->name,
            'brand_id' => $product->brand_id,
            'brand_label' => $product->brand?->name,
            'product_category_id' => $product->product_category_id,
            'category_label' => $product->category->name,
            'product_template_id' => $product->product_template_id,
            'template_label' => $product->template->name,
            'completeness_score' => $product->completeness()->score,
            'ean' => $product->ean,
            'internal_article_number' => $product->internal_article_number,
            'supplier_article_number' => $product->supplier_article_number,
            'order_number' => $product->order_number,
            'customs_tariff_number' => $product->customs_tariff_number,
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
