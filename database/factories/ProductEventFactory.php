<?php

namespace Database\Factories;

use App\Enums\ProductEventType;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductEvent>
 */
class ProductEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * A plain edit by nobody in particular, which is the least a history
     * line can be. Tests that care who did it say so with by().
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'user_id' => null,
            'organization_id' => null,
            'actor_name' => null,
            'actor_organization_name' => null,
            'type' => ProductEventType::Updated,
            'note' => null,
            'changes' => null,
        ];
    }

    /**
     * Indicate who the event was recorded for, snapshotting their names the
     * way a recorded event does.
     */
    public function by(User $user, Organization $organization): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
            'organization_id' => $organization->id,
            'actor_name' => $user->name,
            'actor_organization_name' => $organization->name,
        ]);
    }

    /**
     * Indicate what kind of event it was.
     */
    public function ofType(ProductEventType $type): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => $type,
        ]);
    }
}
