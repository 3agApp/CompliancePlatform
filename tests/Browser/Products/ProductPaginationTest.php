<?php

use App\Data\ProductFilters;
use App\Models\Organization;
use App\Models\Product;

/**
 * Fill a distributor's catalogue past the first page.
 *
 * Names are numbered so alphabetical order is predictable, which is what
 * lets a test say which rows belong on which page.
 */
function seedPagesOfProducts(Organization $distributor, int $count): void
{
    $connection = newSupplierConnection($distributor, attributes: ['company_name' => 'Acme Supplies']);

    foreach (range(1, $count) as $index) {
        Product::factory()->for($distributor)->create([
            'name' => sprintf('Product %03d', $index),
            'supplier_connection_id' => $connection->id,
        ]);
    }
}

test('a catalogue longer than one page is walked with the pagination controls', function () {
    [$user, $distributor] = newOrganizationMember();

    $perPage = ProductFilters::PAGE_SIZES[0];
    seedPagesOfProducts($distributor, $perPage + 2);

    $this->actingAs($user);

    $page = visit(route('products.index', ['current_organization' => $distributor->slug]));

    $page->assertSee('Product 001')
        ->assertDontSee(sprintf('Product %03d', $perPage + 1))
        ->click('@pagination-next')
        ->assertSee(sprintf('Product %03d', $perPage + 1))
        ->assertDontSee('Product 001')
        ->click('@pagination-previous')
        ->assertSee('Product 001')
        ->assertNoJavaScriptErrors();
});

test('the page size control re-renders the list at the chosen size', function () {
    [$user, $distributor] = newOrganizationMember();

    $perPage = ProductFilters::PAGE_SIZES[0];
    seedPagesOfProducts($distributor, $perPage + 2);

    $this->actingAs($user);

    $page = visit(route('products.index', ['current_organization' => $distributor->slug]));

    $page->assertDontSee(sprintf('Product %03d', $perPage + 1))
        ->click('@per-page')
        ->click(sprintf('[role="option"]:has-text("%d / page")', ProductFilters::PAGE_SIZES[1]))
        ->assertSee(sprintf('Product %03d', $perPage + 1))
        ->assertNoJavaScriptErrors();
});

test('narrowing the list from a later page lands on results rather than an empty page', function () {
    [$user, $distributor] = newOrganizationMember();

    $perPage = ProductFilters::PAGE_SIZES[0];
    seedPagesOfProducts($distributor, $perPage + 2);

    $this->actingAs($user);

    $page = visit(route('products.index', [
        'current_organization' => $distributor->slug,
        'page' => 2,
    ]));

    $page->assertSee(sprintf('Product %03d', $perPage + 1))
        ->fill('@product-filter-search', 'Product 001')
        ->assertSee('Product 001')
        ->assertDontSee('No products match these filters')
        ->assertNoJavaScriptErrors();
});

test('a single page of products carries no pagination controls', function () {
    [$user, $distributor] = newOrganizationMember();

    seedPagesOfProducts($distributor, 3);

    $this->actingAs($user);

    $page = visit(route('products.index', ['current_organization' => $distributor->slug]));

    $page->assertSee('Product 001')
        ->assertMissing('[aria-label="Pagination"]')
        ->assertNoJavaScriptErrors();
});
