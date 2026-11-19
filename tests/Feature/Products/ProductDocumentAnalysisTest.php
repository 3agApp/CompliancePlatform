<?php

use App\Actions\Products\AssessProductDocuments;
use App\Ai\Agents\SingleDocumentAnalysisAgent;
use App\Enums\AiProvider;
use App\Enums\AssessmentOverall;
use App\Enums\AssessmentStatus;
use App\Enums\FindingSeverity;
use App\Enums\Locale;
use App\Enums\OrganizationRole;
use App\Enums\ProductDocumentType;
use App\Enums\ProductEventType;
use App\Enums\ProductReviewStatus;
use App\Jobs\RunProductDocumentAnalysis;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductDocument;
use App\Models\ProductDocumentAnalysis;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Laravel\Ai\Files\StoredDocument;
use Laravel\Ai\Prompts\AgentPrompt;

beforeEach(function () {
    Storage::fake(ProductDocument::DISK);
});

/**
 * Create a distributor reviewer whose organization has agreed to have its
 * documents read, with one PDF test report filed against a product.
 *
 * @return array{0: User, 1: Organization, 2: Product, 3: ProductDocument}
 */
function reviewerWithAnalyzableDocument(OrganizationRole $role = OrganizationRole::Owner, bool $allowed = true, string $mimeType = 'application/pdf', string $name = 'EN71-3 report.pdf'): array
{
    [$user, $organization] = newOrganizationMember($role);

    $product = Product::factory()->for($organization)->create([
        'name' => 'Wooden Stacking Rings',
        'supplier_connection_id' => newSupplierConnection($organization)->id,
        'review_status' => ProductReviewStatus::InReview,
    ]);

    $organization->aiSetting()->create([
        'provider' => AiProvider::Gemini,
        'model' => AiProvider::Gemini->defaultModel(),
        'api_key' => 'AIzaSyD-DocumentAnalysisKeyForTests-0123',
        'allow_document_analysis' => $allowed,
    ]);

    $document = ProductDocument::factory()->for($product)->ofType(ProductDocumentType::TestReport)->create([
        'name' => $name,
        'mime_type' => $mimeType,
        'size' => 2048,
    ]);

    Storage::disk(ProductDocument::DISK)->put($document->path, 'contents of '.$name);

    return [$user, $organization, $product, $document];
}

/**
 * Ask for a reading of one paper.
 */
function startDocumentAnalysis(Organization $organization, Product $product, ProductDocument $document): TestResponse
{
    return test()->post(route('products.documents.analyses.store', [
        'current_organization' => $organization->slug,
        'product' => $product->id,
        'document' => $document->id,
    ]));
}

/**
 * A well-formed answer about one test report.
 *
 * @param  array<string, mixed>  $facts
 * @param  array<int, array<string, mixed>>|null  $findings
 * @return array<string, mixed>
 */
function documentAnalysisAnswer(array $facts = [], ?array $findings = null): array
{
    return [
        'summary' => 'An EN 71-3 report from a named laboratory; the sample has no article number.',
        'overall' => 'gaps_found',
        'facts' => [
            'issuer' => 'Example Testing Laboratory GmbH',
            'document_number' => 'TR-2026-0142',
            'issued_on' => '2026-03-04',
            'expires_on' => '',
            'standards' => ['EN 71-3:2019+A1:2021', '', 'EN 71-3:2019+A1:2021'],
            'covered_product' => 'Wooden stacking rings',
            'type_matches' => true,
            'suggested_type' => 'unknown',
            ...$facts,
        ],
        'findings' => $findings ?? [[
            'severity' => 'major',
            'category' => 'mismatch_with_product',
            'requirement' => 'Test report: sample identification',
            'rationale' => 'The sample is described by name only.',
            'evidence' => 'Page 1: "Sample: stacking toy"',
            'ask_manufacturer' => 'Send a report naming the article number.',
        ]],
    ];
}

test('a reviewer can ask for one document to be read, and the run is queued', function () {
    Queue::fake();

    [$user, $organization, $product, $document] = reviewerWithAnalyzableDocument();

    $this->actingAs($user);

    startDocumentAnalysis($organization, $product, $document)->assertRedirect()->assertSessionHasNoErrors();

    $analysis = $document->analyses()->sole();

    expect($analysis->status)->toBe(AssessmentStatus::Queued)
        ->and($analysis->requested_by)->toBe($user->id)
        ->and($analysis->prompt_version)->toBe(SingleDocumentAnalysisAgent::PROMPT_VERSION)
        ->and($product->events()->where('type', ProductEventType::DocumentAnalysisRequested)->exists())->toBeTrue();

    Queue::assertPushed(RunProductDocumentAnalysis::class, fn (RunProductDocumentAnalysis $job): bool => $job->analysis->is($analysis));
});

test('the run sends only that document and records the verdict, facts and findings', function () {
    [$user, $organization, $product, $document] = reviewerWithAnalyzableDocument();

    ProductDocument::factory()->for($product)->create(['name' => 'other.pdf']);

    SingleDocumentAnalysisAgent::fake([documentAnalysisAnswer()])->preventStrayPrompts();

    $this->actingAs($user);

    startDocumentAnalysis($organization, $product, $document)->assertSessionHasNoErrors();

    $analysis = $document->analyses()->sole();
    $finding = $analysis->findings()->sole();

    expect($analysis->status)->toBe(AssessmentStatus::Completed)
        ->and($analysis->overall)->toBe(AssessmentOverall::GapsFound)
        ->and($analysis->facts)->toBe([
            'issuer' => 'Example Testing Laboratory GmbH',
            'document_number' => 'TR-2026-0142',
            'issued_on' => '2026-03-04',
            'expires_on' => null,
            'standards' => ['EN 71-3:2019+A1:2021'],
            'covered_product' => 'Wooden stacking rings',
            'type_matches' => true,
            'suggested_type' => null,
        ])
        ->and($finding->severity)->toBe(FindingSeverity::Major)
        ->and($product->events()->where('type', ProductEventType::DocumentAnalysisCompleted)->exists())->toBeTrue();

    SingleDocumentAnalysisAgent::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->attachments->count() === 1
        && $prompt->attachments[0] instanceof StoredDocument
        && $prompt->contains('Wooden Stacking Rings')
        && $prompt->contains('EN71-3 report.pdf')
        && $prompt->contains('test_report')
        && ! $prompt->contains('other.pdf'));
});

test('made-up enums, impossible dates and a bogus document type are dropped', function () {
    [$user, $organization, $product, $document] = reviewerWithAnalyzableDocument();

    SingleDocumentAnalysisAgent::fake([documentAnalysisAnswer(
        facts: ['issued_on' => '2026-02-31', 'expires_on' => 'next year', 'type_matches' => false, 'suggested_type' => 'invoice'],
        findings: [
            ['severity' => 'catastrophic', 'category' => 'incomplete', 'requirement' => 'x', 'rationale' => 'y', 'evidence' => '', 'ask_manufacturer' => ''],
            ['severity' => 'minor', 'category' => 'vibes', 'requirement' => 'x', 'rationale' => 'y', 'evidence' => '', 'ask_manufacturer' => ''],
        ],
    )]);

    $this->actingAs($user);

    startDocumentAnalysis($organization, $product, $document);

    $analysis = $document->analyses()->sole();

    expect($analysis->findings()->count())->toBe(0)
        ->and($analysis->facts['issued_on'])->toBeNull()
        ->and($analysis->facts['expires_on'])->toBeNull()
        ->and($analysis->facts['type_matches'])->toBeFalse()
        ->and($analysis->facts['suggested_type'])->toBeNull();
});

test('a document filed as the wrong kind says what it looks like instead', function () {
    [$user, $organization, $product, $document] = reviewerWithAnalyzableDocument();

    SingleDocumentAnalysisAgent::fake([documentAnalysisAnswer(facts: ['type_matches' => false, 'suggested_type' => 'certificate'])]);

    $this->actingAs($user);

    startDocumentAnalysis($organization, $product, $document);

    expect($document->analyses()->sole()->facts)
        ->type_matches->toBeFalse()
        ->suggested_type->toBe(ProductDocumentType::Certificate->value);
});

test('a provider that will not answer leaves a failed run with a reason', function () {
    [$user, $organization, $product, $document] = reviewerWithAnalyzableDocument();

    SingleDocumentAnalysisAgent::fake(function (): never {
        throw new RuntimeException('Gemini is down');
    });

    $this->actingAs($user);

    startDocumentAnalysis($organization, $product, $document)->assertSessionHasNoErrors();

    expect($document->analyses()->sole())
        ->status->toBe(AssessmentStatus::Failed)
        ->failure_reason->not->toBeNull();
});

test('a reading never moves the review', function () {
    [$user, $organization, $product, $document] = reviewerWithAnalyzableDocument();

    SingleDocumentAnalysisAgent::fake([documentAnalysisAnswer()]);

    $this->actingAs($user);

    startDocumentAnalysis($organization, $product, $document);

    expect($product->fresh()->review_status)->toBe(ProductReviewStatus::InReview);
});

test('a run is refused when the organization has not allowed document analysis', function () {
    Queue::fake();

    [$user, $organization, $product, $document] = reviewerWithAnalyzableDocument(allowed: false);

    $this->actingAs($user);

    startDocumentAnalysis($organization, $product, $document)->assertSessionHasErrors('analysis');

    expect($document->analyses()->exists())->toBeFalse();
    Queue::assertNothingPushed();
});

test('a run is refused when the organization has no AI provider', function () {
    Queue::fake();

    [$user, $organization, $product, $document] = reviewerWithAnalyzableDocument();
    $organization->aiSetting()->delete();

    $this->actingAs($user);

    startDocumentAnalysis($organization, $product, $document)->assertSessionHasErrors('analysis');

    Queue::assertNothingPushed();
});

test('a Word file is refused, because the providers do not read one', function () {
    Queue::fake();

    [$user, $organization, $product, $document] = reviewerWithAnalyzableDocument(
        mimeType: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        name: 'manual.docx',
    );

    $this->actingAs($user);

    startDocumentAnalysis($organization, $product, $document)->assertSessionHasErrors('analysis');

    Queue::assertNothingPushed();
});

test('a second run is refused while one is still going', function () {
    Queue::fake();

    [$user, $organization, $product, $document] = reviewerWithAnalyzableDocument();

    ProductDocumentAnalysis::factory()->for($document, 'document')->for($organization)->create(['status' => AssessmentStatus::Running]);

    $this->actingAs($user);

    startDocumentAnalysis($organization, $product, $document)->assertSessionHasErrors('analysis');

    expect($document->analyses()->count())->toBe(1);
});

test('a run lost by the queue does not block the next one forever', function () {
    Queue::fake();

    [$user, $organization, $product, $document] = reviewerWithAnalyzableDocument();

    $lost = ProductDocumentAnalysis::factory()->for($document, 'document')->for($organization)->create([
        'status' => AssessmentStatus::Queued,
        'created_at' => now()->subMinutes(AssessProductDocuments::STALE_AFTER_MINUTES + 1),
    ]);

    $this->actingAs($user);

    startDocumentAnalysis($organization, $product, $document)->assertSessionHasNoErrors();

    expect($lost->fresh()->status)->toBe(AssessmentStatus::Failed)
        ->and($document->analyses()->count())->toBe(2);
});

test('a member may not ask for a run', function () {
    [$user, $organization, $product, $document] = reviewerWithAnalyzableDocument(OrganizationRole::Member);

    $this->actingAs($user);

    startDocumentAnalysis($organization, $product, $document)->assertForbidden();
});

test('the supplier may not ask for a run or see one', function () {
    [, $distributor, $product, $document] = reviewerWithAnalyzableDocument();

    [$supplierUser, $supplier] = newSupplierMember();
    $product->update(['supplier_connection_id' => newSupplierConnection($distributor, $supplier)->id]);

    ProductDocumentAnalysis::factory()->for($document, 'document')->for($distributor)->completed()->create();

    $this->actingAs($supplierUser);

    startDocumentAnalysis($supplier, $product, $document)->assertForbidden();

    $this->get(route('products.edit', ['current_organization' => $supplier->slug, 'product' => $product->id]))
        ->assertInertia(fn ($page) => $page->where('documentAnalyses', null));
});

test('a document of another product cannot be analyzed through this one', function () {
    [$user, $organization, $product] = reviewerWithAnalyzableDocument();
    [, , , $elsewhere] = reviewerWithAnalyzableDocument();

    $this->actingAs($user);

    startDocumentAnalysis($organization, $product, $elsewhere)->assertNotFound();
});

test('the edit page gives the reviewer the latest reading of each document', function () {
    [$user, $organization, $product, $document] = reviewerWithAnalyzableDocument();

    ProductDocumentAnalysis::factory()->for($document, 'document')->for($organization)->failed()->create();
    $latest = ProductDocumentAnalysis::factory()->for($document, 'document')->for($organization)->completed()->hasFindings(1)->create();

    $this->actingAs($user);

    $this->get(route('products.edit', ['current_organization' => $organization->slug, 'product' => $product->id]))
        ->assertInertia(fn ($page) => $page
            ->where('product.documents.0.analyzable', true)
            ->where("documentAnalyses.{$document->id}.id", $latest->id)
            ->where("documentAnalyses.{$document->id}.overall", 'gaps_found')
            ->where("documentAnalyses.{$document->id}.facts.issuer", 'Example Testing Laboratory GmbH')
            ->has("documentAnalyses.{$document->id}.findings", 1));
});

test('removing a document removes its readings', function () {
    [, $organization, , $document] = reviewerWithAnalyzableDocument();

    $analysis = ProductDocumentAnalysis::factory()->for($document, 'document')->for($organization)->completed()->hasFindings(1)->create();

    $document->delete();

    expect(ProductDocumentAnalysis::query()->whereKey($analysis->id)->exists())->toBeFalse();
});

test('a reading the worker gave up on is marked failed, in the organization language', function () {
    [, $organization, , $document] = reviewerWithAnalyzableDocument();
    $organization->update(['locale' => Locale::German]);

    $analysis = ProductDocumentAnalysis::factory()->for($document, 'document')->for($organization)->create(['status' => AssessmentStatus::Running]);

    app()->setLocale('en');

    (new RunProductDocumentAnalysis($analysis))->failed(new RuntimeException('Timed out'));

    expect($analysis->fresh())
        ->status->toBe(AssessmentStatus::Failed)
        ->failure_reason->toBe('Die Analyse hat zu lange gedauert und wurde abgebrochen. Versuchen Sie es erneut.');
});

test('a reading that already finished is left alone when the worker reports a failure', function () {
    [, $organization, , $document] = reviewerWithAnalyzableDocument();

    $analysis = ProductDocumentAnalysis::factory()->for($document, 'document')->for($organization)->completed()->create();

    (new RunProductDocumentAnalysis($analysis))->failed(new RuntimeException('Late'));

    expect($analysis->fresh()->status)->toBe(AssessmentStatus::Completed);
});
