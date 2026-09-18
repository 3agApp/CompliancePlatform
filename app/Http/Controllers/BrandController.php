<?php

namespace App\Http\Controllers;

use App\Http\Requests\Brands\SaveBrandRequest;
use App\Models\Brand;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The distributor's own list of brands. The brand used to be typed onto each
 * product, which spelled the same maker three ways across a catalog and left
 * nothing that could be counted or filtered by.
 */
class BrandController extends Controller
{
    /**
     * Display the organization's brands.
     */
    public function index(Request $request, Organization $currentOrganization): Response
    {
        Gate::authorize('viewAny', [Brand::class, $currentOrganization]);

        return Inertia::render('brands/index', [
            'brands' => $this->brands($currentOrganization),
            'permissions' => $request->user()->toBrandPermissions($currentOrganization),
        ]);
    }

    /**
     * Store a newly created brand.
     */
    public function store(SaveBrandRequest $request, Organization $currentOrganization): RedirectResponse
    {
        Gate::authorize('create', [Brand::class, $currentOrganization]);

        $currentOrganization->brands()->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Brand created.')]);

        return to_route('brands.index', ['current_organization' => $currentOrganization->slug]);
    }

    /**
     * Update the specified brand.
     */
    public function update(SaveBrandRequest $request, Organization $currentOrganization, Brand $brand): RedirectResponse
    {
        Gate::authorize('update', $brand);

        $brand->update($request->validated());

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
     * Transform the organization's brands for the frontend.
     *
     * The product count travels with each row so the list can say what a
     * brand is holding, and the delete dialog can explain itself before the
     * request that would be refused is ever sent.
     *
     * @return array<array{id: int, name: string, products_count: int}>
     */
    protected function brands(Organization $organization): array
    {
        return $organization->brands()
            ->withCount('products')
            ->orderBy('name')
            ->get()
            ->map(fn (Brand $brand) => [
                'id' => $brand->id,
                'name' => $brand->name,
                'products_count' => (int) $brand->products_count,
            ])
            ->values()
            ->toArray();
    }
}
