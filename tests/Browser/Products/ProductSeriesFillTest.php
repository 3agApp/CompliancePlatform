<?php

use App\Enums\ProductDocumentType;
use App\Enums\ProductRequirement;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductDocument;
use App\Models\ProductTemplate;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake(ProductDocument::DISK);
});

/**
 * A distributor with three products from a supplier that has not joined,
 * on a template asking for an age grading and a warning: the shape
 * production is in.
 *
 * @return array{0: User, 1: Organization}
 */
function browserDistributorWithSeries(): array
{
    [$user, $organization] = newOrganizationMember();
    $connection = newSupplierConnection($organization, attributes: ['company_name' => 'Magna Supplies']);

    $template = ProductTemplate::factory()
        ->for(legalFamily($organization), 'category')
        ->requiring(ProductRequirement::AgeGrading, ProductRequirement::WarningText)
        ->create();

    foreach (['Classic Set', 'Dinos Set', 'Glow Set'] as $name) {
        Product::factory()->for($organization)->create([
            'name' => $name,
            'supplier_connection_id' => $connection->id,
            'product_category_id' => $template->product_category_id,
            'product_template_id' => $template->id,
        ]);
    }

    return [$user, $organization];
}

/**
 * The dashboard names what holds the series back, and "Fill for all" opens
 * the bulk change on the safety details with every product ticked.
 */
test('a distributor fills a gap for the whole series from the dashboard', function () {
    [$user, $organization] = browserDistributorWithSeries();

    $this->actingAs($user);

    visit(route('dashboard', ['current_organization' => $organization->slug]))
        ->assertSeeIn('@dashboard-readiness', '0')
        ->assertSeeIn('@dashboard-gaps', 'Age grading')
        ->assertSeeIn('@dashboard-attention-waiting', 'Magna Supplies')
        ->click('@dashboard-gap-fill')
        ->assertSee('Edit 3 products')
        ->type('@bulk-age_grading', '3+')
        ->type('@bulk-warning_text', 'Not suitable for children under 3 years.')
        ->click('@bulk-edit-confirm')
        ->assertSee('3 products updated.')
        ->assertNoJavaScriptErrors();

    expect($organization->products()->where('age_grading', '3+')->count())->toBe(3)
        ->and($organization->products()->whereNull('warning_text')->count())->toBe(0);
});

/**
 * Drafts nobody else will fill in are the distributor's own to-do, so the
 * product page steps through them.
 */
test('a distributor steps through the drafts it fills in itself', function () {
    [$user, $organization] = browserDistributorWithSeries();
    $first = $organization->products()->orderBy('id')->first();

    $this->actingAs($user);

    visit(route('products.edit', ['current_organization' => $organization->slug, 'product' => $first->id]))
        ->assertVisible('@product-todo-bar')
        ->assertSeeIn('@product-todo-bar', 'Dinos Set')
        ->assertNoJavaScriptErrors();
});

test('a distributor shows every image of a product on the public page at once', function () {
    [$user, $organization] = browserDistributorWithSeries();
    $product = $organization->products()->first();

    foreach (['front.png', 'side.png'] as $name) {
        $image = ProductDocument::factory()->for($product)->ofType(ProductDocumentType::ProductImage)->create([
            'name' => $name,
            'mime_type' => 'image/png',
        ]);
        Storage::disk(ProductDocument::DISK)->put($image->path, 'png');
    }

    $this->actingAs($user);

    visit(route('products.edit', ['current_organization' => $organization->slug, 'product' => $product->id, 'tab' => 'documents']))
        ->assertSeeIn('@product-images-visibility-all', 'Show all on public page')
        ->click('@product-images-visibility-all')
        ->assertSee('2 images shown on the public page.')
        ->assertSeeIn('@product-images-visibility-all', 'Hide all from public page')
        ->assertNoJavaScriptErrors();

    expect($product->documents()->where('is_public', true)->count())->toBe(2);
});
