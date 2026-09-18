<?php

use App\Enums\SupplierConnectionStatus;
use App\Models\Organization;
use App\Models\Product;
use App\Models\User;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Visit the product list with a query string.
 *
 * @param  array<string, string>  $filters
 */
function filteredProducts(User $user, Organization $organization, array $filters): TestResponse
{
    return test()
        ->actingAs($user)
        ->get(route('products.index', ['current_organization' => $organization->slug, ...$filters]));
}

test('a distributor filters the product list by one of its suppliers', function () {
    [$user, $distributor] = newOrganizationMember();

    $acme = newSupplierConnection($distributor, attributes: ['company_name' => 'Acme Supplies']);
    $other = newSupplierConnection($distributor, attributes: ['contact_email' => 'other@supplier.test']);

    Product::factory()->for($distributor)->create(['name' => 'Oat Milk', 'supplier_connection_id' => $acme->id]);
    Product::factory()->for($distributor)->create(['name' => 'Rice Milk', 'supplier_connection_id' => $acme->id]);
    Product::factory()->for($distributor)->create(['name' => 'Soy Milk', 'supplier_connection_id' => $other->id]);

    filteredProducts($user, $distributor, ['connection' => $acme->uuid])
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('products', 2)
            ->where('products.0.name', 'Oat Milk')
            ->where('products.1.name', 'Rice Milk')
            ->where('filters.connection', $acme->uuid),
        );
});

test('a supplier filters the product list by one of its distributors', function () {
    [$supplierUser, $supplier] = newSupplierMember();
    [, $distributorA] = newOrganizationMember();
    [, $distributorB] = newOrganizationMember();

    $connectionA = newSupplierConnection($distributorA, $supplier);
    $connectionB = newSupplierConnection($distributorB, $supplier);

    Product::factory()->for($distributorA)->create(['name' => 'Oat Milk', 'supplier_connection_id' => $connectionA->id]);
    Product::factory()->for($distributorB)->create(['name' => 'Rice Milk', 'supplier_connection_id' => $connectionB->id]);

    filteredProducts($supplierUser, $supplier, ['connection' => $connectionA->uuid])
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('products', 1)
            ->where('products.0.name', 'Oat Milk')
            ->where('products.0.counterparty', $distributorA->name),
        );
});

test('the product search matches a product name', function () {
    [$user, $distributor] = newOrganizationMember();
    $connection = newSupplierConnection($distributor);

    Product::factory()->for($distributor)->create(['name' => 'Organic Oat Milk', 'supplier_connection_id' => $connection->id]);
    Product::factory()->for($distributor)->create(['name' => 'Whipping Cream', 'supplier_connection_id' => $connection->id]);

    filteredProducts($user, $distributor, ['search' => 'Oat'])
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('products', 1)
            ->where('products.0.name', 'Organic Oat Milk')
            ->where('filters.search', 'Oat'),
        );
});

test('the product search matches a barcode', function () {
    [$user, $distributor] = newOrganizationMember();
    $connection = newSupplierConnection($distributor);

    Product::factory()->for($distributor)->create([
        'name' => 'Organic Oat Milk',
        'ean' => '4006381333931',
        'supplier_connection_id' => $connection->id,
    ]);
    Product::factory()->for($distributor)->create([
        'name' => 'Whipping Cream',
        'ean' => '7610200416016',
        'supplier_connection_id' => $connection->id,
    ]);

    filteredProducts($user, $distributor, ['search' => '400638'])
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('products', 1)
            ->where('products.0.name', 'Organic Oat Milk'),
        );
});

test('the product search matches an article number from either side', function (string $field, string $term) {
    [$user, $distributor] = newOrganizationMember();
    $connection = newSupplierConnection($distributor);

    Product::factory()->for($distributor)->create([
        'name' => 'Organic Oat Milk',
        'internal_article_number' => 'ART-10294',
        'supplier_article_number' => 'MT-BLUE-32',
        'supplier_connection_id' => $connection->id,
        $field => $term,
    ]);
    Product::factory()->for($distributor)->create([
        'name' => 'Whipping Cream',
        'internal_article_number' => 'ART-55555',
        'supplier_article_number' => 'MT-RED-11',
        'supplier_connection_id' => $connection->id,
    ]);

    filteredProducts($user, $distributor, ['search' => $term])
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('products', 1)
            ->where('products.0.name', 'Organic Oat Milk'),
        );
})->with([
    'internal article number' => ['internal_article_number', 'ART-10294'],
    'supplier article number' => ['supplier_article_number', 'MT-BLUE-32'],
]);

test('the product search by article number never reaches another organization products', function () {
    [$user, $distributor] = newOrganizationMember();
    [, $otherDistributor] = newOrganizationMember();

    $connection = newSupplierConnection($distributor);
    $otherConnection = newSupplierConnection($otherDistributor);

    Product::factory()->for($distributor)->create([
        'internal_article_number' => 'ART-00001',
        'supplier_article_number' => 'SUP-00001',
        'supplier_connection_id' => $connection->id,
    ]);
    Product::factory()->for($otherDistributor)->create([
        'internal_article_number' => 'ART-10294',
        'supplier_article_number' => 'SUP-10294',
        'supplier_connection_id' => $otherConnection->id,
    ]);

    filteredProducts($user, $distributor, ['search' => 'ART-10294'])
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('products', 0));
});

test('the product search ignores case', function () {
    [$user, $distributor] = newOrganizationMember();
    $connection = newSupplierConnection($distributor);

    Product::factory()->for($distributor)->create(['name' => 'Organic Oat Milk', 'supplier_connection_id' => $connection->id]);

    filteredProducts($user, $distributor, ['search' => 'oat'])
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('products', 1));
});

test('the product search never reaches another organization products', function () {
    [$user, $distributor] = newOrganizationMember();
    [, $otherDistributor] = newOrganizationMember();

    $connection = newSupplierConnection($distributor);
    $otherConnection = newSupplierConnection($otherDistributor);

    $own = Product::factory()->for($distributor)->create([
        'name' => 'Oat Milk',
        'ean' => '1111111111111',
        'supplier_connection_id' => $connection->id,
    ]);
    Product::factory()->for($otherDistributor)->create([
        'name' => 'Oat Milk',
        'ean' => '4006381333931',
        'supplier_connection_id' => $otherConnection->id,
    ]);

    filteredProducts($user, $distributor, ['search' => 'Oat'])
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('products', 1)
            ->where('products.0.uuid', $own->uuid),
        );

    // Only the other distributor's barcode matches. An ungrouped OR in the
    // search scope escapes the organization clause and leaks it.
    filteredProducts($user, $distributor, ['search' => '400638'])
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('products', 0));
});

test('the product search never reaches another supplier products', function () {
    [$supplierUser, $supplier] = newSupplierMember();
    [, $otherSupplier] = newSupplierMember();
    [, $distributor] = newOrganizationMember();

    $connection = newSupplierConnection($distributor, $supplier);
    $otherConnection = newSupplierConnection($distributor, $otherSupplier, ['contact_email' => 'other@supplier.test']);

    Product::factory()->for($distributor)->create([
        'name' => 'Oat Milk',
        'ean' => '1111111111111',
        'supplier_connection_id' => $connection->id,
    ]);
    Product::factory()->for($distributor)->create([
        'name' => 'Rice Milk',
        'ean' => '4006381333931',
        'supplier_connection_id' => $otherConnection->id,
    ]);

    filteredProducts($supplierUser, $supplier, ['search' => '400638'])
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('products', 0));
});

test('a distributor filtering by another distributor connection sees no products', function () {
    [$user, $distributor] = newOrganizationMember();
    [, $otherDistributor] = newOrganizationMember();

    $connection = newSupplierConnection($distributor);
    $foreignConnection = newSupplierConnection($otherDistributor);

    Product::factory()->for($distributor)->create(['supplier_connection_id' => $connection->id]);
    Product::factory()->for($otherDistributor)->create(['supplier_connection_id' => $foreignConnection->id]);

    filteredProducts($user, $distributor, ['connection' => $foreignConnection->uuid])
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('products', 0));
});

test('a supplier filtering by a connection it does not hold sees no products', function () {
    [$supplierUser, $supplier] = newSupplierMember();
    [, $distributor] = newOrganizationMember();
    [, $otherDistributor] = newOrganizationMember();
    [, $otherSupplier] = newSupplierMember();

    $connection = newSupplierConnection($distributor, $supplier);
    $foreignConnection = newSupplierConnection($otherDistributor, $otherSupplier);

    Product::factory()->for($distributor)->create(['supplier_connection_id' => $connection->id]);
    Product::factory()->for($otherDistributor)->create(['supplier_connection_id' => $foreignConnection->id]);

    filteredProducts($supplierUser, $supplier, ['connection' => $foreignConnection->uuid])
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('products', 0));
});

test('a blank search is treated as no filter', function () {
    [$user, $distributor] = newOrganizationMember();
    $connection = newSupplierConnection($distributor);

    Product::factory()->count(2)->for($distributor)->create(['supplier_connection_id' => $connection->id]);

    filteredProducts($user, $distributor, ['search' => '   '])
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('products', 2)
            ->where('filters.search', null),
        );
});

test('the product list reports whether the organization has any products at all', function () {
    [$user, $distributor] = newOrganizationMember();
    $connection = newSupplierConnection($distributor);

    filteredProducts($user, $distributor, [])
        ->assertInertia(fn (Assert $page) => $page->where('hasProducts', false));

    Product::factory()->for($distributor)->create(['name' => 'Oat Milk', 'supplier_connection_id' => $connection->id]);

    filteredProducts($user, $distributor, ['search' => 'nothing matches this'])
        ->assertInertia(fn (Assert $page) => $page
            ->has('products', 0)
            ->where('hasProducts', true),
        );
});

test('a distributor can filter by a revoked connection that still holds products', function () {
    [$user, $distributor] = newOrganizationMember();
    [, $supplier] = newSupplierMember();

    $connection = newSupplierConnection($distributor, $supplier);
    Product::factory()->for($distributor)->create(['name' => 'Oat Milk', 'supplier_connection_id' => $connection->id]);

    $connection->update(['status' => SupplierConnectionStatus::Revoked]);

    filteredProducts($user, $distributor, ['connection' => $connection->uuid])
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('products', 1)
            ->has('counterparties', 1)
            ->where('counterparties.0.uuid', $connection->uuid),
        );
});

test('a supplier is offered its active distributors as filter options', function () {
    [$supplierUser, $supplier] = newSupplierMember();
    [, $distributorA] = newOrganizationMember(organizationAttributes: ['name' => 'Alpine Trading AG']);
    [, $distributorB] = newOrganizationMember(organizationAttributes: ['name' => 'Coop Trading AG']);

    newSupplierConnection($distributorA, $supplier);
    $revoked = newSupplierConnection($distributorB, $supplier);
    $revoked->update(['status' => SupplierConnectionStatus::Revoked]);

    filteredProducts($supplierUser, $supplier, [])
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('counterparties', 1)
            ->where('counterparties.0.label', 'Alpine Trading AG'),
        );
});
