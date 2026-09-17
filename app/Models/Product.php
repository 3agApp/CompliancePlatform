<?php

namespace App\Models;

use App\Enums\CountryOfOrigin;
use Carbon\CarbonImmutable;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
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
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
