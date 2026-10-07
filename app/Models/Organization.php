<?php

namespace App\Models;

use App\Concerns\GeneratesUniqueOrganizationSlugs;
use App\Enums\Locale;
use App\Enums\OrganizationRole;
use App\Enums\OrganizationType;
use App\Enums\SupplierConnectionStatus;
use Carbon\CarbonImmutable;
use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property OrganizationType $type
 * @property Locale $locale
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Collection<int, OrganizationInvitation> $invitations
 * @property-read Collection<int, Membership> $memberships
 * @property-read Collection<int, Product> $products
 * @property-read Collection<int, ProductCategory> $productCategories
 * @property-read Collection<int, Brand> $brands
 * @property-read Collection<int, Brand> $suppliedBrands
 * @property-read Collection<int, User> $members
 * @property-read Collection<int, SupplierConnection> $supplierConnections
 * @property-read Collection<int, SupplierConnection> $distributorConnections
 * @property-read Collection<int, Product> $suppliedProducts
 */
#[Fillable(['name', 'slug', 'type', 'locale'])]
class Organization extends Model
{
    /** @use HasFactory<OrganizationFactory> */
    use GeneratesUniqueOrganizationSlugs, HasFactory;

    /**
     * Bootstrap the model and its traits.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Organization $organization) {
            if (empty($organization->slug)) {
                $organization->slug = static::generateUniqueOrganizationSlug($organization->name);
            }
        });

        static::updating(function (Organization $organization) {
            if ($organization->isDirty('name')) {
                $organization->slug = static::generateUniqueOrganizationSlug($organization->name, $organization->id);
            }
        });

        static::created(function (Organization $organization) {
            $organization->createDefaultProductCategories();
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => OrganizationType::class,
            'locale' => Locale::class,
        ];
    }

    /**
     * Determine if the organization places products on the market.
     */
    public function isDistributor(): bool
    {
        return $this->type === OrganizationType::Distributor;
    }

    /**
     * Determine if the organization supplies products to distributors.
     */
    public function isSupplier(): bool
    {
        return $this->type === OrganizationType::Supplier;
    }

    /**
     * Get the organization owner.
     */
    public function owner(): ?Model
    {
        return $this->members()
            ->wherePivot('role', OrganizationRole::Owner->value)
            ->first();
    }

    /**
     * The membership of the organization's owner.
     *
     * owner() answers with the user and runs a query each time. This is the
     * relation, so a listing can eager load the owner and sort or search on
     * their address without a query per row.
     *
     * @return HasOne<Membership, $this>
     */
    public function ownerMembership(): HasOne
    {
        return $this->hasOne(Membership::class)->where('role', OrganizationRole::Owner->value);
    }

    /**
     * Get all members of this organization.
     *
     * @return BelongsToMany<User, $this, Membership, 'pivot'>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'organization_members', 'organization_id', 'user_id')
            ->using(Membership::class)
            ->withPivot(['role'])
            ->withTimestamps();
    }

    /**
     * Get all memberships for this organization.
     *
     * @return HasMany<Membership, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    /**
     * Get the AI provider this organization has connected.
     *
     * Absent for most organizations: the feature it powers degrades to
     * filling the fields in by hand, so having no provider is an ordinary
     * state rather than a broken one.
     *
     * @return HasOne<OrganizationAiSetting, $this>
     */
    public function aiSetting(): HasOne
    {
        return $this->hasOne(OrganizationAiSetting::class);
    }

    /**
     * Get all products for this organization.
     *
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Get the organization's own list of legal families.
     *
     * @return HasMany<ProductCategory, $this>
     */
    public function productCategories(): HasMany
    {
        return $this->hasMany(ProductCategory::class);
    }

    /**
     * Get every homework sheet the organization keeps, across all of its
     * legal families.
     *
     * A template belongs to a category rather than to an organization, so
     * reaching them all means going through the categories. The product
     * form wants the whole set in one go: it narrows the list to the chosen
     * family on the client rather than asking the server again.
     *
     * @return HasManyThrough<ProductTemplate, ProductCategory, $this>
     */
    public function productTemplates(): HasManyThrough
    {
        return $this->hasManyThrough(ProductTemplate::class, ProductCategory::class);
    }

    /**
     * Get the makers reachable from this organization as a distributor.
     *
     * A brand is named under one supplier connection, so a distributor's
     * list is everything named under the trades it holds -- including a
     * connection it has revoked, whose products stay in the catalog and
     * have to keep reading as something.
     *
     * There is no starting list to match the legal families: a family
     * comes from regulation, while the makers behind a trade are its own.
     *
     * @return HasManyThrough<Brand, SupplierConnection, $this>
     */
    public function brands(): HasManyThrough
    {
        return $this->hasManyThrough(
            Brand::class,
            SupplierConnection::class,
            'distributor_organization_id',
            'supplier_connection_id',
            'id',
            'id',
        );
    }

    /**
     * Get the makers this organization names as a supplier.
     *
     * Narrowed to live connections the way suppliedProducts is: a supplier
     * whose connection has been revoked has lost the products, and has no
     * business editing the brands filed against them either.
     *
     * @return HasManyThrough<Brand, SupplierConnection, $this>
     */
    public function suppliedBrands(): HasManyThrough
    {
        return $this->hasManyThrough(
            Brand::class,
            SupplierConnection::class,
            'supplier_organization_id',
            'supplier_connection_id',
            'id',
            'id',
        )->where('supplier_connections.status', SupplierConnectionStatus::Active->value);
    }

    /**
     * Give the organization the legal families it starts out with.
     *
     * Organizations are created from onboarding, from the organization
     * settings and from claiming a supplier invitation, so this hangs off the
     * created event rather than off any one of those paths. Only a
     * distributor files products under a category, and an organization cannot
     * change type once created, so a supplier is left without a list rather
     * than with one nothing will ever read.
     */
    public function createDefaultProductCategories(): void
    {
        if (! $this->isDistributor()) {
            return;
        }

        foreach (ProductCategory::DEFAULT_NAMES as $name) {
            $this->productCategories()->create(['name' => $name]);
        }
    }

    /**
     * Get all invitations for this organization.
     *
     * @return HasMany<OrganizationInvitation, $this>
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(OrganizationInvitation::class);
    }

    /**
     * Get the connections where this organization is the distributor.
     *
     * @return HasMany<SupplierConnection, $this>
     */
    public function supplierConnections(): HasMany
    {
        return $this->hasMany(SupplierConnection::class, 'distributor_organization_id');
    }

    /**
     * Get the connections where this organization is the supplier.
     *
     * @return HasMany<SupplierConnection, $this>
     */
    public function distributorConnections(): HasMany
    {
        return $this->hasMany(SupplierConnection::class, 'supplier_organization_id');
    }

    /**
     * Get the products this organization supplies through active connections.
     *
     * The status filter belongs to the relationship rather than to any caller,
     * so revoking a connection takes effect at once for the product list, the
     * scoped route binding and the product policy alike.
     *
     * @return HasManyThrough<Product, SupplierConnection, $this>
     */
    public function suppliedProducts(): HasManyThrough
    {
        return $this->hasManyThrough(
            Product::class,
            SupplierConnection::class,
            'supplier_organization_id',
            'supplier_connection_id',
            'id',
            'id',
        )->where('supplier_connections.status', SupplierConnectionStatus::Active->value);
    }

    /**
     * Get the relationship used to resolve scoped child route bindings.
     *
     * Products and brands reach a supplier organization through its active
     * supplier connections instead of through ownership, so the two sides
     * of the platform resolve "/{current_organization}/products/{product}"
     * through different relationships. A miss still raises a
     * model-not-found, so an organization asking for a row it cannot see
     * gets a 404.
     *
     * @param  string  $childType
     */
    protected function childRouteBindingRelationshipName($childType): string
    {
        if ($childType === 'product' && $this->isSupplier()) {
            return 'suppliedProducts';
        }

        if ($childType === 'brand' && $this->isSupplier()) {
            return 'suppliedBrands';
        }

        return parent::childRouteBindingRelationshipName($childType);
    }

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
