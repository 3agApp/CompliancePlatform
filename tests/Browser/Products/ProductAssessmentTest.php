<?php

use App\Enums\AiProvider;
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
