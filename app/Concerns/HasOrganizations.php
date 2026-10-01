<?php

namespace App\Concerns;

use App\Data\BrandPermissions;
use App\Data\OrganizationPermissions;
use App\Data\ProductCategoryPermissions;
use App\Data\ProductPermissions;
use App\Data\SupplierConnectionPermissions;
use App\Data\UserOrganization;
use App\Enums\OrganizationPermission;
use App\Enums\OrganizationRole;
use App\Models\Membership;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;

trait HasOrganizations
{
    /**
     * Get all of the organizations the user belongs to.
     *
     * @return BelongsToMany<Organization, $this>
     */
    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class, 'organization_members', 'user_id', 'organization_id')
            ->withPivot(['role'])
            ->withTimestamps();
    }

    /**
     * Get all of the organizations the user owns.
     *
     * @return HasManyThrough<Organization, Membership, $this>
     */
    public function ownedOrganizations(): HasManyThrough
    {
        return $this->hasManyThrough(
            Organization::class,
            Membership::class,
            'user_id',
            'id',
            'id',
            'organization_id',
        )->where('organization_members.role', OrganizationRole::Owner->value);
    }

    /**
     * Get all of the memberships for the user.
     *
     * @return HasMany<Membership, $this>
     */
    public function organizationMemberships(): HasMany
    {
        return $this->hasMany(Membership::class, 'user_id');
    }

    /**
     * Get the user's current organization.
     *
     * @return BelongsTo<Organization, $this>
     */
    public function currentOrganization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'current_organization_id');
    }

    /**
     * Switch to the given organization.
     */
    public function switchOrganization(Organization $organization): bool
    {
        if (! $this->belongsToOrganization($organization)) {
            return false;
        }

        $this->update(['current_organization_id' => $organization->id]);
        $this->setRelation('currentOrganization', $organization);

        URL::defaults(['current_organization' => $organization->slug]);

        return true;
    }

    /**
     * Switch to the first remaining organization, or clear the current
     * organization when the user no longer belongs to any.
     */
    public function switchToFallbackOrganization(?Organization $excluding = null): void
    {
        if ($organization = $this->fallbackOrganization($excluding)) {
            $this->switchOrganization($organization);

            return;
        }

        $this->update(['current_organization_id' => null]);
        $this->setRelation('currentOrganization', null);
    }

    /**
     * Determine if the user belongs to the given organization.
     */
    public function belongsToOrganization(Organization $organization): bool
    {
        return $this->organizations()->where('organizations.id', $organization->id)->exists();
    }

    /**
     * Determine if the given organization is the user's current organization.
     */
    public function isCurrentOrganization(Organization $organization): bool
    {
        return $this->current_organization_id === $organization->id;
    }

    /**
     * Determine if the user is the owner of the given organization.
     */
    public function ownsOrganization(Organization $organization): bool
    {
        return $this->organizationRole($organization) === OrganizationRole::Owner;
    }

    /**
     * Get the user's role on the given organization.
     */
    public function organizationRole(Organization $organization): ?OrganizationRole
    {
        return $this->organizationMemberships()
            ->where('organization_id', $organization->id)
            ->first()
            ?->role;
    }

    /**
     * Get the user's organizations as a collection of UserOrganization objects.
     *
     * @return Collection<int, UserOrganization>
     */
    public function toUserOrganizations(bool $includeCurrent = false): Collection
    {
        return $this->organizations()
            ->get()
            ->map(fn (Organization $organization) => ! $includeCurrent && $this->isCurrentOrganization($organization) ? null : $this->toUserOrganization($organization))
            ->filter()
            ->values();
    }

    /**
     * Get the user's organization as a UserOrganization object.
     */
    public function toUserOrganization(Organization $organization): UserOrganization
    {
        $role = $this->organizationRole($organization);

        return new UserOrganization(
            id: $organization->id,
            name: $organization->name,
            slug: $organization->slug,
            type: $organization->type->value,
            typeLabel: $organization->type->label(),
            role: $role?->value,
            roleLabel: $role?->label(),
            isCurrent: $this->isCurrentOrganization($organization),
        );
    }

    /**
     * Get the standard permissions for an organization as a OrganizationPermissions object.
     */
    public function toOrganizationPermissions(Organization $organization): OrganizationPermissions
    {
        $role = $this->organizationRole($organization);

        return new OrganizationPermissions(
            canUpdateOrganization: $role?->hasPermission(OrganizationPermission::UpdateOrganization) ?? false,
            canDeleteOrganization: $role?->hasPermission(OrganizationPermission::DeleteOrganization) ?? false,
            canManageAiProvider: $role?->hasPermission(OrganizationPermission::ManageAiProvider) ?? false,
            canAddMember: $role?->hasPermission(OrganizationPermission::AddMember) ?? false,
            canUpdateMember: $role?->hasPermission(OrganizationPermission::UpdateMember) ?? false,
            canRemoveMember: $role?->hasPermission(OrganizationPermission::RemoveMember) ?? false,
            canCreateInvitation: $role?->hasPermission(OrganizationPermission::CreateInvitation) ?? false,
            canCancelInvitation: $role?->hasPermission(OrganizationPermission::CancelInvitation) ?? false,
        );
    }

    /**
     * Get the product permissions for an organization as a ProductPermissions object.
     */
    public function toProductPermissions(Organization $organization): ProductPermissions
    {
        $role = $this->organizationRole($organization);

        /**
         * Products belong to the distributor that places them on the market.
         * A supplier fills in the details of products assigned to them, but
         * never creates or deletes one, so the type gates those two the same
         * way ProductPolicy does.
         */
        $ownsProducts = $organization->isDistributor();

        return new ProductPermissions(
            canCreateProduct: $ownsProducts && ($role?->hasPermission(OrganizationPermission::CreateProduct) ?? false),
            canUpdateProduct: $role?->hasPermission(OrganizationPermission::UpdateProduct) ?? false,
            canDeleteProduct: $ownsProducts && ($role?->hasPermission(OrganizationPermission::DeleteProduct) ?? false),
            /**
             * A review is the distributor reading what their supplier
             * answered, so only the side that owns the product can sign one
             * off or send it back.
             */
            canReviewProduct: $ownsProducts && ($role?->hasPermission(OrganizationPermission::ReviewProduct) ?? false),
            /**
             * The seal is on the distributor's own product and speaks in
             * their name, so only they may set one by hand.
             */
            canOverrideSeal: $ownsProducts && ($role?->hasPermission(OrganizationPermission::OverrideProductSeal) ?? false),
            /**
             * The code encodes nothing secret, but the label goes on the
             * packet the distributor places on the market, so the artwork
             * is theirs.
             */
            canDownloadLabel: $ownsProducts && ($role?->hasPermission(OrganizationPermission::ViewProduct) ?? false),
            /**
             * A run of serials is vouched for in the distributor's name, so
             * it is for those who may edit the product.
             */
            canManageSerialLabels: $ownsProducts && ($role?->hasPermission(OrganizationPermission::UpdateProduct) ?? false),
            /**
             * The public page speaks in the distributor's name, so what goes
             * on it is theirs to decide.
             */
            canPublishDocuments: $ownsProducts && ($role?->hasPermission(OrganizationPermission::UpdateProduct) ?? false),
        );
    }

    /**
     * Get the supplier connection permissions for an organization.
     */
    public function toSupplierConnectionPermissions(Organization $organization): SupplierConnectionPermissions
    {
        $role = $this->organizationRole($organization);

        return new SupplierConnectionPermissions(
            canViewConnection: $role?->hasPermission(OrganizationPermission::ViewConnection) ?? false,
            canManageConnection: $role?->hasPermission(OrganizationPermission::ManageConnection) ?? false,
        );
    }

    /**
     * Get the organization to fall back on, ignoring the one being left.
     */
    public function fallbackOrganization(?Organization $excluding = null): ?Organization
    {
        return $this->organizations()
            ->when($excluding, fn ($query) => $query->where('organizations.id', '!=', $excluding->id))
            ->orderByRaw('LOWER(organizations.name)')
            ->first();
    }

    /**
     * Get what the user may do with the organization's category list.
     *
     * Only a distributor keeps a list, which gates the whole screen the same
     * way ProductCategoryPolicy does.
     */
    public function toProductCategoryPermissions(Organization $organization): ProductCategoryPermissions
    {
        $role = $this->organizationRole($organization);
        $keepsCategories = $organization->isDistributor();

        return new ProductCategoryPermissions(
            canCreateCategory: $keepsCategories && ($role?->hasPermission(OrganizationPermission::CreateProductCategory) ?? false),
            canUpdateCategory: $keepsCategories && ($role?->hasPermission(OrganizationPermission::UpdateProductCategory) ?? false),
            canDeleteCategory: $keepsCategories && ($role?->hasPermission(OrganizationPermission::DeleteProductCategory) ?? false),
        );
    }

    /**
     * Get what the user may do with the organization's brand list.
     *
     * Only a distributor keeps a list, which gates the whole screen the same
     * way BrandPolicy does.
     */
    public function toBrandPermissions(Organization $organization): BrandPermissions
    {
        $role = $this->organizationRole($organization);

        /**
         * Both sides of a trade name makers under it, so unlike the legal
         * families this is not gated on the organization being a
         * distributor. Which particular trades can take a new brand is a
         * per-connection question the brands page answers row by row.
         */
        return new BrandPermissions(
            canCreateBrand: $role?->hasPermission(OrganizationPermission::CreateBrand) ?? false,
            canUpdateBrand: $role?->hasPermission(OrganizationPermission::UpdateBrand) ?? false,
            canDeleteBrand: $role?->hasPermission(OrganizationPermission::DeleteBrand) ?? false,
        );
    }

    /**
     * Determine if the user has the given permission on the organization.
     */
    public function hasOrganizationPermission(Organization $organization, OrganizationPermission $permission): bool
    {
        return $this->organizationRole($organization)?->hasPermission($permission) ?? false;
    }
}
