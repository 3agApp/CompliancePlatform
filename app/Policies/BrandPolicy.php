<?php

namespace App\Policies;

use App\Enums\OrganizationPermission;
use App\Models\Brand;
use App\Models\Organization;
use App\Models\User;

/**
 * A brand list belongs to the distributor that keeps it. A supplier picks
 * from the owning distributor's list when filling in a product assigned to
 * them, but holds none of its own, so every check here starts from the
 * organization that owns the row.
 */
class BrandPolicy
{
    /**
     * Determine whether the user can view the organization's brands.
     */
    public function viewAny(User $user, Organization $organization): bool
    {
        return $organization->isDistributor()
            && $user->hasOrganizationPermission($organization, OrganizationPermission::ViewBrand);
    }

    /**
     * Determine whether the user can create brands for the organization.
     */
    public function create(User $user, Organization $organization): bool
    {
        return $organization->isDistributor()
            && $user->hasOrganizationPermission($organization, OrganizationPermission::CreateBrand);
    }

    /**
     * Determine whether the user can update the brand.
     */
    public function update(User $user, Brand $brand): bool
    {
        return $user->hasOrganizationPermission($brand->organization, OrganizationPermission::UpdateBrand);
    }

    /**
     * Determine whether the user can delete the brand.
     */
    public function delete(User $user, Brand $brand): bool
    {
        return $user->hasOrganizationPermission($brand->organization, OrganizationPermission::DeleteBrand);
    }
}
