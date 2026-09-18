<?php

namespace Database\Factories;

use App\Enums\CountryOfOrigin;
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
     * The category is left unset so a test that asserts on the categories
     * offered by the product pages sees only the ones seeded with the table,
     * rather than one invented for a product it happened to create.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'supplier_connection_id' => null,
            'product_category_id' => null,
            'name' => fake()->unique()->words(3, true),
            'brand' => fake()->company(),
            'ean' => (string) fake()->unique()->numerify('#############'),
            'internal_article_number' => fake()->unique()->bothify('ART-#####'),
            'supplier_article_number' => fake()->unique()->bothify('SUP-#####'),
            'order_number' => fake()->bothify('PO-#####'),
            'country_of_origin' => fake()->randomElement(CountryOfOrigin::cases()),
        ];
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
            'brand' => null,
            'ean' => null,
            'internal_article_number' => null,
            'supplier_article_number' => null,
            'order_number' => null,
            'country_of_origin' => null,
        ]);
    }
}
