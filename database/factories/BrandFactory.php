<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\SupplierConnection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Brand>
 */
class BrandFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'supplier_connection_id' => SupplierConnection::factory(),
            'name' => fake()->unique()->company(),
        ];
    }
}
