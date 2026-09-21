<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\ProductCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The legal family a product belongs to, such as a toy, a magnetic toy or a
 * filter. Which rules a product has to answer for follows from its family.
 *
 * The list belongs to one organization. The families themselves are defined
 * by regulation and so repeat from one distributor to the next, but each
 * keeps its own rows, curates its own wording, and cannot disturb anybody
 * else's catalog by renaming or deleting one.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $name
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Organization $organization
 * @property-read Collection<int, Product> $products
 * @property-read Collection<int, ProductTemplate> $templates
 * @property-read int|null $products_count
 */
#[Fillable(['name'])]
class ProductCategory extends Model
{
    /** @use HasFactory<ProductCategoryFactory> */
    use HasFactory;

    /**
     * The legal families every new distributor starts with.
     *
     * @var array<int, string>
     */
    public const array DEFAULT_NAMES = [
        'Toy',
        'Magnetic toy',
        'Filter',
    ];

    /**
     * Get the organization whose list this category belongs to.
     *
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Get the products filed under this category.
     *
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Get the homework sheets kept under this family.
     *
     * Ordered by name, because the list is only ever read as a list: a
     * category holds a handful of these and they are chosen from a select.
     *
     * @return HasMany<ProductTemplate, $this>
     */
    public function templates(): HasMany
    {
        return $this->hasMany(ProductTemplate::class)->orderBy('name');
    }

    /**
     * Get the category as an option for a product form.
     *
     * @return array{id: int, label: string}
     */
    public function toOption(): array
    {
        return ['id' => $this->id, 'label' => $this->name];
    }
}
