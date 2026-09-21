<?php

namespace App\Policies;

use App\Enums\OrganizationPermission;
use App\Models\Brand;
use App\Models\Organization;
use App\Models\SupplierConnection;
use App\Models\User;

/**
 * A brand is named under one supplier connection, so both sides of that
 * trade reach it: the supplier because the maker is theirs to name, and the
 * distributor because it is their catalog the answer lands in.
 *
 * Every check therefore starts from the connection rather than from a
 * single owning organization. A supplier reaches one only while the
 * connection is live -- a revoked supplier has already lost the products
 * these brands sit on.
 */
class BrandPolicy
{
    /**
     * Determine whether the user can view the organization's brands.
     */
    public function viewAny(User $user, Organization $organization): bool
    {
        return $user->hasOrganizationPermission($organization, OrganizationPermission::ViewBrand);
    }

    /**
     * Determine whether the user can name a maker under the connection.
     */
    public function create(User $user, SupplierConnection $connection): bool
    {
        return $this->reaches($user, $connection, OrganizationPermission::CreateBrand);
    }

    /**
     * Determine whether the user can update the brand.
     */
    public function update(User $user, Brand $brand): bool
    {
        return $this->reaches($user, $brand->supplierConnection, OrganizationPermission::UpdateBrand);
    }

    /**
     * Determine whether the user can delete the brand.
     */
    public function delete(User $user, Brand $brand): bool
    {
        return $this->reaches($user, $brand->supplierConnection, OrganizationPermission::DeleteBrand);
    }

    /**
     * Determine whether the user holds the permission on either side of the
     * trade the brand is named under.
     *
     * The distributor reaches every connection they hold, revoked ones
     * included: the products are still in their catalog and still carry
     * these brands. The supplier reaches only a live one, which is the same
     * line their products are drawn on.
     */
    protected function reaches(User $user, SupplierConnection $connection, OrganizationPermission $permission): bool
    {
        if ($user->hasOrganizationPermission($connection->distributorOrganization, $permission)) {
            return true;
        }

        $supplier = $connection->supplierOrganization;

        return $connection->isActive()
            && $supplier instanceof Organization
            && $user->hasOrganizationPermission($supplier, $permission);
    }
}
