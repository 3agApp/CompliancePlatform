<?php

namespace App\Models;

use App\Data\ProductCompleteness;
use App\Data\ProductSeal;
use App\Enums\CountryOfOrigin;
use App\Enums\ProductEventType;
use App\Enums\ProductReviewStatus;
use App\Enums\ProductSealStatus;
use Carbon\CarbonImmutable;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property int $organization_id
 * @property int $supplier_connection_id
 * @property string $name
 * @property int|null $brand_id
 * @property int $product_category_id
 * @property int $product_template_id
 * @property string|null $ean
 * @property string|null $internal_article_number
 * @property string|null $supplier_article_number
 * @property string|null $order_number
 * @property string|null $customs_tariff_number
 * @property CountryOfOrigin|null $country_of_origin
 * @property string|null $age_grading
 * @property string|null $safety_notice
 * @property string|null $warning_text
 * @property string|null $material_information
 * @property string|null $usage_restrictions
 * @property string|null $safety_instructions
 * @property string|null $additional_notes
 * @property ProductReviewStatus $review_status
 * @property CarbonImmutable|null $submitted_at
 * @property CarbonImmutable|null $reviewed_at
 * @property ProductSealStatus|null $seal_override
 * @property string|null $seal_override_reason
 * @property int|null $seal_overridden_by
 * @property CarbonImmutable|null $seal_overridden_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Organization $organization
 * @property-read Brand|null $brand
 * @property-read ProductCategory $category
 * @property-read ProductTemplate $template
 * @property-read SupplierConnection|null $supplierConnection
 * @property-read Collection<int, ProductDocument> $documents
 * @property-read Collection<int, ProductEvent> $events
 * @property-read User|null $sealOverriddenBy
 */
#[Fillable([
    'name',
    'brand_id',
    'product_category_id',
    'product_template_id',
    'ean',
    'internal_article_number',
    'supplier_article_number',
    'order_number',
    'customs_tariff_number',
    'country_of_origin',
    'supplier_connection_id',
    'age_grading',
    'safety_notice',
    'warning_text',
    'material_information',
    'usage_restrictions',
    'safety_instructions',
    'additional_notes',
])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    /**
     * Bootstrap the model and its traits.
     *
     * The public name is given here rather than by the database, because
     * every way a product is created -- the form, a factory, a seeder --
     * has to end up with one: a product without a uuid has no public page.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Product $product) {
            if (empty($product->uuid)) {
                $product->uuid = (string) Str::uuid();
            }
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
            'country_of_origin' => CountryOfOrigin::class,
            'review_status' => ProductReviewStatus::class,
            'seal_override' => ProductSealStatus::class,
            'seal_overridden_at' => 'datetime',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * Get the organization the product belongs to.
     *
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Get the brand the product carries.
     *
     * @return BelongsTo<Brand, $this>
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /**
     * Get the legal family the product is filed under.
     *
     * @return BelongsTo<ProductCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id');
    }

    /**
     * Get the homework sheet the product is held to.
     *
     * Always one of the templates under the product's own category: the
     * pair is checked together when the product is saved, because no
     * foreign key can say that the two point at the same family.
     *
     * @return BelongsTo<ProductTemplate, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(ProductTemplate::class, 'product_template_id');
    }

    /**
     * Read the product against its template.
     *
     * Expects the template and the documents to be loaded; every caller
     * reads a list of products and would otherwise pay two queries a row.
     */
    public function completeness(): ProductCompleteness
    {
        return ProductCompleteness::for($this);
    }

    /**
     * Get the connection this product is assigned to.
     *
     * A product is assigned to the connection rather than directly to the
     * supplier organization, so it can be assigned before the supplier has
     * claimed their invitation.
     *
     * @return BelongsTo<SupplierConnection, $this>
     */
    public function supplierConnection(): BelongsTo
    {
        return $this->belongsTo(SupplierConnection::class);
    }

    /**
     * Get the papers filed against the product.
     *
     * Ordered oldest first, so a kind that gathers several -- a test report
     * per component -- reads in the order the evidence arrived.
     *
     * @return HasMany<ProductDocument, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(ProductDocument::class)->oldest();
    }

    /**
     * Get everything that has ever happened to the product, newest first.
     *
     * The id breaks the tie rather than the timestamp alone: a request that
     * writes two events -- an edit that withdraws a product from review
     * writes the edit and the withdrawal -- writes them in the same second,
     * and they only read correctly in the order they happened.
     *
     * @return HasMany<ProductEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(ProductEvent::class)->latest('id');
    }

    /**
     * Record one thing that happened to the product.
     *
     * The actor is kept twice over -- as the account and organization that
     * did it, and as the names they held at the time -- so closing an
     * account never blanks out what it did.
     *
     * @param  array<string, array{from: string|null, to: string|null}>  $changes
     */
    public function recordEvent(ProductEventType $type, ?User $actor = null, ?Organization $organization = null, ?string $note = null, array $changes = []): ProductEvent
    {
        return $this->events()->create([
            'user_id' => $actor?->id,
            'organization_id' => $organization?->id,
            'actor_name' => $actor?->name,
            'actor_organization_name' => $organization?->name,
            'type' => $type,
            'note' => $note,
            'changes' => $changes === [] ? null : $changes,
        ]);
    }

    /**
     * Get whoever last set the public seal by hand, while their account
     * still exists.
     *
     * @return BelongsTo<User, $this>
     */
    public function sealOverriddenBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seal_overridden_by');
    }

    /**
     * Read the seal the product shows in public.
     *
     * Expects the template and documents to be loaded, like the
     * completeness score it is partly read from.
     */
    public function seal(): ProductSeal
    {
        return ProductSeal::for($this);
    }

    /**
     * Get the address the product answers to in public.
     */
    public function publicUrl(): string
    {
        return route('products.public', ['product' => $this->uuid]);
    }

    /**
     * Get the note the product was last sent back with, while it is still
     * waiting on those changes.
     *
     * Read separately from the history, which is deferred: the note is the
     * one thing on a product sent back that has to be in front of the
     * supplier the moment the page opens, because it is the instruction for
     * everything else they are about to do.
     */
    public function latestReviewNote(): ?string
    {
        if ($this->review_status !== ProductReviewStatus::ChangesRequested) {
            return null;
        }

        return $this->events()
            ->where('type', ProductEventType::ChangesRequested)
            ->value('note');
    }

    /**
     * Scope the query to the products in one state of review.
     *
     * Applied on top of a query that is already scoped to the viewer, like
     * every other filter here.
     *
     * @param  Builder<Product>  $query
     */
    public function scopeInReviewStatus(Builder $query, ProductReviewStatus $status): void
    {
        $query->where('products.review_status', $status);
    }

    /**
     * Scope the query to the products assigned to one connection.
     *
     * Written as an EXISTS clause so it composes onto a query that is already
     * scoped to the viewing organization. A connection id belonging to
     * someone else therefore matches no candidate row rather than needing a
     * second authorization check, and the viewer learns nothing about it.
     *
     * @param  Builder<Product>  $query
     */
    public function scopeAssignedTo(Builder $query, int $connectionId): void
    {
        $query->whereRelation('supplierConnection', 'supplier_connections.id', $connectionId);
    }

    /**
     * Scope the query to the products filed under one category.
     *
     * Applied on top of a query that is already scoped to the viewer, so a
     * category id from another organization's list matches nothing rather
     * than needing a check of its own.
     *
     * @param  Builder<Product>  $query
     */
    public function scopeInCategory(Builder $query, int $categoryId): void
    {
        $query->where('products.product_category_id', $categoryId);
    }

    /**
     * Scope the query to the products carrying one brand.
     *
     * Applied on top of a query that is already scoped to the viewer, so a
     * brand id from another organization's list matches nothing rather than
     * needing a check of its own.
     *
     * @param  Builder<Product>  $query
     */
    public function scopeOfBrand(Builder $query, int $brandId): void
    {
        $query->where('products.brand_id', $brandId);
    }

    /**
     * Scope the query to products whose name, barcode or either side's
     * article number contains the term.
     *
     * Both article numbers are searched because the two sides of a trade
     * know the same product by different ones, and each side pastes the
     * number it holds into the same box.
     *
     * The OR must stay grouped. Ungrouped, it binds looser than the caller's
     * organization clause and returns every product on the platform whose
     * barcode matches. A scope is grouped for free -- callScope wraps
     * whatever wheres it adds -- so the explicit group is belt and braces,
     * kept because the danger is invisible without it and because the clause
     * may one day be read outside a scope.
     *
     * @param  Builder<Product>  $query
     */
    public function scopeMatching(Builder $query, string $term): void
    {
        $query->where(fn (Builder $query) => $query
            ->whereLike('products.name', "%{$term}%")
            ->orWhereLike('products.ean', "%{$term}%")
            ->orWhereLike('products.internal_article_number', "%{$term}%")
            ->orWhereLike('products.supplier_article_number', "%{$term}%"));
    }
}
