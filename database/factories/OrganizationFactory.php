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
     * Indicate that the organization has filled in its company details.
     */
    public function withCompanyProfile(): static
    {
        return $this->state(fn (array $attributes) => [
            'street' => fake()->streetAddress(),
            'postal_code' => fake()->postcode(),
            'city' => fake()->city(),
            'country' => 'CH',
            'phone' => fake()->phoneNumber(),
            'email' => fake()->companyEmail(),
            'website' => 'https://'.fake()->domainName(),
            'vat_number' => 'CHE-'.fake()->numerify('###.###.###').' MWST',
            'represented_by' => fake()->name(),
        ]);
    }
}
