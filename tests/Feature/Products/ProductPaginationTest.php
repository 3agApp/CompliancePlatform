<?php

use App\Data\ProductFilters;
use App\Models\Organization;
use App\Models\Product;
use App\Models\User;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Visit the product list with a query string.
 *
 * @param  array<string, string|int>  $query
 */
function paginatedProducts(User $user, Organization $organization, array $query = []): TestResponse
{
    return test()
        ->actingAs($user)
        ->get(route('products.index', ['current_organization' => $organization->slug, ...$query]));
}

test('the product list sends one page of rows rather than the whole catalogue', function () {
    [$user, $distributor] = newOrganizationMember();
    $connection = newSupplierConnection($distributor);

    $perPage = ProductFilters::PAGE_SIZES[0];

    Product::factory()
        ->count($perPage + 5)
        ->for($distributor)
        ->create(['supplier_connection_id' => $connection->id]);

    paginatedProducts($user, $distributor)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('products.data', $perPage)
            ->where('products.total', $perPage + 5)
            ->where('products.current_page', 1)
            ->where('products.last_page', 2)
            ->where('filters.perPage', $perPage),
        );
});

test('the rows stay in name order across pages', function () {
    [$user, $distributor] = newOrganizationMember();
    $connection = newSupplierConnection($distributor);

    $perPage = ProductFilters::PAGE_SIZES[0];

    /**
     * Names are numbered so that alphabetical order is also creation order,
     * which is what makes the split between the pages predictable.
     */
    foreach (range(1, $perPage + 2) as $index) {
        Product::factory()->for($distributor)->create([
            'name' => sprintf('Product %03d', $index),
            'supplier_connection_id' => $connection->id,
        ]);
    }

    paginatedProducts($user, $distributor)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('products.data', $perPage)
            ->where('products.data.0.name', 'Product 001'),
        );

    paginatedProducts($user, $distributor, ['page' => 2])
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('products.data', 2)
            ->where('products.data.0.name', sprintf('Product %03d', $perPage + 1))
            ->where('products.current_page', 2),
        );
});

test('a page past the end sends the reader to the last page that has rows', function () {
    [$user, $distributor] = newOrganizationMember();
    $connection = newSupplierConnection($distributor);

    Product::factory()->count(3)->for($distributor)->create([
        'supplier_connection_id' => $connection->id,
    ]);

    paginatedProducts($user, $distributor, ['page' => 9])
        ->assertRedirect(route('products.index', [
            'current_organization' => $distributor->slug,
            'page' => 1,
        ]));
});

test('the first page is rendered rather than redirected when the catalogue is empty', function () {
    [$user, $distributor] = newOrganizationMember();

    paginatedProducts($user, $distributor)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('products.data', 0)
            ->where('products.total', 0)
            ->where('hasProducts', false),
        );
});

test('the page size is honoured when it is one the list offers', function () {
    [$user, $distributor] = newOrganizationMember();
    $connection = newSupplierConnection($distributor);

    Product::factory()->count(30)->for($distributor)->create([
        'supplier_connection_id' => $connection->id,
    ]);

    $size = ProductFilters::PAGE_SIZES[1];

    paginatedProducts($user, $distributor, ['per_page' => $size])
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('products.data', 30)
            ->where('products.per_page', $size)
            ->where('filters.perPage', $size),
        );
});

test('a page size the list does not offer falls back to the default', function (string $size) {
    [$user, $distributor] = newOrganizationMember();
    $connection = newSupplierConnection($distributor);

    Product::factory()->count(30)->for($distributor)->create([
        'supplier_connection_id' => $connection->id,
    ]);

    paginatedProducts($user, $distributor, ['per_page' => $size])
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.perPage', ProductFilters::PAGE_SIZES[0])
            ->where('products.per_page', ProductFilters::PAGE_SIZES[0]),
        );
})->with([
    'a size nobody offers' => '7',
    'a hand-typed huge size' => '100000',
    'not a number' => 'lots',
    'blank' => '',
    'zero' => '0',
    'negative' => '-25',
]);

test('the page sizes the list offers are shared with the page', function () {
    [$user, $distributor] = newOrganizationMember();

    paginatedProducts($user, $distributor)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('pageSizes', ProductFilters::PAGE_SIZES),
        );
});

test('the filters survive the links to the other pages', function () {
    [$user, $distributor] = newOrganizationMember();
    $connection = newSupplierConnection($distributor);

    Product::factory()->count(30)->for($distributor)->create([
        'name' => 'Oat Milk',
        'supplier_connection_id' => $connection->id,
    ]);

    paginatedProducts($user, $distributor, ['search' => 'Oat'])
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('products.total', 30)
            ->where('filters.search', 'Oat')
            ->where(
                'products.next_page_url',
                fn (?string $url) => str_contains((string) $url, 'search=Oat'),
            ),
        );
});

test('a filtered list reports only the rows that match, not the whole catalogue', function () {
    [$user, $distributor] = newOrganizationMember();
    $connection = newSupplierConnection($distributor);

    Product::factory()->count(30)->for($distributor)->create([
        'name' => 'Oat Milk',
        'supplier_connection_id' => $connection->id,
    ]);

    Product::factory()->for($distributor)->create([
        'name' => 'Rice Milk',
        'supplier_connection_id' => $connection->id,
    ]);

    paginatedProducts($user, $distributor, ['search' => 'Rice'])
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('products.data', 1)
            ->where('products.total', 1)
            ->where('products.last_page', 1)
            ->where('hasProducts', true),
        );
});

test('a supplier list is paginated the same way', function () {
    [$supplierUser, $supplier] = newSupplierMember();
    [, $distributor] = newOrganizationMember();

    $connection = newSupplierConnection($distributor, $supplier);

    $perPage = ProductFilters::PAGE_SIZES[0];

    Product::factory()
        ->count($perPage + 1)
        ->for($distributor)
        ->create(['supplier_connection_id' => $connection->id]);

    paginatedProducts($supplierUser, $supplier)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('products.data', $perPage)
            ->where('products.total', $perPage + 1)
            ->where('products.last_page', 2),
        );
});
