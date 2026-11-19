<?php

use App\Ai\Agents\SingleDocumentAnalysisAgent;
use App\Enums\AiProvider;
use App\Enums\AssessmentOverall;
use App\Enums\AssessmentStatus;
use App\Enums\ProductDocumentType;
use App\Enums\ProductReviewStatus;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductDocument;
use App\Models\ProductDocumentAnalysis;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake(ProductDocument::DISK);
});

/**
 * A reviewer whose organization lets the AI read its papers, and one test
 * report on a product in review.
 *
 * @return array{0: User, 1: Organization, 2: Product, 3: ProductDocument}
 */
function browserReviewerWithTestReport(): array
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

    $document = ProductDocument::factory()->for($product)->ofType(ProductDocumentType::TestReport)->create([
        'name' => 'EN71-3 report.pdf',
        'mime_type' => 'application/pdf',
        'size' => 2048,
    ]);

    Storage::disk(ProductDocument::DISK)->put($document->path, '%PDF-1.4');

    return [$user, $organization, $product, $document];
}

/**
 * The reviewer asks for one paper to be read, and the reading opens under
 * the paper with what it says about itself and where it falls short.
 */
test('a reviewer analyzes one document and reads the result under it', function () {
    [$user, $organization, $product] = browserReviewerWithTestReport();

    SingleDocumentAnalysisAgent::fake([[
        'summary' => 'An EN 71-3 report from a named laboratory.',
        'overall' => 'gaps_found',
        'facts' => [
            'issuer' => 'Example Testing Laboratory GmbH',
            'document_number' => 'TR-2026-0142',
            'issued_on' => '2026-03-04',
            'expires_on' => '',
            'standards' => ['EN 71-3:2019+A1:2021'],
            'covered_product' => 'Wooden stacking rings',
            'type_matches' => true,
            'suggested_type' => 'unknown',
        ],
        'findings' => [[
            'severity' => 'major',
            'category' => 'mismatch_with_product',
            'requirement' => 'Test report: sample identification',
            'rationale' => 'The sample is described by name only.',
            'evidence' => '',
            'ask_manufacturer' => 'Send a report naming the article number.',
        ]],
    ]]);

    $this->actingAs($user);

    visit(route('products.edit', ['current_organization' => $organization->slug, 'product' => $product->id, 'tab' => 'documents']))
        ->click('@document-analyze')
        ->assertVisible('@document-analysis-verdict')
        ->assertSee('Gaps found')
        ->click('@document-analysis-verdict')
        ->assertVisible('@document-analysis-facts')
        ->assertSee('Example Testing Laboratory GmbH')
        ->assertSee('EN 71-3:2019+A1:2021')
        ->assertVisible('@document-analysis-finding-major')
        ->assertSee('Test report: sample identification')
        ->assertNoJavaScriptErrors();
});

/**
 * The reading happens on the queue, so the documents tab watches it and
 * shows the verdict by itself once it lands.
 */
test('a reading in progress shows its verdict by itself once it finishes', function () {
    [$user, $organization, $product, $document] = browserReviewerWithTestReport();

    $analysis = ProductDocumentAnalysis::factory()->for($document, 'document')->for($organization)->create();

    $this->actingAs($user);

    $page = visit(route('products.edit', ['current_organization' => $organization->slug, 'product' => $product->id, 'tab' => 'documents']))
        ->assertVisible('@document-analysis-pending');

    $analysis->update([
        'status' => AssessmentStatus::Completed,
        'overall' => AssessmentOverall::NoGapsFound,
        'summary' => 'Nothing to flag.',
        'completed_at' => now(),
    ]);

    $page->wait(6)
        ->assertVisible('@document-analysis-verdict')
        ->assertSee('No gaps found')
        ->assertNoJavaScriptErrors();
});
