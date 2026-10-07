<?php

use App\Ai\Agents\DocumentKindAgent;
use App\Enums\AiProvider;
use App\Enums\OrganizationRole;
use App\Enums\ProductDocumentType;
use App\Enums\ProductRequirement;
use App\Models\Product;
use App\Models\ProductDocument;
use App\Models\ProductTemplate;

/**
 * Write a real PDF somewhere the browser can pick it up from.
 *
 * The bytes never matter -- the suggestion call sends the name, the type and
 * the size -- but the file has to exist for the picker to take it.
 */
function aPdfNamed(string $name): string
{
    $path = sys_get_temp_dir().'/pest-documents/'.$name;

    @mkdir(dirname($path), 0777, true);
    file_put_contents($path, "%PDF-1.4\n% a file with a name and a size\n");

    return $path;
}

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
        /** Unanswered and not asked for, the long answers wait behind a button each. */
        ->assertMissing('@product-warning-text')
        ->click('@product-add-safety-notice')
        ->click('@product-add-warning-text')
        ->click('@product-add-material-information')
        ->click('@product-add-usage-restrictions')
        ->click('@product-add-safety-instructions')
        ->click('@product-add-additional-notes')
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
        'tab' => 'documents',
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
        'tab' => 'documents',
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
        'tab' => 'documents',
    ]))
        ->assertSee('Still needed')
        /** The report has been filed, so only the certificate is offered. */
        ->assertMissing('@document-kind-test_report')
        ->assertPresent('@document-kind-certificate')
        /** And the warning text is typed into the form, not filed here. */
        ->assertMissing('@document-kind-warning_text')
        ->assertMissing('@upload-documents-modal')
        /** The slot is a way into the dialog. */
        ->click('@document-kind-certificate')
        ->assertPresent('@upload-documents-modal')
        ->assertSee('Drag files here')
        /** Nothing is chosen yet, so there is nothing to upload. */
        ->assertButtonDisabled('@upload-document-submit')
        ->assertMissing('@document-review-table')
        /**
         * A file chosen from the certificate's slot is filed as a
         * certificate without being asked, so it is ready to go.
         */
        ->attach('@document-file', aPdfNamed('en71-certificate.pdf'))
        ->assertSeeIn('@pending-document-type-0', 'Certificate')
        ->assertButtonEnabled('@upload-document-submit')
        /** And it closes again without filing anything. */
        ->click('@upload-documents-cancel')
        ->assertMissing('@upload-documents-modal')
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
        'tab' => 'documents',
    ]))
        ->assertSee('ce-marking.pdf')
        ->assertMissing('@upload-documents-button')
        ->assertMissing('@document-file')
        ->assertMissing('@product-document-delete-button')
        ->assertNoJavaScriptErrors();
});

/**
 * The half of the upload the browser can actually drive.
 *
 * Choosing files, asking what they look like, correcting the answer and
 * being held back until every row has a kind all happen without a file ever
 * being posted -- the suggestion call carries names and sizes as JSON. Only
 * the final submit carries bytes, and that is covered in
 * tests/Feature/Products/ProductDocumentTest.php for the reason given above.
 */
test('the kind of a chosen file is proposed for the person to confirm', function () {
    DocumentKindAgent::fake([['guesses' => [
        ['index' => 0, 'type' => 'declaration_of_conformity', 'confidence' => 'high'],
    ]]]);

    [$user, $organization] = newOrganizationMember();
    $connection = newSupplierConnection($organization);

    $organization->aiSetting()->create([
        'provider' => AiProvider::Gemini,
        'model' => AiProvider::Gemini->defaultModel(),
        'api_key' => 'AIzaSyD-ExampleKeyForTests-0123456789',
    ]);

    $product = Product::factory()->for($organization)->create([
        'supplier_connection_id' => $connection->id,
    ]);

    $this->actingAs($user);

    visit(route('products.edit', [
        'current_organization' => $organization->slug,
        'product' => $product->id,
        'tab' => 'documents',
    ]))
        ->click('@upload-documents-button')
        ->assertButtonDisabled('@upload-document-submit')
        ->attach('@document-file', aPdfNamed('DoC-2026.pdf'))
        ->assertPresent('@document-review-table')
        ->assertSee('DoC-2026.pdf')
        /** Nothing is guessed until it is asked for. */
        ->assertSeeIn('@pending-document-type-0', 'Select a kind')
        ->assertButtonDisabled('@upload-document-submit')
        ->click('@guess-document-kinds-button')
        /** Now the kind is filled in, and says so rather than pretending. */
        ->assertSeeIn('@pending-document-type-0', 'Declaration of conformity')
        ->assertSeeIn('@pending-document-guessed-0', 'Proposed')
        /** With a kind on every row, the batch may go. */
        ->assertButtonEnabled('@upload-document-submit')
        /** And it can be taken back off again. */
        ->click('@pending-document-remove-0')
        ->assertMissing('@document-review-table')
        ->assertButtonDisabled('@upload-document-submit')
        ->assertNoJavaScriptErrors();
});

test('a file the AI cannot place is left for the person to name', function () {
    DocumentKindAgent::fake([['guesses' => [
        ['index' => 0, 'type' => 'unknown', 'confidence' => 'low'],
    ]]]);

    [$user, $organization] = newOrganizationMember();
    $connection = newSupplierConnection($organization);

    $organization->aiSetting()->create([
        'provider' => AiProvider::Gemini,
        'model' => AiProvider::Gemini->defaultModel(),
        'api_key' => 'AIzaSyD-ExampleKeyForTests-0123456789',
    ]);

    $product = Product::factory()->for($organization)->create([
        'supplier_connection_id' => $connection->id,
    ]);

    $this->actingAs($user);

    visit(route('products.edit', [
        'current_organization' => $organization->slug,
        'product' => $product->id,
        'tab' => 'documents',
    ]))
        ->click('@upload-documents-button')
        ->attach('@document-file', aPdfNamed('scan_0012.pdf'))
        ->assertPresent('@document-review-table')
        ->click('@guess-document-kinds-button')
        ->assertSeeIn('@pending-document-unsure-0', 'Not sure')
        /** No kind, so nothing may be filed yet. */
        ->assertButtonDisabled('@upload-document-submit')
        ->assertNoJavaScriptErrors();
});

test('an organization with no AI provider is not offered the guess at all', function () {
    DocumentKindAgent::fake()->preventStrayPrompts();

    [$user, $organization] = newOrganizationMember();
    $connection = newSupplierConnection($organization);

    $product = Product::factory()->for($organization)->create([
        'supplier_connection_id' => $connection->id,
    ]);

    $this->actingAs($user);

    visit(route('products.edit', [
        'current_organization' => $organization->slug,
        'product' => $product->id,
        'tab' => 'documents',
    ]))
        ->click('@upload-documents-button')
        ->attach('@document-file', aPdfNamed('DoC-2026.pdf'))
        ->assertPresent('@document-review-table')
        /*
         * A button whose only answer would be "no provider is configured"
         * is not a button. Filing by hand is what it was before any of this
         * existed, and still is.
         */
        ->assertMissing('@guess-document-kinds-button')
        ->assertSee('DoC-2026.pdf')
        ->assertButtonDisabled('@upload-document-submit')
        ->assertNoJavaScriptErrors();

    DocumentKindAgent::assertNeverPrompted();
});
