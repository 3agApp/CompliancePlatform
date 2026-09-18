<?php

namespace App\Models;

use App\Enums\CountryOfOrigin;
use Carbon\CarbonImmutable;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $uuid
 * @property int $organization_id
 * @property int|null $supplier_connection_id
 * @property string $name
 * @property string|null $ean
 * @property CountryOfOrigin|null $country_of_origin
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Organization $organization
 * @property-read SupplierConnection|null $supplierConnection
 */
#[Fillable(['name', 'ean', 'country_of_origin', 'supplier_connection_id'])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory, HasUuids;

    /**
     * Get the columns that should receive a unique identifier.
     *
     * The auto-incrementing key is kept as the internal identifier, while the
     * UUID is the public one used in URLs.
     *
     * @return array<int, string>
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
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
     * The connection is matched by its public uuid so the filter can live in
     * a URL, and as an EXISTS clause so it composes onto a query that is
     * already scoped to the viewing organization. A uuid belonging to someone
     * else therefore matches no candidate row rather than needing a second
     * authorization check, and the viewer learns nothing about it.
     *
     * @param  Builder<Product>  $query
     */
    public function scopeAssignedTo(Builder $query, string $connectionUuid): void
    {
        $query->whereRelation('supplierConnection', 'supplier_connections.uuid', $connectionUuid);
    }

    /**
     * Scope the query to products whose name or barcode contains the term.
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
            ->orWhereLike('products.ean', "%{$term}%"));
    }

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
