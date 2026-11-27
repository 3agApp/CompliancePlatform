<?php

namespace App\Models;

use App\Enums\Locale;
use App\Enums\TranslationSource;
use Carbon\CarbonImmutable;
use Database\Factories\ProductTranslationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A product's safety texts in one language other than the one they were
 * written in.
 *
 * @property int $id
 * @property int $product_id
 * @property Locale $locale
 * @property string|null $safety_notice
 * @property string|null $warning_text
 * @property string|null $material_information
 * @property string|null $usage_restrictions
 * @property string|null $safety_instructions
 * @property TranslationSource $source
 * @property string $source_hash
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Product $product
 */
#[Fillable([
    'locale',
    'safety_notice',
    'warning_text',
    'material_information',
    'usage_restrictions',
    'safety_instructions',
    'source',
    'source_hash',
])]
class ProductTranslation extends Model
{
    /** @use HasFactory<ProductTranslationFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'locale' => Locale::class,
            'source' => TranslationSource::class,
        ];
    }

    /**
     * Get the product the texts belong to.
     *
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Determine if the original texts have changed since this was saved.
     */
    public function isOutdated(): bool
    {
        return $this->source_hash !== $this->product->translationSourceHash();
    }

    /**
     * Get the translated texts, keyed by the product's column names.
     *
     * @return array{safety_notice: string|null, warning_text: string|null, material_information: string|null, usage_restrictions: string|null, safety_instructions: string|null}
     */
    public function texts(): array
    {
        return [
            'safety_notice' => $this->safety_notice,
            'warning_text' => $this->warning_text,
            'material_information' => $this->material_information,
            'usage_restrictions' => $this->usage_restrictions,
            'safety_instructions' => $this->safety_instructions,
        ];
    }
}
