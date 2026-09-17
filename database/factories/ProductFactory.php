<?php

namespace Database\Factories;

use App\Enums\CountryOfOrigin;
use App\Models\Organization;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->unique()->words(3, true),
            'ean' => (string) fake()->unique()->numerify('#############'),
            'country_of_origin' => fake()->randomElement(CountryOfOrigin::cases()),
        ];
    }

    /**
     * Indicate that the product has no barcode or country of origin.
     */
    public function withoutOptionalDetails(): static
    {
        return $this->state(fn (array $attributes) => [
            'ean' => null,
            'country_of_origin' => null,
        ]);
    }
}
