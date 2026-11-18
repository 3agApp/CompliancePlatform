<?php

namespace Database\Factories;

use App\Enums\Country;
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
     * Indicate that the organization has filled in its company profile.
     */
    public function withProfile(): static
    {
        return $this->state(fn (array $attributes) => [
            'street' => 'Tramstrasse 66',
            'address_line_2' => 'Im Walzwerk',
            'postal_code' => '4142',
            'city' => 'Münchenstein',
            'country' => Country::Switzerland,
            'map_url' => 'https://maps.app.goo.gl/abc123XYZ',
            'represented_by' => fake()->name(),
            'phone' => '+41 61 683 47 57',
            'email' => fake()->companyEmail(),
            'website' => 'https://'.fake()->domainName(),
            'vat_number' => 'CHE-103.107.876',
        ]);
    }
}
