<?php

namespace Database\Factories;

use App\Enums\OrganizationType;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Organization>
 */
class OrganizationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'type' => OrganizationType::Distributor,
        ];
    }

    /**
     * Indicate that the organization supplies products to distributors.
     */
    public function supplier(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => OrganizationType::Supplier,
        ]);
    }

    /**
     * Indicate that the organization has been deleted.
     */
    public function trashed(): static
    {
        return $this->state(fn (array $attributes) => [
            'deleted_at' => now(),
        ]);
    }
}
