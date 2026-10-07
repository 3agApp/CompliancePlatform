<?php

use App\Enums\OrganizationRole;
use App\Enums\ProductDocumentType;
use App\Enums\ProductRequirement;
use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductDocument;
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
test('the edit page keeps the fields on one tab and the rest of the product on its own tabs', function () {
    [$user, $organization] = newOrganizationMember();
    $connection = newSupplierConnection($organization);

    $product = Product::factory()->for($organization)->create([
        'name' => 'Organic Oat Milk',
        'supplier_connection_id' => $connection->id,
    ]);

    ProductDocument::factory()->for($product)->ofType(ProductDocumentType::TestReport)->create([
        'name' => 'en71-part-1.pdf',
    ]);

    $this->actingAs($user);

    visit(route('products.edit', [
        'current_organization' => $organization->slug,
        'product' => $product->id,
    ]))
        ->assertSee('Classification')
        ->assertSee('Product details')
        ->assertSee('Compliance details')
        /**
         * A field from each group, without a thing having been opened
         * first: the whole form is on the details tab together.
         */
        ->assertVisible('@product-supplier')
        ->assertVisible('@product-name')
        ->assertVisible('@product-age-grading')
        ->assertAriaAttribute('@product-tab-details', 'selected', 'true')
        ->assertDontSee('en71-part-1.pdf')
        /** The jump links move within the tab. */
        ->assertAriaAttribute('@edit-product-jump-product-classification', 'current', 'true')
        ->click('@edit-product-jump-product-compliance')
        ->assertScript('document.activeElement.id', 'product-compliance')
        /** The documents have a tab of their own, kept in the address. */
        ->click('@product-tab-documents')
        ->assertSee('en71-part-1.pdf')
        ->assertMissing('@product-name')
        ->assertQueryStringHas('tab', 'documents')
        ->assertNoJavaScriptErrors();
});

/**
 * An edit is still unsaved after a look at another tab, and the way to
 * save it goes along.
 */
test('an edit survives a change of tab and is saved from the bar on another tab', function () {
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
        ->fill('@product-name', 'Oat Milk Barista')
        ->click('@product-tab-history')
        ->assertSee('Unsaved changes')
        ->click('@product-tab-details')
        ->assertValue('@product-name', 'Oat Milk Barista')
        ->click('@product-tab-history')
        ->click('@product-unsaved-bar-submit')
        ->assertSee('Product updated.')
        ->assertMissing('@product-unsaved-bar')
        ->assertNoJavaScriptErrors();

    expect($product->fresh()->name)->toBe('Oat Milk Barista');
});

/**
 * A save refused from the bar on another tab goes back to the field, and
 * the address follows, so a reload opens the same place.
 */
test('a save refused from another tab shows the details and says so in the address', function () {
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
        ->fill('@product-ean', '123')
        ->click('@product-tab-history')
        ->assertQueryStringHas('tab', 'history')
        ->click('@product-unsaved-bar-submit')
        ->assertSee('The EAN/barcode must be 8, 12, 13, or 14 digits.')
        ->assertAriaAttribute('@product-tab-details', 'selected', 'true')
        ->assertQueryStringMissing('tab')
        ->assertNoJavaScriptErrors();
});

/**
 * An answer typed into a field the template asked for is not taken off the
 * form when the template changes before it is saved.
 */
test('typed compliance text stays on the form when the template changes', function () {
    [$user, $organization] = newOrganizationMember();
    $connection = newSupplierConnection($organization);

    $template = ProductTemplate::factory()
        ->requiring(ProductRequirement::WarningText)
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
        ->fill('@product-warning-text', 'Not suitable for children under 3 years.')
        /** Another family clears the template, and with it the requirement. */
        ->click('@product-category')
        ->click('[role="option"]:has-text("Filter")')
        ->assertVisible('@product-warning-text')
        ->assertValue('@product-warning-text', 'Not suitable for children under 3 years.')
        /** An untouched answer the template no longer asks for is tucked away. */
        ->assertPresent('@product-add-safety-notice')
        ->assertNoJavaScriptErrors();
});

/**
 * What the template still waits for is listed above the tabs, and each
 * item leads to where it is answered.
 */
test('an outstanding paper in the status strip opens the documents tab', function () {
    [$user, $organization] = newOrganizationMember();
    $connection = newSupplierConnection($organization);

    $template = ProductTemplate::factory()
        ->requiring(ProductRequirement::TestReport)
        ->create([
            'product_category_id' => legalFamily($organization)->id,
            'name' => 'EU toy safety',
        ]);

    $product = Product::factory()
        ->for($organization)
        ->usingTemplate($template)
        ->create([
            'name' => 'Organic Oat Milk',
            'supplier_connection_id' => $connection->id,
        ]);

    $this->actingAs($user);

    visit(route('products.edit', [
        'current_organization' => $organization->slug,
        'product' => $product->id,
    ]))
        ->assertSeeIn('@product-requirements-summary', 'Test report')
        ->click('@product-outstanding-item')
        ->assertAriaAttribute('@product-tab-documents', 'selected', 'true')
        ->assertVisible('@product-tabpanel-documents')
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
 * A field the template asks for and the product leaves empty says so on
 * its label, and stops saying so once it is answered.
 */
test('a field the template still waits for is marked as needed until it is saved', function () {
    [$user, $organization] = newOrganizationMember();
    $connection = newSupplierConnection($organization);

    $template = ProductTemplate::factory()
        ->requiring(ProductRequirement::Ean, ProductRequirement::WarningText)
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
            'ean' => '4006381333931',
            'supplier_connection_id' => $connection->id,
        ]);

    $this->actingAs($user);

    visit(route('products.edit', [
        'current_organization' => $organization->slug,
        'product' => $product->id,
    ]))
        /** The barcode is in, so only the warning is still owed. */
        ->assertCount('@field-needed', 1)
        ->assertSeeIn('label[for="edit-product-warning-text"]', 'Needed')
        ->assertDontSeeIn('label[for="edit-product-ean"]', 'Needed')
        ->fill('@product-warning-text', 'Not suitable for children under 3 years.')
        ->click('@update-product-submit')
        ->assertSee('Product updated.')
        ->assertMissing('@field-needed')
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
        ->click('@product-add-warning-text')
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

/**
 * Adding a product is often when a distributor first names its supplier,
 * well before there is anything worth inviting them to.
 */
test('a supplier is added from the product form without an invitation and chosen for it', function () {
    Notification::fake();

    [$user, $organization] = newOrganizationMember();
    newSupplierConnection($organization, attributes: ['company_name' => 'Existing Supplies']);

    ProductTemplate::factory()->create([
        'product_category_id' => legalFamily($organization)->id,
    ]);

    $this->actingAs($user);

    visit(route('products.create', ['current_organization' => $organization->slug]))
        ->fill('@product-name', 'Organic Oat Milk')
        ->click('@product-add-supplier')
        ->fill('@supplier-company-name', 'Acme Supplies AG')
        ->fill('@supplier-contact-email', 'compliance@acme.test')
        ->click('@supplier-send-invitation')
        ->click('@invite-supplier-submit')
        ->assertSee('Supplier added. Invite them whenever you are ready.')
        ->assertSeeIn('@product-supplier', 'Acme Supplies AG (not invited yet)')
        /** What was typed before the dialog opened is still there. */
        ->assertValue('@product-name', 'Organic Oat Milk')
        ->assertNoJavaScriptErrors();

    Notification::assertNothingSent();
    expect(SupplierConnection::where('company_name', 'Acme Supplies AG')->sole()->invited_at)->toBeNull();
});

test('a product opens from its name in the list', function () {
    [$user, $organization] = newOrganizationMember();
    $connection = newSupplierConnection($organization);
    Product::factory()->for($organization)->create([
        'name' => 'Organic Oat Milk',
        'supplier_connection_id' => $connection->id,
    ]);

    $this->actingAs($user);

    visit(route('products.index', ['current_organization' => $organization->slug]))
        ->click('@product-name-link')
        ->assertSee('Product details')
        ->assertSee('Save changes')
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
