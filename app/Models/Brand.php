<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\BrandFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A maker whose products an organization carries, such as tigerbox or
 * Magna-Tiles.
 *
 * The list belongs to one organization. Two distributors carrying the same
 * maker keep a row each: neither can rename or delete the other's, and what
 * one of them calls it says nothing about the other's catalog.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $name
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Organization $organization
 * @property-read Collection<int, Product> $products
 * @property-read int|null $products_count
 */
#[Fillable(['name'])]
class Brand extends Model
{
    /** @use HasFactory<BrandFactory> */
    use HasFactory;

    /**
     * Get the organization whose list this brand belongs to.
     *
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Get the products carrying this brand.
     *
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Get the brand as an option for a product form.
     *
     * @return array{id: int, label: string}
     */
    public function toOption(): array
    {
        return ['id' => $this->id, 'label' => $this->name];
    }
}
