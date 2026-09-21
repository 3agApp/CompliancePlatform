<?php

use App\Enums\OrganizationRole;
use App\Enums\ProductRequirement;
use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductTemplate;
use App\Models\SupplierConnection;
use Illuminate\Support\Facades\Notification;

/**
 * Creating a product asks for what it cannot exist without and nothing
 * else, then opens the product so the rest can be filled in.
 */
test('a product is created from its classification and its name alone', function () {
    [$user, $organization] = newOrganizationMember();
    newSupplierConnection($organization, attributes: ['company_name' => 'Acme Supplies']);

    $template = ProductTemplate::factory()
        ->requiring(ProductRequirement::TestReport, ProductRequirement::Ean)
        ->create([
            'product_category_id' => $organization->productCategories()->where('name', 'Magnetic toy')->value('id'),
            'name' => 'EU toy safety',
        ]);

    $this->actingAs($user);

    $page = visit(route('products.index', ['current_organization' => $organization->slug]));

    $page->assertSee('No products yet')
        ->click('@products-new-product-button')
        ->assertSee('Classify the product')
        /**
         * None of the details belong on this page any more: they are asked
         * for on the product itself.
         */
        ->assertMissing('@product-ean')
        ->assertMissing('@product-brand')
        ->assertMissing('@product-warning-text')
        ->click('@product-supplier')
        ->click('[role="option"]:has-text("Acme Supplies")')
        ->click('@product-category')
        ->click('[role="option"]:has-text("Magnetic toy")')
        ->click('@product-template')
        ->click('[role="option"]:has-text("EU toy safety")')
        /**
         * Choosing the sheet says what it will ask for before a single
         * detail has been typed.
         */
        ->assertSee('EU toy safety asks for')
        ->assertSee('Test report')
        ->fill('@product-name', 'Organic Oat Milk')
        ->click('@create-product-submit')
        ->assertSee('Product created.')
        /**
         * And the product itself is what comes back, with the checklist of
         * everything its template is still waiting for.
         */
        ->assertSee('Organic Oat Milk')
        ->assertSee('Requirements')
        ->assertSee('Still needed')
        ->assertVisible('@product-ean')
        ->assertNoJavaScriptErrors();

    expect(Product::sole())
        ->name->toBe('Organic Oat Milk')
        ->product_category_id->toBe($template->product_category_id)
        ->product_template_id->toBe($template->id)
        ->organization_id->toBe($organization->id)
        ->supplier_connection_id->toBe(SupplierConnection::sole()->id)
        ->brand_id->toBeNull()
        ->ean->toBeNull()
        ->country_of_origin->toBeNull();
});

test('a category with no templates says so rather than letting the form be submitted', function () {
    [$user, $organization] = newOrganizationMember();
    newSupplierConnection($organization, attributes: ['company_name' => 'Acme Supplies']);

    ProductTemplate::factory()->create([
        'product_category_id' => $organization->productCategories()->where('name', 'Toy')->value('id'),
        'name' => 'EU toy safety',
    ]);

    $this->actingAs($user);

    visit(route('products.create', ['current_organization' => $organization->slug]))
        ->click('@product-category')
        ->click('[role="option"]:has-text("Filter")')
        ->assertSee('Filter has no templates yet')
        ->assertPresent('@product-manage-categories-link')
        ->assertNoJavaScriptErrors();
});

test('the identification details of a product are edited on its own page', function () {
    [$user, $organization] = newOrganizationMember();
    $connection = newSupplierConnection($organization, attributes: ['company_name' => 'Acme Supplies']);
    carriedBrand($connection, 'Magna-Tiles');

    $magneticToy = ProductTemplate::factory()->create([
        'product_category_id' => $organization->productCategories()->where('name', 'Magnetic toy')->value('id'),
        'name' => 'EU magnetic toy',
    ]);

    $product = Product::factory()->for($organization)->withoutOptionalDetails()->create([
        'name' => 'Organic Oat Milk',
        'supplier_connection_id' => $connection->id,
    ]);

    $this->actingAs($user);

    $page = visit(route('products.edit', [
        'current_organization' => $organization->slug,
        'product' => $product->id,
    ]));

    /**
     * A sheet belongs to one family, so moving the product to another one
     * asks the template question again.
     */
    $page->click('@product-brand')
        ->click('[role="option"]:has-text("Magna-Tiles")')
        ->click('@product-category')
        ->click('[role="option"]:has-text("Magnetic toy")')
        ->click('@product-template')
        ->click('[role="option"]:has-text("EU magnetic toy")')
        ->fill('@product-internal-article-number', 'ART-10294')
        ->fill('@product-supplier-article-number', 'MT-BLUE-32')
        ->fill('@product-order-number', 'PO-2026-0148')
        ->fill('@product-customs-tariff-number', '9503.00.75')
        ->click('@update-product-submit')
        ->assertSee('Product updated.')
        ->assertNoJavaScriptErrors();

    expect($product->fresh())
        ->brand_id->toBe(Brand::query()->where('name', 'Magna-Tiles')->value('id'))
        ->product_category_id->toBe($magneticToy->product_category_id)
        ->product_template_id->toBe($magneticToy->id)
        ->internal_article_number->toBe('ART-10294')
        ->supplier_article_number->toBe('MT-BLUE-32')
        ->order_number->toBe('PO-2026-0148')
        ->customs_tariff_number->toBe('95030075');
});

/**
 * Every group of questions sits on the page at once rather than behind a
 * tab, so one save carries the lot however far down the page the person is
 * working. The links beside them only move the view.
 */
test('the edit page stacks every group of fields, with links that jump to them', function () {
    [$user, $organization] = newOrganizationMember();
    $connection = newSupplierConnection($organization);

    $product = Product::factory()->for($organization)->create([
        'name' => 'Organic Oat Milk',
        'supplier_connection_id' => $connection->id,
    ]);

    $this->actingAs($user);

    visit(route('products.edit', [
        'current_organization' => $organization->slug,
        'product' => $product->id,
    ]))
        ->assertSee('Classification')
        ->assertSee('Product details')
        ->assertSee('Compliance details')
        ->assertSee('Documents')
        /**
         * A field from each group, without a thing having been opened
         * first: the classification, the details and the compliance
         * answers are all on the page together.
         */
        ->assertVisible('@product-supplier')
        ->assertVisible('@product-name')
        ->assertVisible('@product-warning-text')
        /** The first section is the one marked until the page is scrolled. */
        ->assertAriaAttribute('@edit-product-jump-product-classification', 'current', 'true')
        /**
         * The jump moves the view and puts the section under the cursor,
         * and leaves everything else exactly where it was.
         */
        ->click('@edit-product-jump-product-documents')
        ->assertScript('document.activeElement.id', 'product-documents')
        ->assertVisible('@product-name')
        ->assertNoJavaScriptErrors();
});

/**
 * The links double as a map of the work: what the template is still
 * waiting for, counted where it is answered.
 */
test('the section links carry what each section still owes its template', function () {
    [$user, $organization] = newOrganizationMember();
    $connection = newSupplierConnection($organization);

    $template = ProductTemplate::factory()
        ->requiring(
            ProductRequirement::TestReport,
            ProductRequirement::Ean,
            ProductRequirement::WarningText,
            ProductRequirement::AgeGrading,
        )
        ->create([
            'product_category_id' => legalFamily($organization)->id,
            'name' => 'EU toy safety',
        ]);

    $product = Product::factory()
        ->for($organization)
        ->usingTemplate($template)
        ->withoutOptionalDetails()
        ->create([
            'name' => 'Organic Oat Milk',
            'supplier_connection_id' => $connection->id,
        ]);

    $this->actingAs($user);

    visit(route('products.edit', [
        'current_organization' => $organization->slug,
        'product' => $product->id,
    ]))
        /** One number field, two safety answers, one paper. */
        ->assertSeeIn('@product-identification-outstanding', '1')
        ->assertSeeIn('@product-compliance-outstanding', '2')
        ->assertSeeIn('@product-documents-outstanding', '1')
        /** Nothing is asked of the classification, so it carries no mark. */
        ->assertMissing('@product-classification-outstanding')
        ->assertNoJavaScriptErrors();
});

/**
 * The form runs the height of four panels, so the way to save it follows
 * the person down the page instead of waiting at the foot of it.
 */
test('a change raises a save bar that saves the whole form', function () {
    [$user, $organization] = newOrganizationMember();
    $connection = newSupplierConnection($organization);

    $product = Product::factory()->for($organization)->create([
        'name' => 'Oat Milk',
        'supplier_connection_id' => $connection->id,
    ]);

    $this->actingAs($user);

    visit(route('products.edit', [
        'current_organization' => $organization->slug,
        'product' => $product->id,
    ]))
        /** Nothing has changed yet, so there is nothing to say. */
        ->assertMissing('@product-unsaved-bar')
        ->fill('@product-warning-text', 'Not suitable for children under 3 years.')
        /**
         * Back at the top of the page, where the form's own save button is
         * far below: this is where the bar earns its place.
         */
        ->click('@edit-product-jump-product-classification')
        ->assertSee('Unsaved changes')
        ->click('@product-unsaved-bar-submit')
        ->assertSee('Product updated.')
        /** And it stands down once the edit is safe. */
        ->assertMissing('@product-unsaved-bar')
        ->assertNoJavaScriptErrors();

    expect($product->fresh()->warning_text)
        ->toBe('Not suitable for children under 3 years.');
});

test('the edit page keeps what was typed and shows the validation message for an invalid barcode', function () {
    [$user, $organization] = newOrganizationMember();
    $connection = newSupplierConnection($organization, attributes: ['company_name' => 'Acme Supplies']);

    $product = Product::factory()->for($organization)->withoutOptionalDetails()->create([
        'name' => 'Oat Milk',
        'supplier_connection_id' => $connection->id,
    ]);

    $this->actingAs($user);

    visit(route('products.edit', [
        'current_organization' => $organization->slug,
        'product' => $product->id,
    ]))
        ->fill('@product-name', 'Organic Oat Milk')
        ->fill('@product-ean', '123')
        ->click('@update-product-submit')
        ->assertSee('The EAN/barcode must be 8, 12, 13, or 14 digits.')
        ->assertValue('@product-name', 'Organic Oat Milk')
        ->assertNoJavaScriptErrors();

    expect($product->fresh())
        ->name->toBe('Oat Milk')
        ->ean->toBeNull();
});

test('a product is deleted through the confirmation dialog', function () {
    [$user, $organization] = newOrganizationMember();
    $connection = newSupplierConnection($organization);
    $product = Product::factory()->for($organization)->create([
        'name' => 'Organic Oat Milk',
        'supplier_connection_id' => $connection->id,
    ]);

    $this->actingAs($user);

    $page = visit(route('products.index', ['current_organization' => $organization->slug]));

    $page->click('@product-delete-button')
        ->assertSee('This action cannot be undone.')
        ->click('@delete-product-confirm')
        ->assertSee('No products yet')
        ->assertNoJavaScriptErrors();

    $this->assertModelMissing($product);
});

test('a member sees the product list without the create and delete controls', function () {
    [$user, $organization] = newOrganizationMember(OrganizationRole::Member);
    $connection = newSupplierConnection($organization);
    Product::factory()->for($organization)->create([
        'name' => 'Organic Oat Milk',
        'supplier_connection_id' => $connection->id,
    ]);

    $this->actingAs($user);

    visit(route('products.index', ['current_organization' => $organization->slug]))
        ->assertSee('Organic Oat Milk')
        ->assertMissing('@products-new-product-button')
        ->assertMissing('@product-delete-button')
        ->assertPresent('@product-edit-button')
        ->assertNoJavaScriptErrors();
});

test('a supplier invited from the products page can be assigned without a refresh', function () {
    Notification::fake();

    [$user, $distributor] = newOrganizationMember();

    /**
     * A product needs a template as well as a supplier, so the family has
     * one already: the missing supplier is what this test is about.
     */
    ProductTemplate::factory()->create([
        'product_category_id' => $distributor->productCategories()->where('name', 'Toy')->value('id'),
    ]);

    $this->actingAs($user);

    $page = visit(route('products.index', ['current_organization' => $distributor->slug]));

    $page->assertPresent('@products-invite-supplier-button')
        ->assertMissing('@products-new-product-button')
        ->click('@products-invite-supplier-button')
        ->assertSee('No suppliers yet')
        /**
         * Poison the cache the way a real mouse does on the way to the invite
         * button: the sidebar links prefetch on hover and hold the response
         * for 30 seconds. Inertia refuses to prefetch the page it is already
         * on, so this only works from another page — which is exactly where
         * the user is when they invite. Without it the click below would send
         * a fresh request and the test would pass either way.
         */
        ->hover('@nav-products')
        ->wait(1.5)
        ->click('@invite-supplier-button')
        ->fill('@supplier-company-name', 'Acme Supplies AG')
        ->fill('@supplier-contact-email', 'compliance@acme.test')
        ->click('@invite-supplier-submit')
        ->assertSee('Acme Supplies AG')
        /**
         * Back to products the way the user does it: a sidebar click, with no
         * reload. The invitation is still pending, so this covers both that
         * the stale copy was dropped and that a pending supplier is offered.
         */
        ->click('@nav-products')
        ->assertPresent('@products-new-product-button')
        ->assertMissing('@products-invite-supplier-button')
        ->click('@products-new-product-button')
        ->click('@product-supplier')
        ->assertSee('Acme Supplies AG (invitation pending)')
        ->assertNoJavaScriptErrors();
});
