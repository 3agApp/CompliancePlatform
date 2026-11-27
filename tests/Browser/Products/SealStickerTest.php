<?php

use App\Enums\OrganizationRole;
use App\Enums\ProductReviewStatus;
use App\Models\Product;

/**
 * Keep a note of every sheet the page fetches to print, so a test can see
 * how many sheets were asked for.
 */
const RECORD_SHEET_FETCHES = <<<'JS'
    window.fetched = [];
    const fetchSheet = window.fetch;
    window.fetch = (url, ...rest) => { window.fetched.push(String(url)); return fetchSheet(url, ...rest); };
    true;
JS;

test('an owner prints sheets of seals from their own page in the sidebar', function () {
    [$user, $organization] = newOrganizationMember();

    $this->actingAs($user);

    $page = visit(route('dashboard', ['current_organization' => $organization->slug]));

    $page->click('@nav-seal-stickers')
        ->assertPathIs("/{$organization->slug}/seal-stickers")
        ->assertSee('Seal stickers')
        ->assertSee('24 to a sheet')
        ->assertSeeIn('@seal-stickers-count', '24 seals');

    $page->script(RECORD_SHEET_FETCHES);

    $page->fill('@seal-sheets', '3')
        ->assertSeeIn('@seal-stickers-count', '72 seals')
        ->click('@seal-stickers-print')
        ->assertPresent('@label-print-frame')
        ->assertNoJavaScriptErrors();

    expect($page->script('window.fetched'))->toBe([
        "/{$organization->slug}/seal-stickers/pdf?sheets=3",
    ]);
});

test('more sheets than may be printed at once cannot be sent', function () {
    [$user, $organization] = newOrganizationMember();

    $this->actingAs($user);

    visit(route('seal-stickers.index', ['current_organization' => $organization->slug]))
        ->fill('@seal-sheets', '21')
        ->assertSee('Between 1 and 20 sheets.')
        ->assertButtonDisabled('@seal-stickers-print')
        ->assertNoJavaScriptErrors();
});

test('a member does not see the seal stickers in the sidebar', function () {
    [$user, $organization] = newOrganizationMember(OrganizationRole::Member);

    $this->actingAs($user);

    visit(route('dashboard', ['current_organization' => $organization->slug]))
        ->assertMissing('@nav-seal-stickers')
        ->assertNoJavaScriptErrors();
});

test('an approved product offers its seal for printing beside its labels', function () {
    [$user, $organization] = newOrganizationMember();

    $product = Product::factory()->for($organization)
        ->reviewed(ProductReviewStatus::Approved)
        ->create(['supplier_connection_id' => newSupplierConnection($organization)->id]);

    $this->actingAs($user);

    $page = visit(route('products.edit', [
        'current_organization' => $organization->slug,
        'product' => $product->id,
        'tab' => 'public',
    ]));

    $page->script(RECORD_SHEET_FETCHES);

    $page->assertPresent('@seal-stickers-card')
        ->click('@seal-stickers-print')
        ->assertPresent('@label-print-frame')
        ->assertNoJavaScriptErrors();

    expect($page->script('window.fetched'))->toBe([
        "/{$organization->slug}/seal-stickers/pdf?sheets=1",
    ]);
});

test('a product that has not earned the seal does not offer it', function () {
    [$user, $organization] = newOrganizationMember();

    $product = Product::factory()->for($organization)
        ->reviewed(ProductReviewStatus::InReview)
        ->create(['supplier_connection_id' => newSupplierConnection($organization)->id]);

    $this->actingAs($user);

    visit(route('products.edit', [
        'current_organization' => $organization->slug,
        'product' => $product->id,
        'tab' => 'public',
    ]))
        ->assertPresent('@serial-labels-panel')
        ->assertMissing('@seal-stickers-card')
        ->assertNoJavaScriptErrors();
});
