<?php

namespace App\Http\Controllers\Organizations;

use App\Http\Controllers\Controller;
use App\Http\Requests\Organizations\SaveOrganizationProfileRequest;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class OrganizationProfileController extends Controller
{
    /**
     * Update the organization's company profile.
     */
    public function update(SaveOrganizationProfileRequest $request, Organization $organization): RedirectResponse
    {
        Gate::authorize('update', $organization);

        $organization->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Company profile updated.')]);

        return to_route('organizations.edit', ['organization' => $organization->slug]);
    }
}
