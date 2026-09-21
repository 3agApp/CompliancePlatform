<?php

use App\Enums\OrganizationRole;
use App\Enums\ProductDocumentType;
use App\Enums\ProductRequirement;
use App\Models\Product;
use App\Models\ProductDocument;
use App\Models\ProductTemplate;

test('the compliance details of a product are filled in on its own page', function () {
    [$user, $organization] = newOrganizationMember();
    $connection = newSupplierConnection($organization, attributes: ['company_name' => 'Acme Supplies']);

    $product = Product::factory()->for($organization)->withoutOptionalDetails()->create([
        'name' => 'Organic Oat Milk',
        'supplier_connection_id' => $connection->id,
    ]);

    $this->actingAs($user);

    visit(route('products.edit', [
        'current_organization' => $organization->slug,
        'product' => $product->id,
    ]))
        ->assertSee('Compliance details')
        ->fill('@product-age-grading', '3+')
        ->fill('@product-safety-notice', 'Keep the packaging until the product has been checked.')
        ->fill('@product-warning-text', 'Not suitable for children under 3 years. Small parts.')
        ->fill('@product-material-information', 'ABS plastic, neodymium magnets, water based paint.')
        ->fill('@product-usage-restrictions', 'Indoor use only. Not for use in water.')
        ->fill('@product-safety-instructions', 'Inspect for damage before each use.')
        ->fill('@product-additional-notes', 'Replacement parts are available from the manufacturer.')
        ->click('@update-product-submit')
        ->assertSee('Product updated.')
        ->assertNoJavaScriptErrors();

    expect($product->fresh())
        ->age_grading->toBe('3+')
        ->safety_notice->toBe('Keep the packaging until the product has been checked.')
        ->warning_text->toBe('Not suitable for children under 3 years. Small parts.')
        ->material_information->toBe('ABS plastic, neodymium magnets, water based paint.')
        ->usage_restrictions->toBe('Indoor use only. Not for use in water.')
        ->safety_instructions->toBe('Inspect for damage before each use.')
        ->additional_notes->toBe('Replacement parts are available from the manufacturer.');
});

/*
 * The upload itself is not driven from the browser. The in-process server the
 * browser plugin serves the application from does not parse
 * multipart/form-data -- files are an open TODO in the plugin's
 * LaravelHttpServer -- so a submitted file never reaches the application at
 * all. Filing a document is covered end to end in
 * tests/Feature/Products/ProductDocumentTest.php; what is left for the
 * browser is everything around it.
 */

test('a document is taken off a product through the confirmation dialog', function () {
    [$user, $organization] = newOrganizationMember();
    $connection = newSupplierConnection($organization);

    $product = Product::factory()->for($organization)->create([
        'name' => 'Organic Oat Milk',
        'supplier_connection_id' => $connection->id,
    ]);

    ProductDocument::factory()
        ->for($product)
        ->ofType(ProductDocumentType::TestReport)
        ->create(['name' => 'en71-part-1.pdf', 'uploaded_by' => $user->id]);

    $this->actingAs($user);

    visit(route('products.edit', [
        'current_organization' => $organization->slug,
        'product' => $product->id,
    ]))
        ->assertSee('Test report')
        ->assertSee('en71-part-1.pdf')
        ->click('@product-document-delete-button')
        ->assertSee('This action cannot be undone.')
        ->click('@delete-document-confirm')
        ->assertSee('Document deleted.')
        ->assertSee('No documents yet')
        ->assertNoJavaScriptErrors();

    $this->assertDatabaseCount('product_documents', 0);
});

/**
 * Several documents of one kind gather under a single heading rather than
 * replacing each other.
 */
test('documents of the same kind are listed together under their heading', function () {
    [$user, $organization] = newOrganizationMember();
    $connection = newSupplierConnection($organization);

    $product = Product::factory()->for($organization)->create([
        'supplier_connection_id' => $connection->id,
    ]);

    ProductDocument::factory()
        ->for($product)
        ->ofType(ProductDocumentType::TestReport)
        ->create(['name' => 'magnets.pdf']);

    ProductDocument::factory()
        ->for($product)
        ->ofType(ProductDocumentType::TestReport)
        ->create(['name' => 'paint.pdf']);

    ProductDocument::factory()
        ->for($product)
        ->ofType(ProductDocumentType::Certificate)
        ->create(['name' => 'ce-marking.pdf']);

    $this->actingAs($user);

    visit(route('products.edit', [
        'current_organization' => $organization->slug,
        'product' => $product->id,
    ]))
        ->assertSee('magnets.pdf')
        ->assertSee('paint.pdf')
        ->assertSee('ce-marking.pdf')
        ->assertSee('Test report')
        ->assertSee('Certificate')
        ->assertNoJavaScriptErrors();
});

/**
 * The upload area answers the checklist: it offers the kinds still owed,
 * takes a file by drag or by browse, and stays shut until it has both.
 */
test('the documents panel offers the kinds the template is still waiting for', function () {
    [$user, $organization] = newOrganizationMember();
    $connection = newSupplierConnection($organization);

    $template = ProductTemplate::factory()
        ->requiring(
            ProductRequirement::TestReport,
            ProductRequirement::Certificate,
            ProductRequirement::WarningText,
        )
        ->create([
            'product_category_id' => legalFamily($organization)->id,
            'name' => 'EU toy safety',
        ]);

    $product = Product::factory()
        ->for($organization)
        ->usingTemplate($template)
        ->create(['supplier_connection_id' => $connection->id]);

    ProductDocument::factory()
        ->for($product)
        ->ofType(ProductDocumentType::TestReport)
        ->create(['name' => 'en71-part-1.pdf', 'uploaded_by' => $user->id]);

    $this->actingAs($user);

    visit(route('products.edit', [
        'current_organization' => $organization->slug,
        'product' => $product->id,
    ]))
        ->assertSee('Still needed')
        /** The report has been filed, so only the certificate is offered. */
        ->assertMissing('@document-kind-test_report')
        ->assertPresent('@document-kind-certificate')
        /** And the warning text is typed into the form, not filed here. */
        ->assertMissing('@document-kind-warning_text')
        ->assertSee('Drag a file here')
        /** Nothing is chosen yet, so there is nothing to upload. */
        ->assertButtonDisabled('@upload-document-submit')
        ->assertMissing('@document-chosen-file')
        /** One click names the kind the checklist was asking for. */
        ->click('@document-kind-certificate')
        ->assertSeeIn('@document-type', 'Certificate')
        ->assertButtonDisabled('@upload-document-submit')
        ->assertNoJavaScriptErrors();
});

test('a member sees the documents without the upload and delete controls', function () {
    [$user, $organization] = newOrganizationMember(OrganizationRole::Member);
    $connection = newSupplierConnection($organization);

    $product = Product::factory()->for($organization)->create([
        'supplier_connection_id' => $connection->id,
    ]);

    ProductDocument::factory()
        ->for($product)
        ->ofType(ProductDocumentType::Certificate)
        ->create(['name' => 'ce-marking.pdf']);

    $this->actingAs($user);

    visit(route('products.edit', [
        'current_organization' => $organization->slug,
        'product' => $product->id,
    ]))
        ->assertSee('ce-marking.pdf')
        ->assertMissing('@document-file')
        ->assertMissing('@product-document-delete-button')
        ->assertNoJavaScriptErrors();
});
