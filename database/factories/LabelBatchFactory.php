<?php

namespace Database\Factories;

use App\Models\LabelBatch;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LabelBatch>
 */
class LabelBatchFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * The run's organization follows its product, as an issued run's does.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'organization_id' => fn (array $attributes) => Product::query()->whereKey($attributes['product_id'])->value('organization_id'),
            'created_by' => null,
            'quantity' => 1,
            'revoked_at' => null,
        ];
    }
}
