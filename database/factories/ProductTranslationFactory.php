<?php

namespace Database\Factories;

use App\Enums\Locale;
use App\Enums\TranslationSource;
use App\Models\Product;
use App\Models\ProductTranslation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductTranslation>
 */
class ProductTranslationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * The hash is of the product as it stands, so a translation made here
     * starts up to date.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'locale' => Locale::French,
            'warning_text' => 'Ne convient pas aux enfants de moins de 3 ans. Petites pièces.',
            'source' => TranslationSource::Manual,
            'source_hash' => fn (array $attributes): string => Product::query()->whereKey($attributes['product_id'])->firstOrFail()->translationSourceHash(),
        ];
    }

    /**
     * Indicate that the AI wrote the translation.
     */
    public function byAi(): static
    {
        return $this->state(fn (array $attributes) => [
            'source' => TranslationSource::Ai,
        ]);
    }
}
