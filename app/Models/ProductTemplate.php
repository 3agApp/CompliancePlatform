<?php

namespace App\Models;

use App\Enums\ProductRequirement;
use Carbon\CarbonImmutable;
use Database\Factories\ProductTemplateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection as SupportCollection;

/**
 * The homework sheet for a legal family: which papers and which fields a
 * product filed under that family is expected to carry.
 *
 * A category can hold several, because the same kind of product is not
 * always held to the same standard. A product names exactly one, and the
 * template it names sits under the product's own category.
 *
 * None of it is enforced. A product saves with every requirement unmet; the
 * sheet tells both sides of the trade what is still owed, and the
 * completeness score says how far along they are.
 *
 * @property int $id
 * @property int $product_category_id
 * @property string $name
 * @property bool $requires_test_report
 * @property bool $requires_declaration_of_conformity
 * @property bool $requires_certificate
 * @property bool $requires_safety_image
 * @property bool $requires_product_image
 * @property bool $requires_regulatory_document
 * @property bool $requires_manual_or_instructions
 * @property bool $requires_other_document
 * @property bool $requires_brand
 * @property bool $requires_ean
 * @property bool $requires_internal_article_number
 * @property bool $requires_supplier_article_number
 * @property bool $requires_order_number
 * @property bool $requires_customs_tariff_number
 * @property bool $requires_country_of_origin
 * @property bool $requires_age_grading
 * @property bool $requires_safety_notice
 * @property bool $requires_warning_text
 * @property bool $requires_material_information
 * @property bool $requires_usage_restrictions
 * @property bool $requires_safety_instructions
 * @property bool $requires_additional_notes
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read ProductCategory $category
 * @property-read Collection<int, Product> $products
 * @property-read int|null $products_count
 */
#[Fillable([
    'name',
    'requires_test_report',
    'requires_declaration_of_conformity',
    'requires_certificate',
    'requires_safety_image',
    'requires_product_image',
    'requires_regulatory_document',
    'requires_manual_or_instructions',
    'requires_other_document',
    'requires_brand',
    'requires_ean',
    'requires_internal_article_number',
    'requires_supplier_article_number',
    'requires_order_number',
    'requires_customs_tariff_number',
    'requires_country_of_origin',
    'requires_age_grading',
    'requires_safety_notice',
    'requires_warning_text',
    'requires_material_information',
    'requires_usage_restrictions',
    'requires_safety_instructions',
    'requires_additional_notes',
])]
class ProductTemplate extends Model
{
    /** @use HasFactory<ProductTemplateFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * Spelled out from the register rather than by hand, so a requirement
     * added there cannot arrive as the string "0" on the frontend.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return array_fill_keys(ProductRequirement::columns(), 'boolean');
    }

    /**
     * Get the legal family this template belongs to.
     *
     * @return BelongsTo<ProductCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id');
    }

    /**
     * Get the products held to this template.
     *
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Get what this template asks for.
     *
     * @return SupportCollection<int, ProductRequirement>
     */
    public function requirements(): SupportCollection
    {
        return collect(
            ProductRequirement::switchedOn($this->only(ProductRequirement::columns()))
        );
    }

    /**
     * Get the template as an option for a product form.
     *
     * The category comes with it so the form can narrow the list to the
     * family already chosen without a trip to the server, and so does what
     * the template asks for, which is what marks the fields on the form.
     *
     * @return array{id: int, label: string, product_category_id: int, requirements: array<int, string>}
     */
    public function toOption(): array
    {
        return [
            'id' => $this->id,
            'label' => $this->name,
            'product_category_id' => $this->product_category_id,
            'requirements' => $this->requirements()
                ->map(fn (ProductRequirement $requirement) => $requirement->value)
                ->all(),
        ];
    }
}
