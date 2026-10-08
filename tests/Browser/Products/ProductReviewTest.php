<?php

use App\Enums\ProductRequirement;
use App\Enums\ProductReviewStatus;
use App\Models\Product;
use App\Models\ProductTemplate;

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

/**
 * Approving is what turns the public seal to verified, so a product its
 * template says is unfinished is approved only once the reviewer has seen
 * what is missing -- and sending it back is offered right beside.
 */
test('approving an incomplete product names what is missing and offers sending it back instead', function () {
    [$user, $distributor] = newOrganizationMember();
    [, $supplier] = newSupplierMember();

    $connection = newSupplierConnection($distributor, $supplier);

    $template = ProductTemplate::factory()
        ->requiring(ProductRequirement::TestReport)
        ->create(['product_category_id' => legalFamily($distributor)->id]);

    $product = Product::factory()
        ->for($distributor)
        ->usingTemplate($template)
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
        ->assertSee('1 requirement is still open.')
        ->click('@product-approve')
        ->assertSee('Approve Magnetic Building Set with a requirement still open?')
        ->assertSeeIn('@approve-outstanding', 'Test report')
        ->click('@approve-request-changes-instead')
        ->assertSee('Send Magnetic Building Set back?')
        ->assertVisible('@review-note')
        ->assertNoJavaScriptErrors();

    expect($product->fresh()->review_status)->toBe(ProductReviewStatus::InReview);
});

test('an incomplete product is approved once the reviewer confirms', function () {
    [$user, $distributor] = newOrganizationMember();
    $connection = newSupplierConnection($distributor);

    $template = ProductTemplate::factory()
        ->requiring(ProductRequirement::TestReport)
        ->create(['product_category_id' => legalFamily($distributor)->id]);

    $product = Product::factory()
        ->for($distributor)
        ->usingTemplate($template)
        ->reviewed(ProductReviewStatus::InReview)
        ->create(['supplier_connection_id' => $connection->id]);

    $this->actingAs($user);

    visit(route('products.edit', [
        'current_organization' => $distributor->slug,
        'product' => $product->id,
    ]))
        ->click('@product-approve')
        ->click('@approve-confirm')
        ->assertSee('Product approved.')
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

test('a reviewer takes back an approval given by mistake, with a reason', function () {
    [$user, $distributor] = newOrganizationMember();
    [, $supplier] = newSupplierMember();

    $connection = newSupplierConnection($distributor, $supplier);

    $product = Product::factory()
        ->for($distributor)
        ->reviewed(ProductReviewStatus::Approved)
        ->create([
            'name' => 'Magnetic Building Set',
            'supplier_connection_id' => $connection->id,
        ]);

    $this->actingAs($user);

    visit(route('products.edit', [
        'current_organization' => $distributor->slug,
        'product' => $product->id,
    ]))
        ->assertSee('Approved')
        ->click('@product-reopen-review')
        ->assertSee('Take back the approval on Magnetic Building Set?')
        /** A reason is required before anything changes. */
        ->click('@reopen-review-confirm')
        ->assertSee('Say why the approval is being taken back')
        ->fill('@reopen-note', 'Approved by mistake: the test report has not been checked yet.')
        ->click('@reopen-review-confirm')
        ->assertSee('Approval taken back. The product is in review again.')
        ->assertSee('In review')
        /** Back in front of the reviewer, with both moves offered again. */
        ->assertVisible('@product-approve')
        ->assertVisible('@product-request-changes')
        ->assertMissing('@product-reopen-review')
        ->click('@product-tab-history')
        ->assertSee('Approval taken back')
        ->assertSee('Approved by mistake: the test report has not been checked yet.')
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
        ->click('@product-add-warning-text')
        ->fill('@product-warning-text', 'Not suitable for children under 3 years.')
        ->click('@update-product-submit')
        ->assertSee('Product updated.')
        /** The edit takes it back off the distributor's desk. */
        ->assertSee('Draft')
        ->assertSee('Submit for review')
        /** And the history says so, in the supplier's name. */
        ->click('@product-tab-history')
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
        /** Each state is a tab, counted under the other filters. */
        ->assertSeeIn('@product-status-tab-in_review', '1')
        ->assertSeeIn('@product-status-tab-all', '2')
        ->click('@product-status-tab-in_review')
        ->assertSee('Magnetic Building Set')
        ->assertDontSee('Wooden Train')
        ->assertQueryStringHas('status', 'in_review')
        ->assertNoJavaScriptErrors();
});

/**
 * A supplier works down their to-do without going back to the list: the
 * bar says where they are, and submitting can carry straight on.
 */
test('a supplier submits one product and lands on the next in their to-do', function () {
    [, $distributor] = newOrganizationMember();
    [$supplierUser, $supplier] = newSupplierMember();

    $connection = newSupplierConnection($distributor, $supplier);

    $first = Product::factory()->for($distributor)->create([
        'name' => 'Magnetic Building Set',
        'supplier_connection_id' => $connection->id,
        'created_at' => now()->subDays(2),
    ]);
    Product::factory()->for($distributor)->create([
        'name' => 'Wooden Train',
        'supplier_connection_id' => $connection->id,
        'created_at' => now()->subDay(),
    ]);

    $this->actingAs($supplierUser);

    visit(route('products.edit', ['current_organization' => $supplier->slug, 'product' => $first->id]))
        ->assertSeeIn('@product-todo-position', '1 of 2')
        ->assertSeeIn('@product-todo-next', 'Wooden Train')
        ->click('@product-submit-and-next')
        ->assertSee('Submitted Magnetic Building Set. Next up: Wooden Train.')
        ->assertSee('Wooden Train')
        /** The submitted one has left the to-do, so this is all that is left. */
        ->assertSeeIn('@product-todo-position', '1 of 1')
        ->assertNoJavaScriptErrors();

    expect($first->fresh()->review_status)->toBe(ProductReviewStatus::InReview);
});
