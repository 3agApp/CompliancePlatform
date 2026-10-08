<?php

use App\Enums\OrganizationRole;
use App\Enums\ProductRequirement;
use App\Models\Product;
use App\Models\ProductTemplate;

test('a template is added to a category with the requirements it asks for', function () {
    [$user, $distributor] = newOrganizationMember();

    $this->actingAs($user);

    $page = visit(route('categories.index', ['current_organization' => $distributor->slug]));

    /**
     * A category with no templates is expanded already: it cannot take a
     * product yet, and that is the one thing about it worth surfacing.
     */
    $page->assertSee('No templates yet')
        ->click('[data-test="category-row"]:has(td:text-is("Toy")) + [data-test="category-templates"] [data-test="category-new-template-button"]')
        ->assertSee('Add a template to Toy')
        ->fill('@template-name', 'EU toy safety')
        ->click('@template-test-report')
        ->click('@template-warning-text')
        ->click('@save-template-submit')
        ->assertSee('Template created.')
        ->assertSee('EU toy safety')
        ->assertSee('1 document · 1 field')
        ->assertNoJavaScriptErrors();

    expect(ProductTemplate::sole())
        ->name->toBe('EU toy safety')
        ->requires_test_report->toBeTrue()
        ->requires_warning_text->toBeTrue()
        ->requires_certificate->toBeFalse();
});

test('a requirement is turned back off through the edit dialog', function () {
    [$user, $distributor] = newOrganizationMember();

    $template = ProductTemplate::factory()
        ->requiring(ProductRequirement::TestReport, ProductRequirement::Ean)
        ->create([
            'product_category_id' => legalFamily($distributor)->id,
            'name' => 'EU toy safety',
        ]);

    $this->actingAs($user);

    visit(route('categories.index', ['current_organization' => $distributor->slug]))
        ->click('[data-test="template-row"]:has-text("EU toy safety") [data-test="template-edit-button"]')
        ->assertSee('Edit template')
        ->click('@template-ean')
        ->click('@save-template-submit')
        ->assertSee('Template updated.')
        ->assertNoJavaScriptErrors();

    expect($template->fresh())
        ->requires_test_report->toBeTrue()
        ->requires_ean->toBeFalse();
});

/**
 * The ticks come in the groups a product page answers them in, each with
 * a way to tick or clear the lot, and the editor warns how many products
 * a change will reach before it is saved.
 */
test('a whole group of requirements is ticked at once, with the products it reaches named first', function () {
    [$user, $distributor] = newOrganizationMember();
    $connection = newSupplierConnection($distributor);

    $template = ProductTemplate::factory()
        ->requiring(ProductRequirement::TestReport)
        ->create([
            'product_category_id' => legalFamily($distributor)->id,
            'name' => 'EU toy safety',
        ]);

    Product::factory()->count(2)->for($distributor)->usingTemplate($template)->create([
        'supplier_connection_id' => $connection->id,
    ]);

    $this->actingAs($user);

    visit(route('categories.index', ['current_organization' => $distributor->slug]))
        ->click('[data-test="template-row"]:has-text("EU toy safety") [data-test="template-edit-button"]')
        ->assertSeeIn('@template-impact', '2 products use this template.')
        ->assertSeeIn('@template-requirement-total', '1 of 22')
        ->click('[data-test="template-group-identification"] button:text-is("All")')
        ->assertSeeIn('@template-requirement-total', '8 of 22')
        ->click('@save-template-submit')
        ->assertSee('Template updated.')
        ->assertNoJavaScriptErrors();

    expect($template->fresh())
        ->requires_test_report->toBeTrue()
        ->requires_ean->toBeTrue()
        ->requires_country_of_origin->toBeTrue()
        ->requires_warning_text->toBeFalse();
});

test('a template nothing is held to is deleted through the confirmation dialog', function () {
    [$user, $distributor] = newOrganizationMember();

    ProductTemplate::factory()->create([
        'product_category_id' => legalFamily($distributor)->id,
        'name' => 'EU toy safety',
    ]);

    $this->actingAs($user);

    visit(route('categories.index', ['current_organization' => $distributor->slug]))
        ->click('[data-test="template-row"]:has-text("EU toy safety") [data-test="template-delete-button"]')
        ->assertSee('This action cannot be undone.')
        ->click('@delete-template-confirm')
        ->assertSee('Template deleted.')
        ->assertDontSee('EU toy safety')
        ->assertNoJavaScriptErrors();

    expect(ProductTemplate::query()->count())->toBe(0);
});

test('the delete dialog refuses a template that is still held to by a product', function () {
    [$user, $distributor] = newOrganizationMember();
    $connection = newSupplierConnection($distributor);

    $template = ProductTemplate::factory()->create([
        'product_category_id' => legalFamily($distributor)->id,
        'name' => 'EU toy safety',
    ]);

    Product::factory()->count(2)->for($distributor)->usingTemplate($template)->create([
        'supplier_connection_id' => $connection->id,
    ]);

    $this->actingAs($user);

    visit(route('categories.index', ['current_organization' => $distributor->slug]))
        ->click('[data-test="template-row"]:has-text("EU toy safety") [data-test="template-delete-button"]')
        ->assertSee('is still used by 2 products')
        ->assertMissing('@delete-template-confirm')
        ->assertNoJavaScriptErrors();

    expect(ProductTemplate::query()->count())->toBe(1);
});

test('a member sees the templates without the controls that change them', function () {
    [$user, $distributor] = newOrganizationMember(OrganizationRole::Member);

    ProductTemplate::factory()->create([
        'product_category_id' => legalFamily($distributor)->id,
        'name' => 'EU toy safety',
    ]);

    $this->actingAs($user);

    visit(route('categories.index', ['current_organization' => $distributor->slug]))
        ->assertSee('EU toy safety')
        ->assertMissing('@category-new-template-button')
        ->assertMissing('@template-edit-button')
        ->assertMissing('@template-delete-button')
        ->assertNoJavaScriptErrors();
});

test('the product page keeps score of what its template still asks for', function () {
    [$user, $distributor] = newOrganizationMember();
    $connection = newSupplierConnection($distributor);

    $template = ProductTemplate::factory()
        ->requiring(
            ProductRequirement::TestReport,
            ProductRequirement::WarningText,
            ProductRequirement::ManualOrInstructions,
        )
        ->create([
            'product_category_id' => legalFamily($distributor)->id,
            'name' => 'EU toy safety',
        ]);

    $product = Product::factory()
        ->for($distributor)
        ->usingTemplate($template)
        ->withoutOptionalDetails()
        ->create([
            'name' => 'Organic Oat Milk',
            'supplier_connection_id' => $connection->id,
        ]);

    $this->actingAs($user);

    $page = visit(route('products.edit', [
        'current_organization' => $distributor->slug,
        'product' => $product->id,
    ]));

    $page->assertSee('Requirements')
        ->assertSee('0%')
        ->assertSee('Still needed')
        ->assertSee('Test report')
        ->assertSee('does not affect the score')
        ->fill('@product-warning-text', 'Not suitable for children under 3 years.')
        ->click('@update-product-submit')
        ->assertSee('Product updated.')
        /**
         * The warning is worth one of the four points the sheet can give:
         * the manual is worth none, so it never drags the score down.
         */
        ->assertSee('25%')
        ->assertSee('Done')
        ->assertNoJavaScriptErrors();
});
