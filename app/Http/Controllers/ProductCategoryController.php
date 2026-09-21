<?php

namespace App\Http\Controllers;

use App\Enums\ProductRequirement;
use App\Http\Requests\ProductCategories\SaveProductCategoryRequest;
use App\Models\Organization;
use App\Models\ProductCategory;
use App\Models\ProductTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The distributor's own list of legal families. A product is filed under one
 * of these, and which rules it has to answer for follows from that, so the
 * list is managed here rather than typed in free-hand on every product.
 */
class ProductCategoryController extends Controller
{
    /**
     * Display the organization's categories.
     */
    public function index(Request $request, Organization $currentOrganization): Response
    {
        Gate::authorize('viewAny', [ProductCategory::class, $currentOrganization]);

        return Inertia::render('categories/index', [
            'categories' => $this->categories($currentOrganization),
            'permissions' => $request->user()->toProductCategoryPermissions($currentOrganization),
            'availableRequirements' => ProductRequirement::options(),
        ]);
    }

    /**
     * Store a newly created category.
     */
    public function store(SaveProductCategoryRequest $request, Organization $currentOrganization): RedirectResponse
    {
        Gate::authorize('create', [ProductCategory::class, $currentOrganization]);

        $currentOrganization->productCategories()->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Category created.')]);

        return to_route('categories.index', ['current_organization' => $currentOrganization->slug]);
    }

    /**
     * Update the specified category.
     */
    public function update(SaveProductCategoryRequest $request, Organization $currentOrganization, ProductCategory $productCategory): RedirectResponse
    {
        Gate::authorize('update', $productCategory);

        $productCategory->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Category updated.')]);

        return to_route('categories.index', ['current_organization' => $currentOrganization->slug]);
    }

    /**
     * Remove the specified category.
     *
     * A category still on a product is kept. The column would take the null
     * happily, so refusing here is what stops a delete from quietly stripping
     * the legal family off products the organization has to answer for. The
     * count goes back with the refusal because "reassign them first" is only
     * actionable if you know how many there are.
     */
    public function destroy(Organization $currentOrganization, ProductCategory $productCategory): RedirectResponse
    {
        Gate::authorize('delete', $productCategory);

        $productCount = $productCategory->products()->count();

        if ($productCount > 0) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => trans_choice(
                    '{1}:name is still used by one product. Move it to another category first.|[2,*]:name is still used by :count products. Move them to another category first.',
                    $productCount,
                    ['name' => $productCategory->name, 'count' => $productCount],
                ),
            ]);

            return to_route('categories.index', ['current_organization' => $currentOrganization->slug]);
        }

        $productCategory->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Category deleted.')]);

        return to_route('categories.index', ['current_organization' => $currentOrganization->slug]);
    }

    /**
     * Transform the organization's categories for the frontend.
     *
     * The product count travels with each row so the list can say what a
     * category is holding, and the delete dialog can explain itself before
     * the request that would be refused is ever sent.
     *
     * The templates come with them. A category is only half a thing without
     * the sheets filed under it -- an empty one cannot take a product at all
     * -- so the screen that manages one manages both, and the payload is
     * shaped to match.
     *
     * @return array<array{id: int, name: string, products_count: int, templates: array<array{id: int, name: string, products_count: int, requirements: array<int, string>}>}>
     */
    protected function categories(Organization $organization): array
    {
        return $organization->productCategories()
            ->withCount('products')
            ->with(['templates' => fn ($query) => $query->withCount('products')])
            ->orderBy('name')
            ->get()
            ->map(fn (ProductCategory $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'products_count' => (int) $category->products_count,
                'templates' => $this->templates($category),
            ])
            ->values()
            ->toArray();
    }

    /**
     * Transform one category's templates for the frontend.
     *
     * Each carries what it asks for, so the list can summarize a sheet
     * without a second request, and its product count, so the delete dialog
     * can explain itself before a refusal.
     *
     * @return array<array{id: int, name: string, products_count: int, requirements: array<int, string>}>
     */
    protected function templates(ProductCategory $category): array
    {
        return $category->templates
            ->map(fn (ProductTemplate $template) => [
                'id' => $template->id,
                'name' => $template->name,
                'products_count' => (int) $template->products_count,
                'requirements' => $template->requirements()
                    ->map(fn (ProductRequirement $requirement) => $requirement->value)
                    ->all(),
            ])
            ->values()
            ->toArray();
    }
}
