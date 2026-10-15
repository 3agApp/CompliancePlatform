<?php

namespace Database\Factories;

use App\Models\ImportManufacturer;
use App\Models\ImportSource;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ImportManufacturer>
 */
class ImportManufacturerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'import_source_id' => ImportSource::factory(),
            'external_id' => (string) fake()->unique()->numerify('0##'),
            'name' => fake()->unique()->company(),
            'supplier_connection_id' => null,
            'product_template_id' => null,
        ];
    }
}
