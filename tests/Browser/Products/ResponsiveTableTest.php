<?php

use App\Models\Product;

/**
 * A phone is 375 points wide and a catalogue row is not.
 *
 * Below md the tables stop being tables: each row becomes a card, and every
 * cell that took its meaning from a column heading carries that heading with
 * it. The check that matters is that nothing is wider than the screen --
 * cut-off text with no way to reach it is the failure this guards against.
 */
const PHONE_WIDTH = 375;

/**
 * The widest thing on the page, in pixels.
 *
 * Read from the document rather than asserted on a screenshot, because a
 * column sliding out of view is a measurement, not a look.
 */
function widestElementOn(object $page): int
{
    return (int) $page->script('Math.max(document.documentElement.scrollWidth, ...[...document.querySelectorAll("body *")].map((el) => Math.ceil(el.getBoundingClientRect().right)))');
}

test('the products table becomes cards on a phone, with nothing cut off', function () {
    [$user, $organization] = newOrganizationMember();
    $connection = newSupplierConnection($organization, attributes: ['company_name' => 'Shenzhen Brightline']);

    Product::factory()->for($organization)->create([
        'name' => 'Baby Bottle 0-6 m 001',
        'supplier_connection_id' => $connection->id,
    ]);

    $this->actingAs($user);

    $page = visit(route('products.index', ['current_organization' => $organization->slug]));

    $page->resize(PHONE_WIDTH, 812)
        ->assertSee('Baby Bottle 0-6 m 001')
        /** The headings are gone from the top and back on each cell. */
        ->assertPresent('[data-label="Category"]')
        ->assertPresent('[data-label="Complete"]')
        ->assertNoJavaScriptErrors();

    expect(widestElementOn($page))->toBeLessThanOrEqual(PHONE_WIDTH);
});

test('the suppliers table becomes cards on a phone, with nothing cut off', function () {
    [$user, $organization] = newOrganizationMember();
    newSupplierConnection($organization, attributes: ['company_name' => 'Shenzhen Brightline Industrial']);

    $this->actingAs($user);

    $page = visit(route('suppliers.index', ['current_organization' => $organization->slug]));

    $page->resize(PHONE_WIDTH, 812)
        ->assertSee('Shenzhen Brightline Industrial')
        ->assertPresent('[data-label="Status"]')
        ->assertNoJavaScriptErrors();

    expect(widestElementOn($page))->toBeLessThanOrEqual(PHONE_WIDTH);
});

test('the categories table becomes cards on a phone, with nothing cut off', function () {
    [$user, $organization] = newOrganizationMember();

    $this->actingAs($user);

    $page = visit(route('categories.index', ['current_organization' => $organization->slug]));

    $page->resize(PHONE_WIDTH, 812)
        ->assertPresent('[data-label="Templates"]')
        ->assertNoJavaScriptErrors();

    expect(widestElementOn($page))->toBeLessThanOrEqual(PHONE_WIDTH);
});

test('the same tables are still tables on a desktop', function () {
    [$user, $organization] = newOrganizationMember();
    $connection = newSupplierConnection($organization);

    Product::factory()->for($organization)->create([
        'name' => 'Baby Bottle 0-6 m 001',
        'supplier_connection_id' => $connection->id,
    ]);

    $this->actingAs($user);

    $page = visit(route('products.index', ['current_organization' => $organization->slug]));

    $page->resize(1280, 800)->assertSee('Baby Bottle 0-6 m 001');

    /** The column headings are back, and the cells no longer name themselves. */
    $labelShown = $page->script('getComputedStyle(document.querySelector(\'[data-label="Category"]\'), "::before").display');
    $headShown = $page->script('getComputedStyle(document.querySelector("thead")).display');

    expect($labelShown)->toBe('none')
        ->and($headShown)->not->toBe('none');
});
