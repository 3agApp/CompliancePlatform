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
        ->assertSeeIn('@dashboard-stage-in_review', '1')
        ->assertSeeIn('@dashboard-stage-in_review', 'Waiting on your decision')
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

/**
 * What is stuck is named with the step that unsticks it, and each
 * supplier's row leads to their products.
 */
test('a distributor sees what is stuck and follows a supplier into its products', function () {
    [$user, $distributor] = newOrganizationMember();
    [, $supplier] = newSupplierMember();

    $quiet = newSupplierConnection($distributor, $supplier);
    $expired = newSupplierConnection($distributor, attributes: ['company_name' => 'Late Supplies', 'expires_at' => now()->subDay()]);

    Product::factory()->for($distributor)->create([
        'name' => 'Magnetic Building Set',
        'supplier_connection_id' => $quiet->id,
        'updated_at' => now()->subDays(20),
    ]);
    Product::factory()->for($distributor)->create([
        'name' => 'Wooden Train',
        'supplier_connection_id' => $expired->id,
    ]);

    $this->actingAs($user);

    visit(route('dashboard', ['current_organization' => $distributor->slug]))
        ->assertSeeIn('@dashboard-attention-expired', 'Late Supplies')
        ->assertSeeIn('@dashboard-attention-quiet', $supplier->name)
        ->assertSeeIn('@dashboard-supplier-progress', 'Invitation expired')
        ->click('View drafts')
        ->assertSee('Magnetic Building Set')
        ->assertDontSee('Wooden Train')
        ->assertQueryStringHas('status', 'draft')
        ->assertNoJavaScriptErrors();
});
