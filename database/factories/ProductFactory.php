<?php

namespace Database\Factories;

use App\Enums\CountryOfOrigin;
use App\Models\Brand;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * The brand and the category are left unset so a test that asserts on
     * the lists offered by the product pages sees only the rows it set up
     * itself, rather than ones invented for a product it happened to create.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'supplier_connection_id' => null,
            'product_category_id' => null,
            'brand_id' => null,
            'name' => fake()->unique()->words(3, true),
            'ean' => (string) fake()->unique()->numerify('#############'),
            'internal_article_number' => fake()->unique()->bothify('ART-#####'),
            'supplier_article_number' => fake()->unique()->bothify('SUP-#####'),
            'order_number' => fake()->bothify('PO-#####'),
            'customs_tariff_number' => (string) fake()->numerify('95030075'),
            'country_of_origin' => fake()->randomElement(CountryOfOrigin::cases()),
        ];
    }

    /**
     * Indicate that the product carries the given brand.
     */
    public function ofBrand(Brand $brand): static
    {
        return $this->state(fn (array $attributes) => [
            'brand_id' => $brand->id,
        ]);
    }

    /**
     * Indicate that the product is filed under the given legal family.
     */
    public function inCategory(ProductCategory $category): static
    {
        return $this->state(fn (array $attributes) => [
            'product_category_id' => $category->id,
        ]);
    }

    /**
     * Indicate that the product carries nothing but its name.
     */
    public function withoutOptionalDetails(): static
    {
        return $this->state(fn (array $attributes) => [
            'product_category_id' => null,
            'brand_id' => null,
            'ean' => null,
            'internal_article_number' => null,
            'supplier_article_number' => null,
            'order_number' => null,
            'customs_tariff_number' => null,
            'country_of_origin' => null,
        ]);
    }
}
