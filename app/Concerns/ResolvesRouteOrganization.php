<?php

namespace App\Concerns;

use App\Models\Organization;
use Illuminate\Http\Request;

/**
 * Resolves the organization a request is scoped to.
 *
 * Shared by the tenancy middlewares so they cannot disagree about which
 * organization the current request belongs to.
 */
trait ResolvesRouteOrganization
{
    /**
     * Get the organization associated with the request.
     */
    protected function routeOrganization(Request $request): ?Organization
    {
        $organization = $request->route('current_organization') ?? $request->route('organization');

        if (is_string($organization)) {
            $organization = Organization::where('slug', $organization)->first();
        }

        return $organization;
    }
}
