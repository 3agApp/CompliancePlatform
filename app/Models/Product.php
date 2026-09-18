<?php

namespace App\Models;

use App\Enums\CountryOfOrigin;
use Carbon\CarbonImmutable;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $organization_id
 * @property int|null $supplier_connection_id
 * @property string $name
 * @property string|null $brand
 * @property int|null $product_category_id
 * @property string|null $ean
 * @property string|null $internal_article_number
 * @property string|null $supplier_article_number
 * @property string|null $order_number
 * @property CountryOfOrigin|null $country_of_origin
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Organization $organization
 * @property-read ProductCategory|null $category
 * @property-read SupplierConnection|null $supplierConnection
 */
#[Fillable([
    'name',
    'brand',
    'product_category_id',
    'ean',
    'internal_article_number',
    'supplier_article_number',
    'order_number',
    'country_of_origin',
    'supplier_connection_id',
])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'country_of_origin' => CountryOfOrigin::class,
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
     * Get the legal family the product is filed under.
     *
     * @return BelongsTo<ProductCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id');
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
