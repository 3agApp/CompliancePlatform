<?php

use App\Enums\ProductDocumentType;
use App\Enums\ProductRequirement;
use App\Enums\ProductReviewStatus;
use App\Enums\ProductSealStatus;
use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductDocument;
use App\Models\ProductTemplate;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

/**
 * A one pixel PNG, written where the document row says its file is.
 *
 * The public page draws the picture, so there has to be one: a row pointing
 * at nothing renders a broken frame and the test would pass on it.
 */
function aStoredImage(Product $product, string $name = 'front.png', ProductDocumentType $type = ProductDocumentType::ProductImage): ProductDocument
{
    $path = ProductDocument::directoryFor($product).'/'.$name;

    Storage::disk(ProductDocument::DISK)->put($path, base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
    ));

    return ProductDocument::factory()
        ->for($product)
        ->ofType($type)
        ->create(['name' => $name, 'path' => $path, 'mime_type' => 'image/png', 'is_public' => true]);
}

/**
 * A product held to a template that asks for something, so the score on its
 * seal is a real reading rather than the hundred an empty template scores.
 */
function publishableProduct(array $attributes = [], ProductReviewStatus $status = ProductReviewStatus::Draft): Product
{
    [, $organization] = newOrganizationMember();
    $connection = newSupplierConnection($organization);

    $template = ProductTemplate::factory()
        ->requiring(ProductRequirement::Ean, ProductRequirement::WarningText, ProductRequirement::TestReport)
        ->create(['product_category_id' => legalFamily($organization)->id]);

    return Product::factory()
        ->for($organization)
        ->usingTemplate($template)
        ->withoutOptionalDetails()
        ->reviewed($status)
        ->create([
            'name' => 'Magnetic Building Set',
            'supplier_connection_id' => $connection->id,
            'brand_id' => Brand::factory()->for($connection, 'supplierConnection')->create(['name' => 'Brio'])->id,
            ...$attributes,
        ]);
}

test('the public page is the article, its numbers and an unfinished check', function () {
    Storage::fake(ProductDocument::DISK);

    $product = publishableProduct([
        'ean' => '7612345678900',
        'internal_article_number' => 'ART-4711',
    ]);

    aStoredImage($product);

    $page = visit(route('products.public', ['product' => $product->uuid]));

    $page->assertSee('Magnetic Building Set')
        ->assertSee('Brio')
        ->assertSee('7612345678900')
        ->assertSee('ART-4711')
        ->assertPresent('@product-photo')
        /** The check is running, so the seal is amber and carries the bar. */
        ->assertSee('In progress')
        ->assertSee('The compliance check has not finished yet.')
        ->assertPresent('@product-seal-bar')
        ->assertSee('20%')
        /** And none of the trade behind it is on the page. */
        ->assertDontSee('Supplier')
        ->assertDontSee('Requirements')
        ->assertNoJavaScriptErrors();
});

test('an approved product carries a verified seal with the day it passed', function () {
    $product = publishableProduct(status: ProductReviewStatus::Approved);

    $page = visit(route('products.public', ['product' => $product->uuid]));

    $page->assertSee('Verified')
        ->assertSee('This product meets Swiss compliance requirements.')
        ->assertPresent('@product-seal-approved-at')
        ->assertSee('Approved')
        /** A passed check has nothing left to be a percentage of. */
        ->assertMissing('@product-seal-bar')
        ->assertNoJavaScriptErrors();
});

test('a product nobody has started reads as not verified, with no bar', function () {
    $product = publishableProduct();

    $page = visit(route('products.public', ['product' => $product->uuid]));

    $page->assertSee('Not verified')
        ->assertSee('No completed compliance check yet.')
        ->assertMissing('@product-seal-bar')
        ->assertSee('No picture of this article yet')
        ->assertNoJavaScriptErrors();
});

test('a hand-set seal is shown as set rather than earned', function () {
    $product = publishableProduct();

    $product->forceFill([
        'seal_override' => ProductSealStatus::Verified,
        'seal_override_reason' => 'Certified under the previous article number.',
        'seal_overridden_at' => now(),
    ])->save();

    $page = visit(route('products.public', ['product' => $product->uuid]));

    $page->assertSee('Verified')
        ->assertSee('Set by the distributor rather than by a completed check.')
        /** Nothing approved it, so there is no date to claim. */
        ->assertMissing('@product-seal-approved-at')
        ->assertNoJavaScriptErrors();
});

test('a distributor sets the public seal by hand from the product page', function () {
    [$user, $organization] = newOrganizationMember();
    $connection = newSupplierConnection($organization);

    $product = Product::factory()->for($organization)->create([
        'name' => 'Magnetic Building Set',
        'supplier_connection_id' => $connection->id,
    ]);

    $this->actingAs($user);

    $page = visit(route('products.edit', [
        'current_organization' => $organization->slug,
        'product' => $product->id,
        'tab' => 'public',
    ]));

    $page->assertSee('Public page')
        ->assertPresent('@product-public-url')
        ->click('@product-override-seal')
        ->assertSee('The seal normally follows this product')
        ->click('@seal-choice')
        ->click('@seal-option-verified')
        ->fill('@seal-reason', 'Certified under the previous article number.')
        ->click('@seal-confirm')
        ->assertSee('Public seal updated.')
        ->assertSee('Set by the distributor rather than by a completed check.')
        ->assertNoJavaScriptErrors();

    expect($product->fresh()->seal_override)->toBe(ProductSealStatus::Verified);
});

test('a distributor releases a test report to the public page', function () {
    Storage::fake(ProductDocument::DISK);

    [$user, $organization] = newOrganizationMember();

    $product = Product::factory()->for($organization)->create([
        'name' => 'Magnetic Building Set',
        'supplier_connection_id' => newSupplierConnection($organization)->id,
        'warning_text' => 'Not suitable for children under 3 years.',
    ]);

    $path = ProductDocument::directoryFor($product).'/report.pdf';
    Storage::disk(ProductDocument::DISK)->put($path, '%PDF-1.4 report');

    $document = ProductDocument::factory()->for($product)->ofType(ProductDocumentType::TestReport)->create([
        'name' => 'report.pdf',
        'path' => $path,
        'mime_type' => 'application/pdf',
    ]);

    $this->actingAs($user);

    visit(route('products.edit', ['current_organization' => $organization->slug, 'product' => $product->id, 'tab' => 'documents']))
        ->click('@product-document-visibility-button')
        ->assertPresent('@product-document-public-badge')
        ->click('@product-tab-history')
        ->assertSee('Document released to the public page')
        ->assertNoJavaScriptErrors();

    expect($document->fresh()->is_public)->toBeTrue();
});

test('the public page shows released documents grouped by kind, safety information and who places the product on the market', function () {
    Storage::fake(ProductDocument::DISK);

    $product = publishableProduct(['warning_text' => 'Not suitable for children under 3 years.']);

    foreach (['scan_0042.pdf', 'scan_0043.pdf'] as $name) {
        ProductDocument::factory()->for($product)->ofType(ProductDocumentType::Certificate)->create([
            'name' => $name,
            'mime_type' => 'application/pdf',
            'is_public' => true,
        ]);
    }

    ProductDocument::factory()->for($product)->ofType(ProductDocumentType::TestReport)->create([
        'name' => 'test-report.pdf',
        'title' => 'EN 71-1 report',
        'mime_type' => 'application/pdf',
        'is_public' => true,
    ]);

    visit($product->publicUrl())
        ->assertSee('Safety information')
        ->assertSee('Not suitable for children under 3 years.')
        ->assertCount('@product-public-document-group', 2)
        ->assertSeeIn('[data-type="certificate"]', 'Certificate 1')
        ->assertSeeIn('[data-type="certificate"]', 'Certificate 2')
        ->assertDontSee('scan_0042.pdf')
        ->assertSeeIn('[data-type="test_report"]', 'EN 71-1 report')
        ->assertDontSee('test-report.pdf')
        ->assertMissing('@product-public-document-download')
        ->assertSee('Placed on the market by')
        ->assertSee($product->organization->name)
        ->assertNoJavaScriptErrors();
});

test('the company placing the product on the market opens with its details', function () {
    $product = publishableProduct();
    $product->organization->update([
        'name' => 'Spielwaren AG',
        'street' => 'Tramstrasse 66',
        'postal_code' => '4142',
        'city' => 'Münchenstein',
        'country' => 'CH',
        'phone' => '+41 61 683 47 57',
        'email' => 'info@example.ch',
        'vat_number' => 'CHE-103.107.876',
        'map_url' => 'https://maps.app.goo.gl/abc123XYZ',
    ]);

    visit($product->publicUrl())
        ->assertSeeIn('@product-importer', 'Spielwaren AG')
        ->assertSeeIn('@product-importer', 'Münchenstein, Switzerland')
        ->click('@product-importer-details')
        ->assertSeeIn('@product-importer-dialog', 'Tramstrasse 66')
        ->assertSeeIn('@product-importer-dialog', '4142 Münchenstein')
        ->assertSeeIn('@product-importer-dialog', '+41 61 683 47 57')
        ->assertSeeIn('@product-importer-dialog', 'info@example.ch')
        ->assertSeeIn('@product-importer-dialog', 'CHE-103.107.876')
        ->assertAttribute('@product-importer-map', 'href', 'https://maps.app.goo.gl/abc123XYZ')
        ->keys('@product-importer-dialog', 'Escape')
        ->assertMissing('@product-importer-dialog')
        ->assertNoJavaScriptErrors();
});

test('a company that published only its name gets no details dialog', function () {
    $product = publishableProduct();

    visit($product->publicUrl())
        ->assertSeeIn('@product-importer', $product->organization->name)
        ->assertMissing('@product-importer-details')
        ->assertNoJavaScriptErrors();
});

/**
 * A picture whose file cannot be loaded is dropped rather than left as an
 * empty frame at the top of the page.
 */
test('a product picture that cannot be loaded falls back to the no picture panel', function () {
    Storage::fake(ProductDocument::DISK);

    $product = publishableProduct();

    ProductDocument::factory()
        ->for($product)
        ->ofType(ProductDocumentType::ProductImage)
        ->create(['name' => 'gone.png', 'path' => 'missing/gone.png', 'mime_type' => 'image/png']);

    visit(route('products.public', ['product' => $product->uuid]))
        ->assertMissing('@product-photo')
        ->assertPresent('@product-photo-missing');
});

test('a warning image is shown inside the warning, not among the downloads', function () {
    Storage::fake(ProductDocument::DISK);

    $product = publishableProduct(['warning_text' => 'Not suitable for children under 3 years.']);
    aStoredImage($product, 'under-3.png', ProductDocumentType::SafetyImage);

    visit(route('products.public', ['product' => $product->uuid]))
        ->assertSeeIn('@product-public-warning', 'Not suitable for children under 3 years.')
        ->assertCount('[data-test="product-public-warning"] [data-test="product-public-warning-image"] img', 1)
        ->assertMissing('@product-public-documents')
        ->assertNoJavaScriptErrors();
});

test('the public page shows the product photo watermarked and leaves a released Word file off', function () {
    Storage::fake(ProductDocument::DISK);

    $product = publishableProduct();
    aStoredImage($product);

    $manual = ProductDocument::factory()->for($product)->ofType(ProductDocumentType::ManualOrInstructions)->create([
        'name' => 'manual.docx',
        'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ]);
    $manual->forceFill(['is_public' => true])->save();

    visit(route('products.public', ['product' => $product->uuid]))
        ->assertPresent('@product-photo')
        ->assertScript('document.querySelector(\'[data-test="product-photo"]\').complete && document.querySelector(\'[data-test="product-photo"]\').naturalWidth > 0')
        ->assertMissing('@product-public-documents')
        ->assertDontSee('manual.docx')
        ->assertNoJavaScriptErrors();

    expect(Storage::disk(ProductDocument::DISK)->files(ProductDocument::directoryFor($product).'/watermarked'))->toHaveCount(1);
});

test('a distributor cannot release a Word file to the public page', function () {
    Storage::fake(ProductDocument::DISK);

    [$user, $organization] = newOrganizationMember();

    $product = Product::factory()->for($organization)->create([
        'supplier_connection_id' => newSupplierConnection($organization)->id,
    ]);

    $manual = ProductDocument::factory()->for($product)->ofType(ProductDocumentType::ManualOrInstructions)->create([
        'name' => 'manual.docx',
        'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ]);

    $this->actingAs($user);

    visit(route('products.edit', ['current_organization' => $organization->slug, 'product' => $product->id, 'tab' => 'documents']))
        ->assertScript('document.querySelector(\'[data-test="product-document-visibility-button"]\').disabled')
        ->assertAttribute('@product-document-visibility-button', 'title', 'Only PDFs and images can be shown on the public page.')
        ->assertNoJavaScriptErrors();

    expect($manual->fresh()->is_public)->toBeFalse();
});

test('a reader enlarges the product photo on the public page', function () {
    Storage::fake(ProductDocument::DISK);

    $product = publishableProduct();
    aStoredImage($product);

    visit(route('products.public', ['product' => $product->uuid]))
        ->click('@product-photo-enlarge')
        ->assertVisible('@public-preview-dialog')
        ->assertPresent('@public-preview-image')
        ->assertMissing('@public-preview-download')
        ->assertNoJavaScriptErrors();
});

test('a reader previews a released PDF on the public page, with nothing to download', function () {
    Storage::fake(ProductDocument::DISK);

    $product = publishableProduct();
    $path = ProductDocument::directoryFor($product).'/report.pdf';
    Storage::disk(ProductDocument::DISK)->put($path, Pdf::loadHTML('<h1>Test report</h1>')->output());

    $report = ProductDocument::factory()->for($product)->ofType(ProductDocumentType::TestReport)->create([
        'name' => 'test-report.pdf',
        'title' => 'EN 71-1 report',
        'path' => $path,
        'mime_type' => 'application/pdf',
        'is_public' => true,
    ]);

    visit(route('products.public', ['product' => $product->uuid]))
        ->click('@product-public-document-preview')
        ->assertVisible('@public-preview-dialog')
        ->assertSeeIn('[data-test="public-preview-dialog"] h2', 'EN 71-1 report')
        ->assertAttribute('@public-preview-pdf', 'data', route('products.public.document', ['product' => $product->uuid, 'document' => $report->id]).'#toolbar=0&navpanes=0')
        ->assertMissing('@public-preview-download')
        ->assertNoJavaScriptErrors();
});

test('a scanned certificate released as a picture opens in the image viewer', function () {
    Storage::fake(ProductDocument::DISK);

    $product = publishableProduct();
    aStoredImage($product, 'IMG_2041.png', ProductDocumentType::Certificate);

    visit(route('products.public', ['product' => $product->uuid]))
        ->assertSeeIn('@product-public-document-name', 'Certificate')
        ->assertDontSee('IMG_2041.png')
        ->click('@product-public-document-preview')
        ->assertVisible('@public-preview-dialog')
        ->assertPresent('@public-preview-image')
        ->assertNoJavaScriptErrors();
});

test('the brand logo is shown beside the brand name', function () {
    Storage::fake(ProductDocument::DISK);

    $product = publishableProduct();
    $brand = Brand::query()->findOrFail($product->brand_id);
    $path = $brand->logoDirectory().'/logo.png';
    Storage::disk(ProductDocument::DISK)->put($path, base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
    ));
    $brand->forceFill(['logo_path' => $path])->save();

    visit($product->publicUrl())
        ->assertPresent('@product-brand-logo')
        ->assertScript('document.querySelector(\'[data-test="product-brand-logo"]\').naturalWidth', 1)
        ->assertNoJavaScriptErrors();
});
