<?php

namespace App\Policies;

use App\Enums\OrganizationPermission;
use App\Models\Organization;
use App\Models\SupplierConnection;
use App\Models\User;

class SupplierConnectionPolicy
{
    /**
     * Determine whether the user can view the organization's connections.
     */
    public function viewAny(User $user, Organization $organization): bool
    {
        return $user->hasOrganizationPermission($organization, OrganizationPermission::ViewConnection);
    }

    /**
     * Determine whether the user can invite a supplier for the organization.
     */
    public function create(User $user, Organization $organization): bool
    {
        return $organization->isDistributor()
            && $user->hasOrganizationPermission($organization, OrganizationPermission::ManageConnection);
    }

    /**
     * Determine whether the user can revoke, restore or resend the connection.
     *
     * Only the distributor side manages the connection. A supplier that wants
     * out asks the distributor to end it.
     */
    public function update(User $user, SupplierConnection $connection): bool
    {
        return $user->hasOrganizationPermission(
            $connection->distributorOrganization,
            OrganizationPermission::ManageConnection,
        );
    }
}
