<?php

use App\Enums\ProductReviewStatus;
use App\Models\Organization;
use App\Models\Product;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * A supplier with three products to fill in: one sent back, two never
 * submitted -- the sent-back one first, then the drafts oldest first.
 *
 * @return array{0: User, 1: Organization, 2: Product, 3: Product, 4: Product}
 */
function supplierWithTodo(): array
{
    [, $distributor] = newOrganizationMember();
    [$supplierUser, $supplier] = newSupplierMember();

    $connection = newSupplierConnection($distributor, $supplier);

    $older = Product::factory()->for($distributor)->create([
        'name' => 'Older Draft',
        'supplier_connection_id' => $connection->id,
        'created_at' => now()->subDays(5),
    ]);
    $newer = Product::factory()->for($distributor)->create([
        'name' => 'Newer Draft',
        'supplier_connection_id' => $connection->id,
        'created_at' => now()->subDay(),
    ]);
    $sentBack = Product::factory()->for($distributor)->reviewed(ProductReviewStatus::ChangesRequested)->create([
        'name' => 'Sent Back',
        'supplier_connection_id' => $connection->id,
    ]);

    return [$supplierUser, $supplier, $sentBack, $older, $newer];
}

test('the product page says where the product sits in the supplier to-do and what comes either side', function () {
    [$supplierUser, $supplier, $sentBack, $older, $newer] = supplierWithTodo();

    $this->actingAs($supplierUser)
        ->get(route('products.edit', ['current_organization' => $supplier->slug, 'product' => $older->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('todo.position', 2)
            ->where('todo.total', 3)
            ->where('todo.previous', ['id' => $sentBack->id, 'name' => 'Sent Back'])
            ->where('todo.next', ['id' => $newer->id, 'name' => 'Newer Draft'])
            ->where('todo.counts', ['changes_requested' => 1, 'draft' => 2]),
        );
});

test('a product outside the to-do points at the head of it', function () {
    [$supplierUser, $supplier, $sentBack] = supplierWithTodo();

    $approved = Product::factory()
        ->for($sentBack->organization)
        ->reviewed(ProductReviewStatus::Approved)
        ->create(['supplier_connection_id' => $sentBack->supplier_connection_id]);

    $this->actingAs($supplierUser)
        ->get(route('products.edit', ['current_organization' => $supplier->slug, 'product' => $approved->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('todo.position', null)
            ->where('todo.previous', null)
            ->where('todo.next.id', $sentBack->id),
        );
});

test('submitting with the next product named goes straight on to it', function () {
    [$supplierUser, $supplier, , $older, $newer] = supplierWithTodo();

    $this->actingAs($supplierUser)
        ->post(route('products.submit', ['current_organization' => $supplier->slug, 'product' => $older->id]), [
            'next' => $newer->id,
        ])
        ->assertRedirect(route('products.edit', ['current_organization' => $supplier->slug, 'product' => $newer->id]));

    expect($older->fresh()->review_status)->toBe(ProductReviewStatus::InReview);
});

test('a next product outside the to-do is ignored and the page stays on the submitted one', function () {
    [$supplierUser, $supplier, , $older] = supplierWithTodo();
    [, $other] = newOrganizationMember();

    $foreign = Product::factory()->for($other)->create(['supplier_connection_id' => newSupplierConnection($other)->id]);

    $this->actingAs($supplierUser)
        ->post(route('products.submit', ['current_organization' => $supplier->slug, 'product' => $older->id]), [
            'next' => $foreign->id,
        ])
        ->assertRedirect(route('products.edit', ['current_organization' => $supplier->slug, 'product' => $older->id]));
});
