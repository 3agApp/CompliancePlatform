<?php

namespace App\Policies;

use App\Enums\OrganizationPermission;
use App\Models\Organization;
use App\Models\Product;
use App\Models\User;

class ProductPolicy
{
    /**
     * Determine whether the user can view any products of the organization.
     */
    public function viewAny(User $user, Organization $organization): bool
    {
        return $user->hasOrganizationPermission($organization, OrganizationPermission::ViewProduct);
    }

    /**
     * Determine whether the user can view the product.
     */
    public function view(User $user, Product $product): bool
    {
        return $this->viewAny($user, $product->organization)
            || $this->reachesProductAsSupplier($user, $product, OrganizationPermission::ViewProduct);
    }

    /**
     * Determine whether the user can create products for the organization.
     *
     * Products belong to the distributor that places them on the market. A
     * supplier owner holds the create permission inside their own
     * organization, so the type check is what keeps them from creating
     * products of their own.
     */
    public function create(User $user, Organization $organization): bool
    {
        return $organization->isDistributor()
            && $user->hasOrganizationPermission($organization, OrganizationPermission::CreateProduct);
    }

    /**
     * Determine whether the user can update the product.
     */
    public function update(User $user, Product $product): bool
    {
        return $user->hasOrganizationPermission($product->organization, OrganizationPermission::UpdateProduct)
            || $this->reachesProductAsSupplier($user, $product, OrganizationPermission::UpdateProduct);
    }

    /**
     * Determine whether the user can offer the product up for review.
     *
     * Whoever may fill the product in may submit it, which is the supplier
     * on most products and the distributor on their own. The status decides
     * whether there is anything to submit; this decides who may.
     */
    public function submit(User $user, Product $product): bool
    {
        return $this->update($user, $product);
    }

    /**
     * Determine whether the user can rule on the product.
     *
     * Only the distributor that owns it. A supplier holding the review
     * permission inside their own organization reaches this product through
     * the connection rather than through membership, so there is no supplier
     * branch here -- which is what stops a supplier signing off their own
     * homework.
     */
    public function review(User $user, Product $product): bool
    {
        return $user->hasOrganizationPermission($product->organization, OrganizationPermission::ReviewProduct);
    }

    /**
     * Determine whether the user can delete the product.
     *
     * Only the distributor that owns the product may delete it. A supplier is
     * not a member of that organization, so no supplier branch is needed.
     */
    public function delete(User $user, Product $product): bool
    {
        return $user->hasOrganizationPermission($product->organization, OrganizationPermission::DeleteProduct);
    }

    /**
     * Determine whether the user reaches the product as its assigned supplier.
     *
     * The supplier's own role inside their organization decides what they may
     * do, so a supplier member can view while a supplier admin can also edit.
     */
    protected function reachesProductAsSupplier(User $user, Product $product, OrganizationPermission $permission): bool
    {
        $connection = $product->supplierConnection;

        if ($connection === null || ! $connection->isActive()) {
            return false;
        }

        $supplier = $connection->supplierOrganization;

        return $supplier !== null && $user->hasOrganizationPermission($supplier, $permission);
    }
}
