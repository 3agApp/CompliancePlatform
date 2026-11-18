<?php

use App\Enums\AiProvider;
use App\Enums\AssessmentOverall;
use App\Enums\FindingSeverity;
use App\Enums\ProductDocumentType;
use App\Enums\ProductReviewStatus;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductAssessment;
use App\Models\ProductDocument;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake(ProductDocument::DISK);
});

/**
 * A distributor reviewer whose organization lets its documents be read,
 * with one test report filed against the product.
 *
 * @return array{0: User, 1: Organization, 2: Product, 3: ProductDocument}
 */
function productWithCheckableReport(): array
{
    [$user, $organization] = newOrganizationMember();

    $product = Product::factory()->for($organization)->create([
        'supplier_connection_id' => newSupplierConnection($organization)->id,
        'review_status' => ProductReviewStatus::InReview,
    ]);

    $organization->aiSetting()->create([
        'provider' => AiProvider::Gemini,
        'model' => AiProvider::Gemini->defaultModel(),
        'api_key' => 'AIzaSyD-BrowserKeyForTests-0123456789',
        'allow_document_analysis' => true,
    ]);

    $report = ProductDocument::factory()->for($product)->ofType(ProductDocumentType::TestReport)->create([
        'name' => 'EN71-3.pdf',
        'mime_type' => 'application/pdf',
    ]);

    return [$user, $organization, $product, $report];
}

test('a paper checked on its own shows its result from its row', function () {
    [$user, $organization, $product, $report] = productWithCheckableReport();

    ProductAssessment::factory()->for($product)->for($organization)->completed()
        ->hasFindings(1, [
            'severity' => FindingSeverity::Major,
            'requirement' => 'EN 71-3 report date',
            'rationale' => 'The report is older than the current edition of the standard.',
        ])
        ->create([
            'product_document_id' => $report->id,
            'overall' => AssessmentOverall::GapsFound,
        ]);

    $this->actingAs($user);

    visit(route('products.edit', ['current_organization' => $organization->slug, 'product' => $product->id, 'tab' => 'documents']))
        ->assertVisible('@product-document-assess-button')
        ->click('@product-document-assessment-badge')
        ->assertVisible('@document-assessment-modal')
        ->assertSee('Only EN71-3.pdf was read, on its own.')
        ->assertSee('EN 71-3 report date')
        ->assertSee('The report is older than the current edition of the standard.')
        ->assertNoJavaScriptErrors();
});

test('a Word file has no AI check of its own to offer', function () {
    [$user, $organization, $product, $report] = productWithCheckableReport();
    $report->forceFill(['name' => 'manual.docx', 'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'])->save();

    $this->actingAs($user);

    visit(route('products.edit', ['current_organization' => $organization->slug, 'product' => $product->id, 'tab' => 'documents']))
        ->assertSee('manual.docx')
        ->assertMissing('@product-document-assess-button')
        ->assertNoJavaScriptErrors();
});

test('a packaging close-up sits in the gallery with the other pictures', function () {
    [$user, $organization, $product] = productWithCheckableReport();

    $path = ProductDocument::directoryFor($product).'/back-panel.png';
    Storage::disk(ProductDocument::DISK)->put($path, base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
    ));
    ProductDocument::factory()->for($product)->ofType(ProductDocumentType::PackagingPhoto)->create([
        'name' => 'back-panel.png',
        'path' => $path,
        'mime_type' => 'image/png',
    ]);

    $this->actingAs($user);

    visit(route('products.edit', ['current_organization' => $organization->slug, 'product' => $product->id, 'tab' => 'documents']))
        ->assertSee('back-panel.png')
        ->assertSee('Packaging close-up')
        ->assertNoJavaScriptErrors();
});
