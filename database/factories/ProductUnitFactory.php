<?php

namespace Database\Factories;

use App\Models\LabelBatch;
use App\Models\ProductUnit;
use App\Support\UnitSerial;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductUnit>
 */
class ProductUnitFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * A new packet nobody has checked, in a run of its own.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'label_batch_id' => LabelBatch::factory(),
            'product_id' => fn (array $attributes) => LabelBatch::query()->whereKey($attributes['label_batch_id'])->value('product_id'),
            'serial' => UnitSerial::generate(),
            'first_checked_at' => null,
            'revoked_at' => null,
        ];
    }

    /**
     * Indicate that a device has checked the packet.
     */
    public function checkedBy(string $deviceHash, ?DateTimeInterface $at = null): static
    {
        return $this->state(fn (array $attributes) => [
            'first_checked_at' => $at ?? now(),
        ])->afterCreating(fn (ProductUnit $unit) => $unit->checks()->create(['device_hash' => $deviceHash])->forceFill(['created_at' => $at ?? now()])->save());
    }

    /**
     * Indicate that the distributor has withdrawn the packet's code.
     */
    public function revoked(): static
    {
        return $this->state(fn (array $attributes) => [
            'revoked_at' => now(),
        ]);
    }
}
