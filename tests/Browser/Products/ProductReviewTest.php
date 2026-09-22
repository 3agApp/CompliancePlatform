<?php

use App\Enums\ProductReviewStatus;
use App\Models\Product;

/**
 * The round trip: the supplier hands a product over, the distributor hands
 * it back with a note, and the supplier hands it over again.
 *
 * Both sides are driven in one test on purpose. The point of the feature is
 * that the two of them are looking at the same record from opposite ends,
 * and nothing about it is worth asserting from one end alone.
 */
test('a product goes to the distributor, comes back with a note, and is approved on the second pass', function () {
    [$distributorUser, $distributor] = newOrganizationMember();
    [$supplierUser, $supplier] = newSupplierMember();

    $connection = newSupplierConnection($distributor, $supplier);

    $product = Product::factory()->for($distributor)->create([
        'name' => 'Magnetic Building Set',
        'supplier_connection_id' => $connection->id,
    ]);

    $supplierUrl = route('products.edit', [
        'current_organization' => $supplier->slug,
        'product' => $product->id,
    ]);

    $distributorUrl = route('products.edit', [
        'current_organization' => $distributor->slug,
        'product' => $product->id,
    ]);

    $this->actingAs($supplierUser);

    visit($supplierUrl)
        ->assertSee('Draft')
        ->assertSee('Not submitted yet')
        /** Nothing here is the supplier's to rule on. */
        ->assertMissing('@product-approve')
        ->assertMissing('@product-request-changes')
        ->click('@product-submit-review')
        ->assertSee('Submitted for review.')
        ->assertSee('In review')
        ->assertSee('Waiting on the distributor.')
        ->assertMissing('@product-submit-review')
        ->assertNoJavaScriptErrors();

    $this->actingAs($distributorUser);

    visit($distributorUrl)
        ->assertSee('In review')
        ->click('@product-request-changes')
        ->assertSee('Send Magnetic Building Set back?')
        ->fill('@review-note', 'The test report is for the 2021 article number.')
        ->click('@request-changes-confirm')
        ->assertSee('Sent back to the supplier.')
        ->assertSee('Changes requested')
        ->assertNoJavaScriptErrors();

    $this->actingAs($supplierUser);

    visit($supplierUrl)
        ->assertSee('Changes requested')
        /** The note is the instruction for everything they do next. */
        ->assertSee('The test report is for the 2021 article number.')
        ->click('@product-submit-review')
        ->assertSee('Submitted for review.')
        ->assertNoJavaScriptErrors();

    $this->actingAs($distributorUser);

    visit($distributorUrl)
        ->click('@product-approve')
        ->assertSee('Product approved.')
        ->assertSee('Approved')
        ->assertNoJavaScriptErrors();

    expect($product->fresh()->review_status)->toBe(ProductReviewStatus::Approved);
});

test('a reviewer cannot send a product back without saying why', function () {
    [$user, $distributor] = newOrganizationMember();
    [, $supplier] = newSupplierMember();

    $connection = newSupplierConnection($distributor, $supplier);

    $product = Product::factory()
        ->for($distributor)
        ->reviewed(ProductReviewStatus::InReview)
        ->create([
            'name' => 'Magnetic Building Set',
            'supplier_connection_id' => $connection->id,
        ]);

    $this->actingAs($user);

    visit(route('products.edit', [
        'current_organization' => $distributor->slug,
        'product' => $product->id,
    ]))
        ->click('@product-request-changes')
        ->click('@request-changes-confirm')
        ->assertSee('Say what still needs to change')
        ->assertNoJavaScriptErrors();

    expect($product->fresh()->review_status)->toBe(ProductReviewStatus::InReview);
});

test('the history says who did what, and an edit by the supplier withdraws the product', function () {
    [, $distributor] = newOrganizationMember();
    [$supplierUser, $supplier] = newSupplierMember();

    $connection = newSupplierConnection($distributor, $supplier);

    $product = Product::factory()
        ->for($distributor)
        ->reviewed(ProductReviewStatus::InReview)
        ->create([
            'name' => 'Magnetic Building Set',
            'supplier_connection_id' => $connection->id,
        ]);

    $this->actingAs($supplierUser);

    visit(route('products.edit', [
        'current_organization' => $supplier->slug,
        'product' => $product->id,
    ]))
        ->assertSee('In review')
        ->fill('@product-warning-text', 'Not suitable for children under 3 years.')
        ->click('@update-product-submit')
        ->assertSee('Product updated.')
        /** The edit takes it back off the distributor's desk. */
        ->assertSee('Draft')
        ->assertSee('Submit for review')
        /** And the history says so, in the supplier's name. */
        ->assertSee('History')
        ->assertSee('Returned to draft')
        ->assertSee('Details updated')
        ->assertSee($supplierUser->name)
        ->assertSee('Warning text')
        ->assertNoJavaScriptErrors();

    expect($product->fresh()->review_status)->toBe(ProductReviewStatus::Draft);
});

test('the catalogue shows where each product stands and can be narrowed to one state', function () {
    [$user, $distributor] = newOrganizationMember();
    [, $supplier] = newSupplierMember();

    $connection = newSupplierConnection($distributor, $supplier);

    Product::factory()
        ->for($distributor)
        ->reviewed(ProductReviewStatus::InReview)
        ->create(['name' => 'Magnetic Building Set', 'supplier_connection_id' => $connection->id]);

    Product::factory()
        ->for($distributor)
        ->reviewed(ProductReviewStatus::Approved)
        ->create(['name' => 'Wooden Train', 'supplier_connection_id' => $connection->id]);

    $this->actingAs($user);

    visit(route('products.index', ['current_organization' => $distributor->slug]))
        ->assertSee('Magnetic Building Set')
        ->assertSee('Wooden Train')
        ->assertSee('In review')
        ->assertSee('Approved')
        ->click('@product-filter-status')
        ->click('[role="option"]:has-text("In review")')
        ->assertSee('Magnetic Building Set')
        ->assertDontSee('Wooden Train')
        ->assertNoJavaScriptErrors();
});
