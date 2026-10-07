<?php

use App\Actions\Products\AssessProductDocuments;
use App\Ai\Agents\DocumentAssessmentAgent;
use App\Enums\AiProvider;
use App\Enums\AssessmentOverall;
use App\Enums\AssessmentStatus;
use App\Enums\FindingSeverity;
use App\Enums\Locale;
use App\Enums\OrganizationRole;
use App\Enums\ProductDocumentType;
use App\Enums\ProductEventType;
use App\Enums\ProductReviewStatus;
use App\Enums\ProductSealStatus;
use App\Enums\SupplierConnectionStatus;
use App\Jobs\RunProductAssessment;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductAssessment;
use App\Models\ProductDocument;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Laravel\Ai\Files\Image;
use Laravel\Ai\Files\StoredDocument;
use Laravel\Ai\Prompts\AgentPrompt;

const ASSESSMENT_KEY = 'AIzaSyD-AssessmentKeyForTests-0123456789';

beforeEach(function () {
    Storage::fake(ProductDocument::DISK);
});

/**
 * Create a distributor reviewer whose organization has agreed to have its
 * documents read.
 *
 * @return array{0: User, 1: Organization, 2: Product}
 */
function reviewerWithDocumentAnalysis(OrganizationRole $role = OrganizationRole::Owner, bool $allowed = true): array
{
    [$user, $organization] = newOrganizationMember($role);
    $connection = newSupplierConnection($organization);

    $product = Product::factory()->for($organization)->create([
        'name' => 'Magnetic Building Set',
        'supplier_connection_id' => $connection->id,
        'review_status' => ProductReviewStatus::InReview,
        'age_grading' => '3+',
    ]);

    $organization->aiSetting()->create([
        'provider' => AiProvider::Gemini,
        'model' => AiProvider::Gemini->defaultModel(),
        'api_key' => ASSESSMENT_KEY,
        'allow_document_analysis' => $allowed,
    ]);

    return [$user, $organization, $product];
}

/**
 * File a document against the product, with its bytes on the disk.
 */
function fileAssessedDocument(Product $product, string $name, string $mimeType, ProductDocumentType $type): ProductDocument
{
    $document = ProductDocument::factory()->for($product)->ofType($type)->create([
        'name' => $name,
        'mime_type' => $mimeType,
        'size' => 2048,
    ]);

    Storage::disk(ProductDocument::DISK)->put($document->path, 'contents of '.$name);

    return $document;
}

/**
 * Ask for a run.
 */
function startAssessment(Organization $organization, Product $product): TestResponse
{
    return test()->post(route('products.assessments.store', [
        'current_organization' => $organization->slug,
        'product' => $product->id,
    ]));
}

/**
 * A well-formed answer with one finding against the given document number.
 *
 * @return array<string, mixed>
 */
function assessmentAnswer(int $documentIndex = 0, string $severity = 'critical'): array
{
    return [
        'summary' => 'The declaration of conformity does not list the standards applied.',
        'overall' => 'gaps_found',
        'findings' => [[
            'document_index' => $documentIndex,
            'severity' => $severity,
            'category' => 'incomplete',
            'requirement' => 'DoC: harmonised standards applied',
            'rationale' => 'No EN 71 parts are named on the declaration.',
            'evidence' => 'Page 1, section 5 is blank.',
            'ask_manufacturer' => 'Send a declaration listing EN 71-1, EN 71-2 and EN 71-3.',
        ]],
        'factory_request' => 'Please send an updated declaration of conformity listing EN 71-1, EN 71-2 and EN 71-3.',
    ];
}

test('a reviewer can ask for the papers to be read, and the run is queued', function () {
    Queue::fake();

    [$user, $organization, $product] = reviewerWithDocumentAnalysis();

    $this->actingAs($user);

    startAssessment($organization, $product)->assertRedirect()->assertSessionHasNoErrors();

    $assessment = $product->assessments()->sole();

    expect($assessment->status)->toBe(AssessmentStatus::Queued)
        ->and($assessment->requested_by)->toBe($user->id)
        ->and($assessment->prompt_version)->toBe(DocumentAssessmentAgent::PROMPT_VERSION)
        ->and($product->events()->where('type', ProductEventType::AssessmentRequested)->exists())->toBeTrue();

    Queue::assertPushed(RunProductAssessment::class, fn (RunProductAssessment $job): bool => $job->assessment->is($assessment));
});

test('the run reads the PDFs and images and records what it found against the right document', function () {
    [$user, $organization, $product] = reviewerWithDocumentAnalysis();

    $declaration = fileAssessedDocument($product, 'DoC.pdf', 'application/pdf', ProductDocumentType::DeclarationOfConformity);
    fileAssessedDocument($product, 'label.png', 'image/png', ProductDocumentType::SafetyImage);
    $spreadsheet = fileAssessedDocument($product, 'materials.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', ProductDocumentType::Other);

    DocumentAssessmentAgent::fake([assessmentAnswer(documentIndex: 0)])->preventStrayPrompts();

    $this->actingAs($user);

    startAssessment($organization, $product)->assertSessionHasNoErrors();

    $assessment = $product->assessments()->sole();
    $finding = $assessment->findings()->sole();

    expect($assessment->status)->toBe(AssessmentStatus::Completed)
        ->and($assessment->overall)->toBe(AssessmentOverall::GapsFound)
        ->and($assessment->factory_request)->toContain('EN 71-1')
        ->and(array_column($assessment->documents, 'name'))->toBe(['DoC.pdf', 'label.png'])
        ->and($assessment->skipped_documents)->toBe([['id' => $spreadsheet->id, 'name' => 'materials.xlsx', 'reason' => 'unsupported_type']])
        ->and($finding->product_document_id)->toBe($declaration->id)
        ->and($finding->document_name)->toBe('DoC.pdf')
        ->and($finding->severity)->toBe(FindingSeverity::Critical)
        ->and($product->events()->where('type', ProductEventType::AssessmentCompleted)->exists())->toBeTrue();

    DocumentAssessmentAgent::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->attachments->count() === 2
        && $prompt->attachments[0] instanceof StoredDocument
        && $prompt->attachments[1] instanceof Image
        && $prompt->contains('Magnetic Building Set')
        && $prompt->contains('materials.xlsx'));
});

test('a finding pointed at a document that is not on the list is kept against the product as a whole', function () {
    [$user, $organization, $product] = reviewerWithDocumentAnalysis();

    fileAssessedDocument($product, 'DoC.pdf', 'application/pdf', ProductDocumentType::DeclarationOfConformity);

    DocumentAssessmentAgent::fake([assessmentAnswer(documentIndex: 7)]);

    $this->actingAs($user);

    startAssessment($organization, $product);

    expect($product->assessments()->sole()->findings()->sole()->product_document_id)->toBeNull();
});

test('a finding with a severity or category the model made up is dropped', function () {
    [$user, $organization, $product] = reviewerWithDocumentAnalysis();

    $answer = assessmentAnswer();
    $answer['findings'][] = [...$answer['findings'][0], 'severity' => 'catastrophic'];
    $answer['findings'][] = [...$answer['findings'][0], 'category' => 'vibes'];

    DocumentAssessmentAgent::fake([$answer]);

    $this->actingAs($user);

    startAssessment($organization, $product);

    expect($product->assessments()->sole()->findings()->count())->toBe(1);
});

test('the answer is asked for in the organization language', function () {
    [$user, $organization, $product] = reviewerWithDocumentAnalysis();
    $organization->update(['locale' => Locale::German]);

    DocumentAssessmentAgent::fake([assessmentAnswer()]);

    $this->actingAs($user);

    startAssessment($organization, $product);

    DocumentAssessmentAgent::assertPrompted(fn (AgentPrompt $prompt): bool => str_contains((string) $prompt->agent->instructions(), 'in German'));
});

test('a provider that will not answer leaves a failed run with a reason, and no key behind', function () {
    [$user, $organization, $product] = reviewerWithDocumentAnalysis();

    DocumentAssessmentAgent::fake(function (): never {
        throw new RuntimeException('Gemini is down');
    });

    $this->actingAs($user);

    startAssessment($organization, $product)->assertSessionHasNoErrors();

    $assessment = $product->assessments()->sole();

    expect($assessment->status)->toBe(AssessmentStatus::Failed)
        ->and($assessment->failure_reason)->not->toBeNull()
        ->and(json_encode(config('ai.providers')))->not->toContain(ASSESSMENT_KEY);
});

test('a run that finds critical gaps never moves the review or the seal', function () {
    [$user, $organization, $product] = reviewerWithDocumentAnalysis();

    fileAssessedDocument($product, 'DoC.pdf', 'application/pdf', ProductDocumentType::DeclarationOfConformity);

    DocumentAssessmentAgent::fake([assessmentAnswer(severity: 'critical')]);

    $sealBefore = $product->seal();

    $this->actingAs($user);

    startAssessment($organization, $product);

    $product->refresh();

    expect($product->review_status)->toBe(ProductReviewStatus::InReview)
        ->and($product->seal_override)->toBeNull()
        ->and($product->seal()->status)->toBe($sealBefore->status)
        ->and($product->seal()->status)->not->toBe(ProductSealStatus::Verified);
});

test('a run is refused when the organization has not allowed document analysis', function () {
    DocumentAssessmentAgent::fake()->preventStrayPrompts();

    [$user, $organization, $product] = reviewerWithDocumentAnalysis(allowed: false);

    $this->actingAs($user);

    startAssessment($organization, $product)->assertSessionHasErrors('assessment');

    expect($product->assessments()->exists())->toBeFalse();
    DocumentAssessmentAgent::assertNeverPrompted();
});

test('a run is refused when the organization has no AI provider', function () {
    [$user, $organization] = newOrganizationMember();
    $product = Product::factory()->for($organization)->create([
        'supplier_connection_id' => newSupplierConnection($organization)->id,
    ]);

    $this->actingAs($user);

    startAssessment($organization, $product)->assertSessionHasErrors('assessment');

    expect($product->assessments()->exists())->toBeFalse();
});

test('a second run is refused while one is still going', function () {
    Queue::fake();

    [$user, $organization, $product] = reviewerWithDocumentAnalysis();

    ProductAssessment::factory()->for($product)->for($organization)->create(['status' => AssessmentStatus::Running]);

    $this->actingAs($user);

    startAssessment($organization, $product)->assertSessionHasErrors('assessment');

    expect($product->assessments()->count())->toBe(1);
    Queue::assertNothingPushed();
});

test('a run whose organization withdrew its agreement while it was queued fails without being sent', function () {
    DocumentAssessmentAgent::fake()->preventStrayPrompts();

    [, $organization, $product] = reviewerWithDocumentAnalysis();

    $assessment = ProductAssessment::factory()->for($product)->for($organization)->create();

    $organization->aiSetting->update(['allow_document_analysis' => false]);

    (new RunProductAssessment($assessment))->handle(app(AssessProductDocuments::class));

    expect($assessment->fresh()->status)->toBe(AssessmentStatus::Failed);
    DocumentAssessmentAgent::assertNeverPrompted();
});

test('a member may not ask for a run', function () {
    [$user, $organization, $product] = reviewerWithDocumentAnalysis(OrganizationRole::Member);

    $this->actingAs($user);

    startAssessment($organization, $product)->assertForbidden();
});

test('the supplier responsible for the product may not ask for a run or see one', function () {
    [, $distributor, $product] = reviewerWithDocumentAnalysis();

    [$supplierUser, $supplier] = newSupplierMember();
    $product->update(['supplier_connection_id' => newSupplierConnection($distributor, $supplier)->id]);

    $assessment = ProductAssessment::factory()->for($product)->for($distributor)->completed()->create();

    $this->actingAs($supplierUser);

    startAssessment($supplier, $product)->assertForbidden();

    $this->getJson(route('products.assessments.show', [
        'current_organization' => $supplier->slug,
        'product' => $product->id,
        'assessment' => $assessment->id,
    ]))->assertForbidden();

    $this->get(route('products.edit', ['current_organization' => $supplier->slug, 'product' => $product->id]))
        ->assertInertia(fn ($page) => $page->where('assessment', null));
});

test('a supplier whose connection is revoked may not ask for a run', function () {
    [, $distributor, $product] = reviewerWithDocumentAnalysis();

    [$supplierUser, $supplier] = newSupplierMember();
    $product->update(['supplier_connection_id' => newSupplierConnection($distributor, $supplier, [
        'status' => SupplierConnectionStatus::Revoked,
    ])->id]);

    $this->actingAs($supplierUser);

    startAssessment($supplier, $product)->assertNotFound();

    expect($product->assessments()->exists())->toBeFalse();
});

test('a run on another organization product cannot be read', function () {
    [$user, $organization] = reviewerWithDocumentAnalysis();
    [, $other, $otherProduct] = reviewerWithDocumentAnalysis();

    $assessment = ProductAssessment::factory()->for($otherProduct)->for($other)->completed()->create();

    $this->actingAs($user);

    $this->getJson(route('products.assessments.show', [
        'current_organization' => $organization->slug,
        'product' => $otherProduct->id,
        'assessment' => $assessment->id,
    ]))->assertNotFound();
});

test('a reviewer can read an earlier run with its findings', function () {
    [$user, $organization, $product] = reviewerWithDocumentAnalysis();

    $assessment = ProductAssessment::factory()->for($product)->for($organization)->completed()->hasFindings(2)->create();

    $this->actingAs($user);

    $this->getJson(route('products.assessments.show', [
        'current_organization' => $organization->slug,
        'product' => $product->id,
        'assessment' => $assessment->id,
    ]))
        ->assertOk()
        ->assertJsonPath('id', $assessment->id)
        ->assertJsonPath('overall', 'gaps_found')
        ->assertJsonCount(2, 'findings');
});

test('the queue does not hand a run to a second worker while the first is still reading', function () {
    $job = new RunProductAssessment(ProductAssessment::factory()->make());

    expect(config('queue.connections.database.retry_after'))->toBeGreaterThan($job->timeout);
});

test('a run lost by the queue does not block the next one forever', function () {
    Queue::fake();

    [$user, $organization, $product] = reviewerWithDocumentAnalysis();

    $lost = ProductAssessment::factory()->for($product)->for($organization)->create([
        'status' => AssessmentStatus::Queued,
        'created_at' => now()->subMinutes(AssessProductDocuments::STALE_AFTER_MINUTES + 1),
    ]);

    $this->actingAs($user);

    startAssessment($organization, $product)->assertSessionHasNoErrors();

    expect($lost->fresh()->status)->toBe(AssessmentStatus::Failed)
        ->and($product->assessments()->count())->toBe(2);

    Queue::assertPushed(RunProductAssessment::class);
});

test('a failure is written in the organization language, whatever language the worker speaks', function () {
    [, $organization, $product] = reviewerWithDocumentAnalysis();
    $organization->update(['locale' => Locale::German]);

    DocumentAssessmentAgent::fake(function (): never {
        throw new RuntimeException('Gemini is down');
    });

    $assessment = ProductAssessment::factory()->for($product)->for($organization)->create();

    app()->setLocale('en');

    (new RunProductAssessment($assessment))->handle(app(AssessProductDocuments::class));

    expect($assessment->fresh()->failure_reason)->toBe('Der KI-Anbieter hat nicht geantwortet. Versuchen Sie es in ein paar Minuten erneut.');
});

test('the check always runs on the distributor key, even opened from the supplier side', function () {
    DocumentAssessmentAgent::fake()->preventStrayPrompts();

    [$user, $distributor] = newOrganizationMember();
    [, $supplier] = newSupplierMember();

    /** The same person reviews for the distributor and owns the supplier. */
    $supplier->members()->attach($user, ['role' => OrganizationRole::Owner->value]);

    $product = Product::factory()->for($distributor)->create([
        'supplier_connection_id' => newSupplierConnection($distributor, $supplier)->id,
    ]);

    $supplier->aiSetting()->create([
        'provider' => AiProvider::Gemini,
        'model' => AiProvider::Gemini->defaultModel(),
        'api_key' => ASSESSMENT_KEY,
        'allow_document_analysis' => true,
    ]);

    $this->actingAs($user);

    startAssessment($supplier, $product)->assertSessionHasErrors('assessment');

    expect($product->assessments()->exists())->toBeFalse();
    DocumentAssessmentAgent::assertNeverPrompted();
});
