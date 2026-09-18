<?php

namespace App\Policies;

use App\Enums\OrganizationPermission;
use App\Models\Organization;
use App\Models\ProductCategory;
use App\Models\User;

/**
 * A category list belongs to the distributor that keeps it. A supplier fills
 * in the details of products assigned to them, and picks from the owning
 * distributor's families while doing so, but never holds a list of its own --
 * so every check here starts from the organization that owns the row.
 */
class ProductCategoryPolicy
{
    /**
     * Determine whether the user can view the organization's categories.
     */
    public function viewAny(User $user, Organization $organization): bool
    {
        return $organization->isDistributor()
            && $user->hasOrganizationPermission($organization, OrganizationPermission::ViewProductCategory);
    }

    /**
     * Determine whether the user can create categories for the organization.
     */
    public function create(User $user, Organization $organization): bool
    {
        return $organization->isDistributor()
            && $user->hasOrganizationPermission($organization, OrganizationPermission::CreateProductCategory);
    }

    /**
     * Determine whether the user can update the category.
     */
    public function update(User $user, ProductCategory $category): bool
    {
        return $user->hasOrganizationPermission($category->organization, OrganizationPermission::UpdateProductCategory);
    }

    /**
     * Determine whether the user can delete the category.
     */
    public function delete(User $user, ProductCategory $category): bool
    {
        return $user->hasOrganizationPermission($category->organization, OrganizationPermission::DeleteProductCategory);
    }
}
