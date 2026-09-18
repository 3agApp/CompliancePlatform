<?php

namespace Database\Factories;

use App\Enums\SupplierConnectionStatus;
use App\Models\Organization;
use App\Models\SupplierConnection;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupplierConnection>
 */
class SupplierConnectionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'distributor_organization_id' => Organization::factory(),
            'supplier_organization_id' => null,
            'company_name' => fake()->unique()->company(),
            'contact_email' => fake()->unique()->safeEmail(),
            'status' => SupplierConnectionStatus::Pending,
            'invited_by' => User::factory()->withoutOrganization(),
            'expires_at' => now()->addDays(14),
            'accepted_at' => null,
        ];
    }

    /**
     * Indicate that the connection has been claimed by a supplier.
     */
    public function active(?Organization $supplier = null): static
    {
        return $this->state(fn (array $attributes) => [
            'supplier_organization_id' => $supplier ?? Organization::factory()->supplier(),
            'status' => SupplierConnectionStatus::Active,
            'accepted_at' => now(),
        ]);
    }

    /**
     * Indicate that the invitation was declined.
     */
    public function declined(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SupplierConnectionStatus::Declined,
        ]);
    }

    /**
     * Indicate that the distributor ended the connection.
     */
    public function revoked(?Organization $supplier = null): static
    {
        return $this->state(fn (array $attributes) => [
            'supplier_organization_id' => $supplier ?? $attributes['supplier_organization_id'] ?? null,
            'status' => SupplierConnectionStatus::Revoked,
        ]);
    }

    /**
     * Indicate that the claim link is no longer usable.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => now()->subDay(),
        ]);
    }

    /**
     * Indicate that the invitation was sent to the given email address.
     */
    public function invitedTo(string $email): static
    {
        return $this->state(fn (array $attributes) => [
            'contact_email' => $email,
        ]);
    }
}
