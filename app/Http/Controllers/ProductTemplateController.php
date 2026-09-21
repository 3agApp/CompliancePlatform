<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductTemplates\SaveProductTemplateRequest;
use App\Models\Organization;
use App\Models\ProductCategory;
use App\Models\ProductTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * The homework sheets under one legal family: which papers and which fields
 * a product of that kind is expected to carry.
 *
 * There is no screen of their own. A template is meaningless apart from the
 * family it sits under, so they are managed from the categories page and
 * every action here authorizes against the parent category -- configuring a
 * family's sheets is editing that family, and it needs no permission of its
 * own.
 */
class ProductTemplateController extends Controller
{
    /**
     * Store a newly created template under the category.
     */
    public function store(SaveProductTemplateRequest $request, Organization $currentOrganization, ProductCategory $productCategory): RedirectResponse
    {
        Gate::authorize('update', $productCategory);

        $productCategory->templates()->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Template created.')]);

        return $this->backToCategories($currentOrganization);
    }

    /**
     * Update the specified template.
     */
    public function update(SaveProductTemplateRequest $request, Organization $currentOrganization, ProductCategory $productCategory, ProductTemplate $template): RedirectResponse
    {
        Gate::authorize('update', $productCategory);

        $template->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Template updated.')]);

        return $this->backToCategories($currentOrganization);
    }

    /**
     * Remove the specified template.
     *
     * A template still on a product is kept. The foreign key would refuse it
     * anyway, but a constraint violation is a five hundred; refusing here is
     * what turns it into a sentence that says which products are in the way
     * and how many.
     */
    public function destroy(Organization $currentOrganization, ProductCategory $productCategory, ProductTemplate $template): RedirectResponse
    {
        Gate::authorize('update', $productCategory);

        $productCount = $template->products()->count();

        if ($productCount > 0) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => trans_choice(
                    '{1}:name is still used by one product. Move it to another template first.|[2,*]:name is still used by :count products. Move them to another template first.',
                    $productCount,
                    ['name' => $template->name, 'count' => $productCount],
                ),
            ]);

            return $this->backToCategories($currentOrganization);
        }

        $template->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Template deleted.')]);

        return $this->backToCategories($currentOrganization);
    }

    /**
     * Send the caller back to the screen the templates are managed from.
     */
    protected function backToCategories(Organization $organization): RedirectResponse
    {
        return to_route('categories.index', ['current_organization' => $organization->slug]);
    }
}
