<?php

use App\Enums\AiProvider;
use App\Enums\AssessmentOverall;
use App\Enums\AssessmentStatus;
use App\Enums\FindingSeverity;
use App\Enums\ProductReviewStatus;
use App\Models\Product;
use App\Models\ProductAssessment;
use App\Models\ProductDocument;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake(ProductDocument::DISK);
});

/**
 * The reviewer reads what the AI check found and turns its draft into the
 * note the product goes back to the supplier with. The check itself never
 * moved the product: the reviewer's own Request changes form does.
 */
test('a reviewer sends the product back with the AI check draft as the note', function () {
    [$user, $organization] = newOrganizationMember();
    [, $supplier] = newSupplierMember();

    $product = Product::factory()->for($organization)->create([
        'name' => 'Magnetic Building Set',
        'supplier_connection_id' => newSupplierConnection($organization, $supplier)->id,
        'review_status' => ProductReviewStatus::InReview,
    ]);

    $organization->aiSetting()->create([
        'provider' => AiProvider::Gemini,
        'model' => AiProvider::Gemini->defaultModel(),
        'api_key' => 'AIzaSyD-BrowserKeyForTests-0123456789',
        'allow_document_analysis' => true,
    ]);

    ProductAssessment::factory()->for($product)->for($organization)->completed()
        ->hasFindings(1, [
            'severity' => FindingSeverity::Critical,
            'requirement' => 'EN 71-3 migration of elements',
            'rationale' => 'No test report covers EN 71-3.',
        ])
        ->create([
            'factory_request' => 'Please send an EN 71-3 test report for this article.',
        ]);

    $this->actingAs($user);

    visit(route('products.edit', ['current_organization' => $organization->slug, 'product' => $product->id]))
        ->assertSee('AI document check')
        ->assertSee('Advisory only')
        ->assertSee('EN 71-3 migration of elements')
        ->assertSee('No test report covers EN 71-3.')
        ->assertVisible('@assessment-finding-critical')
        ->assertVisible('@assessment-download-report')
        ->click('@assessment-use-as-request')
        ->assertSee('Send Magnetic Building Set back?')
        ->assertValue('@review-note', 'Please send an EN 71-3 test report for this article.')
        ->click('@request-changes-confirm')
        ->assertSee('Sent back to the supplier.')
        ->assertSee('Changes requested')
        ->assertNoJavaScriptErrors();

    $product->refresh();

    expect($product->review_status)->toBe(ProductReviewStatus::ChangesRequested)
        ->and($product->latestReviewNote())->toBe('Please send an EN 71-3 test report for this article.');
});

/**
 * The run happens on the queue, so the page watches it and shows the
 * result by itself once it lands -- nobody should have to reload to find
 * out the check finished.
 */
test('a run in progress shows its result by itself once it finishes', function () {
    [$user, $organization] = newOrganizationMember();

    $product = Product::factory()->for($organization)->create([
        'supplier_connection_id' => newSupplierConnection($organization)->id,
        'review_status' => ProductReviewStatus::InReview,
    ]);

    $assessment = ProductAssessment::factory()->for($product)->for($organization)->create();

    $this->actingAs($user);

    $page = visit(route('products.edit', ['current_organization' => $organization->slug, 'product' => $product->id]))
        ->assertVisible('@assessment-pending');

    $assessment->update([
        'status' => AssessmentStatus::Completed,
        'overall' => AssessmentOverall::GapsFound,
        'summary' => 'The declaration does not name the standards applied.',
        'completed_at' => now(),
    ]);

    $page->wait(6)
        ->assertSee('The declaration does not name the standards applied.')
        ->assertMissing('@assessment-pending')
        ->assertNoJavaScriptErrors();
});

/**
 * After the factory sends fixes and the check is run again, the reviewer
 * sees what was fixed and what is still open, not just a fresh list.
 */
test('a second run shows what was fixed and what is still open since the first', function () {
    [$user, $organization] = newOrganizationMember();

    $product = Product::factory()->for($organization)->create([
        'name' => 'Magnetic Building Set',
        'supplier_connection_id' => newSupplierConnection($organization)->id,
        'review_status' => ProductReviewStatus::InReview,
    ]);

    $earlier = ProductAssessment::factory()->for($product)->for($organization)->completed()->create();

    $stillOpen = $earlier->findings()->create([
        'severity' => FindingSeverity::Critical,
        'category' => 'incomplete',
        'requirement' => 'DoC: harmonised standards applied',
        'rationale' => 'No EN 71 parts are named.',
        'position' => 0,
    ]);

    $earlier->findings()->create([
        'severity' => FindingSeverity::Major,
        'category' => 'missing_document',
        'requirement' => 'EN 71-3 test report',
        'rationale' => 'No migration test report was filed.',
        'position' => 1,
    ]);

    $latest = ProductAssessment::factory()->for($product)->for($organization)->completed()->create([
        'previous_assessment_id' => $earlier->id,
    ]);

    $latest->findings()->create([
        'previous_finding_id' => $stillOpen->id,
        'severity' => FindingSeverity::Major,
        'category' => 'incomplete',
        'requirement' => 'DoC: harmonised standards applied',
        'rationale' => 'Only EN 71-1 is named now.',
        'position' => 0,
    ]);

    $this->actingAs($user);

    visit(route('products.edit', ['current_organization' => $organization->slug, 'product' => $product->id]))
        ->assertVisible('@assessment-comparison')
        ->assertSee('1 fixed')
        ->assertSee('1 still open')
        ->assertVisible('@assessment-finding-still-open')
        ->assertSee('Still open, was Critical')
        ->assertVisible('@assessment-resolved')
        ->assertSee('EN 71-3 test report')
        ->assertNoJavaScriptErrors();
});
