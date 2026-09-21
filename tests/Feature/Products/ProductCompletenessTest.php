<?php

use App\Enums\ProductDocumentType;
use App\Enums\ProductRequirement;
use App\Models\Product;
use App\Models\ProductDocument;
use App\Models\ProductTemplate;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Score a product against a template asking for exactly these things.
 *
 * @param  array<int, ProductRequirement>  $requirements
 * @param  array<string, mixed>  $attributes
 * @param  array<int, ProductDocumentType>  $documents
 */
function scoreFor(array $requirements, array $attributes = [], array $documents = []): int
{
    [, $distributor] = newOrganizationMember();

    $template = ProductTemplate::factory()
        ->requiring(...$requirements)
        ->create(['product_category_id' => legalFamily($distributor)->id]);

    $product = Product::factory()
        ->for($distributor)
        ->usingTemplate($template)
        ->withoutOptionalDetails()
        ->create($attributes);

    foreach ($documents as $type) {
        ProductDocument::factory()->for($product)->ofType($type)->create();
    }

    return $product->load(['template', 'documents'])->completeness()->score;
}

test('a template that asks for nothing is complete, rather than undefined', function () {
    expect(scoreFor([]))->toBe(100);
});

test('a template that asks only for a manual is complete, because a manual never lowers the score', function () {
    expect(scoreFor([ProductRequirement::ManualOrInstructions]))->toBe(100);
});

test('a missing manual does not drag down a product that has everything else', function () {
    $withoutTheManual = scoreFor(
        [ProductRequirement::TestReport, ProductRequirement::ManualOrInstructions],
        documents: [ProductDocumentType::TestReport],
    );

    expect($withoutTheManual)->toBe(100);
});

test('the papers an authority asks for first are worth the most', function () {
    /**
     * A test report is worth three and a product image one, so filing the
     * report alone answers three quarters of a template asking for both.
     */
    $reportOnly = scoreFor(
        [ProductRequirement::TestReport, ProductRequirement::ProductImage],
        documents: [ProductDocumentType::TestReport],
    );

    $imageOnly = scoreFor(
        [ProductRequirement::TestReport, ProductRequirement::ProductImage],
        documents: [ProductDocumentType::ProductImage],
    );

    expect($reportOnly)->toBe(75)
        ->and($imageOnly)->toBe(25);
});

test('the safety wording is a fifth of a standard toy template', function () {
    /**
     * The calibration the weights were chosen for. A toy template asks for
     * a test report, a declaration, a certificate, a safety image, a
     * manual, a barcode, an origin, an age grading, a safety notice and a
     * warning text -- fifteen points, of which the three safety fields are
     * three.
     */
    $safetyWordingOnly = scoreFor(
        [
            ProductRequirement::TestReport,
            ProductRequirement::DeclarationOfConformity,
            ProductRequirement::Certificate,
            ProductRequirement::SafetyImage,
            ProductRequirement::ManualOrInstructions,
            ProductRequirement::Ean,
            ProductRequirement::CountryOfOrigin,
            ProductRequirement::AgeGrading,
            ProductRequirement::SafetyNotice,
            ProductRequirement::WarningText,
        ],
        attributes: [
            'age_grading' => '3+',
            'safety_notice' => 'Keep the packaging until the product has been checked.',
            'warning_text' => 'Not suitable for children under 3 years.',
        ],
    );

    expect($safetyWordingOnly)->toBe(20);
});

test('a second paper of a kind already filed is more evidence, not more progress', function () {
    $once = scoreFor(
        [ProductRequirement::TestReport, ProductRequirement::Certificate],
        documents: [ProductDocumentType::TestReport],
    );

    $twice = scoreFor(
        [ProductRequirement::TestReport, ProductRequirement::Certificate],
        documents: [ProductDocumentType::TestReport, ProductDocumentType::TestReport],
    );

    expect($once)->toBe(60)->and($twice)->toBe(60);
});

test('a paper the template never asked for counts for nothing', function () {
    $score = scoreFor(
        [ProductRequirement::TestReport],
        documents: [ProductDocumentType::Certificate],
    );

    expect($score)->toBe(0);
});

test('a field answered with nothing but spaces is not an answer', function () {
    expect(scoreFor([ProductRequirement::WarningText], attributes: ['warning_text' => '   ']))->toBe(0)
        ->and(scoreFor([ProductRequirement::WarningText], attributes: ['warning_text' => 'Small parts.']))->toBe(100);
});

test('a brand and a country of origin answer their requirements', function () {
    [, $distributor] = newOrganizationMember();
    $connection = newSupplierConnection($distributor);

    $template = ProductTemplate::factory()
        ->requiring(ProductRequirement::Brand, ProductRequirement::CountryOfOrigin)
        ->create(['product_category_id' => legalFamily($distributor)->id]);

    $product = Product::factory()
        ->for($distributor)
        ->usingTemplate($template)
        ->withoutOptionalDetails()
        ->ofBrand(carriedBrand($connection))
        ->create([
            'supplier_connection_id' => $connection->id,
            'country_of_origin' => 'DE',
        ]);

    expect($product->load(['template', 'documents'])->completeness()->score)->toBe(100);
});

test('the checklist lists every requirement the template asks for, answered or not', function () {
    [$user, $distributor] = newOrganizationMember();
    $connection = newSupplierConnection($distributor);

    $template = ProductTemplate::factory()
        ->requiring(ProductRequirement::TestReport, ProductRequirement::WarningText)
        ->create(['product_category_id' => legalFamily($distributor)->id, 'name' => 'EU toy safety']);

    $product = Product::factory()
        ->for($distributor)
        ->usingTemplate($template)
        ->withoutOptionalDetails()
        ->create([
            'supplier_connection_id' => $connection->id,
            'warning_text' => 'Not suitable for children under 3 years.',
        ]);

    $this
        ->actingAs($user)
        ->get(route('products.edit', ['current_organization' => $distributor->slug, 'product' => $product->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('completeness.score', 25)
            ->where('completeness.items', [
                [
                    'requirement' => 'requires_test_report',
                    'label' => 'Test report',
                    'group' => 'document',
                    /** The kind the documents panel offers to file. */
                    'document_type' => 'test_report',
                    'weight' => 3,
                    'satisfied' => false,
                ],
                [
                    'requirement' => 'requires_warning_text',
                    'label' => 'Warning text',
                    'group' => 'data',
                    /** Nothing to file: this one is typed into the form. */
                    'document_type' => null,
                    'weight' => 1,
                    'satisfied' => true,
                ],
            ])
            ->where('product.template_label', 'EU toy safety'),
        );
});

test('the product list carries a score per row without carrying the prose it is read from', function () {
    [$user, $distributor] = newOrganizationMember();
    $connection = newSupplierConnection($distributor);

    $template = ProductTemplate::factory()
        ->requiring(ProductRequirement::WarningText, ProductRequirement::SafetyNotice)
        ->create(['product_category_id' => legalFamily($distributor)->id]);

    Product::factory()
        ->for($distributor)
        ->usingTemplate($template)
        ->withoutOptionalDetails()
        ->create([
            'supplier_connection_id' => $connection->id,
            'warning_text' => 'Not suitable for children under 3 years.',
        ]);

    $this
        ->actingAs($user)
        ->get(route('products.index', ['current_organization' => $distributor->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('products.data.0.completeness_score', 50)
            ->missing('products.data.0.warning_text')
            ->missing('products.data.0.safety_notice'),
        );
});

test('scoring the product list costs the same whatever its length', function () {
    [$user, $distributor] = newOrganizationMember();
    $connection = newSupplierConnection($distributor);

    $template = ProductTemplate::factory()
        ->forToys()
        ->create(['product_category_id' => legalFamily($distributor)->id]);

    Product::factory()->count(3)->for($distributor)->usingTemplate($template)->create([
        'supplier_connection_id' => $connection->id,
    ]);

    $queries = 0;
    DB::listen(function () use (&$queries) {
        $queries++;
    });

    $this
        ->actingAs($user)
        ->get(route('products.index', ['current_organization' => $distributor->slug]))
        ->assertOk();

    $withThree = $queries;

    Product::factory()->count(7)->for($distributor)->usingTemplate($template)->create([
        'supplier_connection_id' => $connection->id,
    ]);

    $queries = 0;

    $this
        ->actingAs($user)
        ->get(route('products.index', ['current_organization' => $distributor->slug]))
        ->assertOk();

    expect($queries)->toBe($withThree);
});
