<?php

use App\Enums\ProductReviewStatus;
use App\Models\Product;

/**
 * The dashboard is somewhere to start work, so what is waiting is named
 * and each name goes straight to the product.
 */
test('a distributor opens a product waiting on review straight from the dashboard', function () {
    [$user, $distributor] = newOrganizationMember();
    [, $supplier] = newSupplierMember();
    $connection = newSupplierConnection($distributor, $supplier);

    Product::factory()->for($distributor)->reviewed(ProductReviewStatus::InReview)->create([
        'name' => 'Magnetic Building Set',
        'supplier_connection_id' => $connection->id,
    ]);

    $this->actingAs($user);

    visit(route('dashboard', ['current_organization' => $distributor->slug]))
        ->assertSee('0 of 1 products approved')
        ->assertSeeIn('@dashboard-queue', 'Magnetic Building Set')
        ->assertSeeIn('@dashboard-queue', $supplier->name)
        ->click('@dashboard-queue-item')
        ->assertSee('Submitted and waiting on the distributor.')
        ->assertVisible('@product-approve')
        ->assertNoJavaScriptErrors();
});

test('a pipeline stage opens the product list narrowed to that stage', function () {
    [$user, $distributor] = newOrganizationMember();
    $connection = newSupplierConnection($distributor);

    Product::factory()->for($distributor)->reviewed(ProductReviewStatus::Approved)->create([
        'name' => 'Wooden Train',
        'supplier_connection_id' => $connection->id,
    ]);
    Product::factory()->for($distributor)->create([
        'name' => 'Magnetic Building Set',
        'supplier_connection_id' => $connection->id,
    ]);

    $this->actingAs($user);

    visit(route('dashboard', ['current_organization' => $distributor->slug]))
        ->assertSeeIn('@dashboard-queue', 'Nothing is waiting on your review')
        ->click('@dashboard-stage-approved')
        ->assertSee('Wooden Train')
        ->assertDontSee('Magnetic Building Set')
        ->assertNoJavaScriptErrors();
});
