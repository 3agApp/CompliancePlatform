<?php

namespace Database\Factories;

use App\Enums\ImportRunStatus;
use App\Models\ImportRun;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ImportRun>
 */
class ImportRunFactory extends Factory
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
            'triggered_by' => null,
            'status' => ImportRunStatus::Completed,
            'total' => 0,
            'started_at' => now(),
            'finished_at' => now(),
        ];
    }

    /**
     * Indicate that the run has not finished yet.
     */
    public function running(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ImportRunStatus::Running,
            'finished_at' => null,
        ]);
    }
}
