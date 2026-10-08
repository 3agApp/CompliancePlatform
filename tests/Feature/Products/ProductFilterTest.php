<?php

use App\Enums\ProductReviewStatus;
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

    filteredProducts($user, $distributor, ['connection' => $acme->id])
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('products.data', 2)
            ->where('products.data.0.name', 'Oat Milk')
            ->where('products.data.1.name', 'Rice Milk')
            ->where('filters.connection', $acme->id),
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

    filteredProducts($supplierUser, $supplier, ['connection' => $connectionA->id])
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('products.data', 1)
            ->where('products.data.0.name', 'Oat Milk')
            ->where('products.data.0.counterparty', $distributorA->name),
        );
});

test('a distributor filters the product list by one of its categories', function () {
    [$user, $distributor] = newOrganizationMember();
    $connection = newSupplierConnection($distributor);

    $magnetic = $distributor->productCategories()->where('name', 'Magnetic toy')->sole();
    $filter = $distributor->productCategories()->where('name', 'Filter')->sole();

    Product::factory()->for($distributor)->inCategory($magnetic)->create([
        'name' => 'Magna-Tiles 32',
        'supplier_connection_id' => $connection->id,
    ]);
    Product::factory()->for($distributor)->inCategory($filter)->create([
        'name' => 'Water Filter Cartridge',
        'supplier_connection_id' => $connection->id,
    ]);
    Product::factory()->for($distributor)->create([
        'name' => 'Uncategorised Thing',
        'supplier_connection_id' => $connection->id,
    ]);

    filteredProducts($user, $distributor, ['category' => (string) $magnetic->id])
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('products.data', 1)
            ->where('products.data.0.name', 'Magna-Tiles 32')
            ->where('filters.category', $magnetic->id),
        );
});

test('a distributor is offered its whole category list to filter by', function () {
    [$user, $distributor] = newOrganizationMember();

    filteredProducts($user, $distributor, [])
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('filterableCategories', 3)
            ->where('filterableCategories.0.label', 'Filter')
            ->where('filterableCategories.2.label', 'Toy'),
        );
});

test('the category filter composes with the supplier filter', function () {
    [$user, $distributor] = newOrganizationMember();

    $acme = newSupplierConnection($distributor, attributes: ['company_name' => 'Acme Supplies']);
    $other = newSupplierConnection($distributor, attributes: ['contact_email' => 'other@supplier.test']);

    $magnetic = $distributor->productCategories()->where('name', 'Magnetic toy')->sole();

    Product::factory()->for($distributor)->inCategory($magnetic)->create([
        'name' => 'Magna-Tiles 32',
        'supplier_connection_id' => $acme->id,
    ]);
    Product::factory()->for($distributor)->inCategory($magnetic)->create([
        'name' => 'Magnetic Letters',
        'supplier_connection_id' => $other->id,
    ]);

    filteredProducts($user, $distributor, [
        'category' => (string) $magnetic->id,
        'connection' => (string) $acme->id,
    ])
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('products.data', 1)
            ->where('products.data.0.name', 'Magna-Tiles 32'),
        );
});

test('a supplier filters by a category of one of its distributors', function () {
    [$supplierUser, $supplier] = newSupplierMember();
    [, $distributorA] = newOrganizationMember(organizationAttributes: ['name' => 'Alpine Trading AG']);
    [, $distributorB] = newOrganizationMember(organizationAttributes: ['name' => 'Coop Trading AG']);

    $connectionA = newSupplierConnection($distributorA, $supplier);
    $connectionB = newSupplierConnection($distributorB, $supplier);

    $magneticA = $distributorA->productCategories()->where('name', 'Magnetic toy')->sole();
    $magneticB = $distributorB->productCategories()->where('name', 'Magnetic toy')->sole();

    Product::factory()->for($distributorA)->inCategory($magneticA)->create([
        'name' => 'Magna-Tiles 32',
        'supplier_connection_id' => $connectionA->id,
    ]);
    Product::factory()->for($distributorB)->inCategory($magneticB)->create([
        'name' => 'Magnetic Letters',
        'supplier_connection_id' => $connectionB->id,
    ]);

    /**
     * The two distributors both call the family "Magnetic toy" and they are
     * still two different rows, so the options say which distributor each one
     * came from and filtering by one never reaches the other.
     */
    filteredProducts($supplierUser, $supplier, [])
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('filterableCategories', 2)
            ->where('filterableCategories.0.label', 'Magnetic toy (Alpine Trading AG)')
            ->where('filterableCategories.1.label', 'Magnetic toy (Coop Trading AG)'),
        );

    filteredProducts($supplierUser, $supplier, ['category' => (string) $magneticA->id])
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('products.data', 1)
            ->where('products.data.0.name', 'Magna-Tiles 32'),
        );
});

test('filtering by another organization category shows no products', function () {
    [$user, $distributor] = newOrganizationMember();
    [, $otherDistributor] = newOrganizationMember();

    $connection = newSupplierConnection($distributor);
    $own = $distributor->productCategories()->where('name', 'Toy')->sole();
    $foreign = $otherDistributor->productCategories()->where('name', 'Toy')->sole();

    Product::factory()->for($distributor)->inCategory($own)->create([
        'name' => 'Wooden Train',
        'supplier_connection_id' => $connection->id,
    ]);

    filteredProducts($user, $distributor, ['category' => (string) $foreign->id])
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('products.data', 0));
});

test('a category filter that names no row is treated as no filter', function (string $category) {
    [$user, $distributor] = newOrganizationMember();
    $connection = newSupplierConnection($distributor);

    Product::factory()->count(2)->for($distributor)->create([
        'supplier_connection_id' => $connection->id,
    ]);

    filteredProducts($user, $distributor, ['category' => $category])
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('products.data', 2)
            ->where('filters.category', null),
        );
})->with([
    'blank' => '',
    'not a number' => 'magnetic-toy',
    'a leftover uuid' => '9f8d4c2e-1a3b-4c5d-8e9f-0a1b2c3d4e5f',
    'zero' => '0',
]);

test('a distributor filters the product list by one of its brands', function () {
    [$user, $distributor] = newOrganizationMember();
    $connection = newSupplierConnection($distributor);

    $magnaTiles = carriedBrand($connection, 'Magna-Tiles');
    $tigerbox = carriedBrand($connection, 'tigerbox');

    Product::factory()->for($distributor)->ofBrand($magnaTiles)->create([
        'name' => 'Clear Colors 32',
        'supplier_connection_id' => $connection->id,
    ]);
    Product::factory()->for($distributor)->ofBrand($tigerbox)->create([
        'name' => 'tigercard Bundle',
        'supplier_connection_id' => $connection->id,
    ]);
    Product::factory()->for($distributor)->create([
        'name' => 'Unbranded Thing',
        'supplier_connection_id' => $connection->id,
    ]);

    filteredProducts($user, $distributor, ['brand' => (string) $magnaTiles->id])
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('products.data', 1)
            ->where('products.data.0.name', 'Clear Colors 32')
            ->where('products.data.0.brand_label', 'Magna-Tiles')
            ->where('filters.brand', $magnaTiles->id),
        );
});

test('a distributor is offered its whole brand list to filter by', function () {
    [$user, $distributor] = newOrganizationMember();
    $connection = newSupplierConnection($distributor);

    carriedBrand($connection, 'tigerbox');
    carriedBrand($connection, 'Magna-Tiles');

    filteredProducts($user, $distributor, [])
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('filterableBrands', 2)
            ->where('filterableBrands.0.label', 'Magna-Tiles')
            ->where('filterableBrands.1.label', 'tigerbox'),
        );
});

test('the brand filter composes with the category filter', function () {
    [$user, $distributor] = newOrganizationMember();
    $connection = newSupplierConnection($distributor);

    $magnaTiles = carriedBrand($connection, 'Magna-Tiles');
    $magnetic = $distributor->productCategories()->where('name', 'Magnetic toy')->sole();
    $toy = $distributor->productCategories()->where('name', 'Toy')->sole();

    Product::factory()->for($distributor)->ofBrand($magnaTiles)->inCategory($magnetic)->create([
        'name' => 'Clear Colors 32',
        'supplier_connection_id' => $connection->id,
    ]);
    Product::factory()->for($distributor)->ofBrand($magnaTiles)->inCategory($toy)->create([
        'name' => 'Plain Blocks',
        'supplier_connection_id' => $connection->id,
    ]);

    filteredProducts($user, $distributor, [
        'brand' => (string) $magnaTiles->id,
        'category' => (string) $magnetic->id,
    ])
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('products.data', 1)
            ->where('products.data.0.name', 'Clear Colors 32'),
        );
});

test('a supplier is offered the brands on the products assigned to it, named with the distributor', function () {
    [$supplierUser, $supplier] = newSupplierMember();
    [, $distributorA] = newOrganizationMember(organizationAttributes: ['name' => 'Alpine Trading AG']);
    [, $distributorB] = newOrganizationMember(organizationAttributes: ['name' => 'Coop Trading AG']);

    $connectionA = newSupplierConnection($distributorA, $supplier);
    $connectionB = newSupplierConnection($distributorB, $supplier);

    $brandA = carriedBrand($connectionA, 'Magna-Tiles');
    $brandB = carriedBrand($connectionB, 'Magna-Tiles');
    carriedBrand($connectionA, 'Nothing Assigned');

    Product::factory()->for($distributorA)->ofBrand($brandA)->create([
        'name' => 'Clear Colors 32',
        'supplier_connection_id' => $connectionA->id,
    ]);
    Product::factory()->for($distributorB)->ofBrand($brandB)->create([
        'name' => 'Stardust 15',
        'supplier_connection_id' => $connectionB->id,
    ]);

    filteredProducts($supplierUser, $supplier, [])
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('filterableBrands', 2)
            ->where('filterableBrands.0.label', 'Magna-Tiles (Alpine Trading AG)')
            ->where('filterableBrands.1.label', 'Magna-Tiles (Coop Trading AG)'),
        );

    filteredProducts($supplierUser, $supplier, ['brand' => (string) $brandA->id])
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('products.data', 1)
            ->where('products.data.0.name', 'Clear Colors 32'),
        );
});

test('filtering by another organization brand shows no products', function () {
    [$user, $distributor] = newOrganizationMember();
    [, $otherDistributor] = newOrganizationMember();

    $connection = newSupplierConnection($distributor);
    $own = carriedBrand($connection, 'Magna-Tiles');
    $foreign = carriedBrand(newSupplierConnection($otherDistributor), 'Magna-Tiles');

    Product::factory()->for($distributor)->ofBrand($own)->create([
        'name' => 'Clear Colors 32',
        'supplier_connection_id' => $connection->id,
    ]);

    filteredProducts($user, $distributor, ['brand' => (string) $foreign->id])
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('products.data', 0));
});

test('the product search matches a product name', function () {
    [$user, $distributor] = newOrganizationMember();
    $connection = newSupplierConnection($distributor);

    Product::factory()->for($distributor)->create(['name' => 'Organic Oat Milk', 'supplier_connection_id' => $connection->id]);
    Product::factory()->for($distributor)->create(['name' => 'Whipping Cream', 'supplier_connection_id' => $connection->id]);

    filteredProducts($user, $distributor, ['search' => 'Oat'])
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('products.data', 1)
            ->where('products.data.0.name', 'Organic Oat Milk')
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
            ->has('products.data', 1)
            ->where('products.data.0.name', 'Organic Oat Milk'),
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
            ->has('products.data', 1)
            ->where('products.data.0.name', 'Organic Oat Milk'),
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
        ->assertInertia(fn (Assert $page) => $page->has('products.data', 0));
});

test('the product search ignores case', function () {
    [$user, $distributor] = newOrganizationMember();
    $connection = newSupplierConnection($distributor);

    Product::factory()->for($distributor)->create(['name' => 'Organic Oat Milk', 'supplier_connection_id' => $connection->id]);

    filteredProducts($user, $distributor, ['search' => 'oat'])
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('products.data', 1));
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
            ->has('products.data', 1)
            ->where('products.data.0.id', $own->id),
        );

    // Only the other distributor's barcode matches. An ungrouped OR in the
    // search scope escapes the organization clause and leaks it.
    filteredProducts($user, $distributor, ['search' => '400638'])
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('products.data', 0));
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
        ->assertInertia(fn (Assert $page) => $page->has('products.data', 0));
});

test('a distributor filtering by another distributor connection sees no products', function () {
    [$user, $distributor] = newOrganizationMember();
    [, $otherDistributor] = newOrganizationMember();

    $connection = newSupplierConnection($distributor);
    $foreignConnection = newSupplierConnection($otherDistributor);

    Product::factory()->for($distributor)->create(['supplier_connection_id' => $connection->id]);
    Product::factory()->for($otherDistributor)->create(['supplier_connection_id' => $foreignConnection->id]);

    filteredProducts($user, $distributor, ['connection' => $foreignConnection->id])
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('products.data', 0));
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

    filteredProducts($supplierUser, $supplier, ['connection' => $foreignConnection->id])
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('products.data', 0));
});

test('a blank search is treated as no filter', function () {
    [$user, $distributor] = newOrganizationMember();
    $connection = newSupplierConnection($distributor);

    Product::factory()->count(2)->for($distributor)->create(['supplier_connection_id' => $connection->id]);

    filteredProducts($user, $distributor, ['search' => '   '])
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('products.data', 2)
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
            ->has('products.data', 0)
            ->where('hasProducts', true),
        );
});

test('a distributor can filter by a revoked connection that still holds products', function () {
    [$user, $distributor] = newOrganizationMember();
    [, $supplier] = newSupplierMember();

    $connection = newSupplierConnection($distributor, $supplier);
    Product::factory()->for($distributor)->create(['name' => 'Oat Milk', 'supplier_connection_id' => $connection->id]);

    $connection->update(['status' => SupplierConnectionStatus::Revoked]);

    filteredProducts($user, $distributor, ['connection' => $connection->id])
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('products.data', 1)
            ->has('counterparties', 1)
            ->where('counterparties.0.id', $connection->id),
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

/**
 * The status tabs count what the other filters let through, so each one
 * says how many it would show -- whichever tab is open.
 */
test('the status tabs count the products under every filter but the status', function () {
    [$user, $distributor] = newOrganizationMember();

    $acme = newSupplierConnection($distributor, attributes: ['company_name' => 'Acme Supplies']);
    $other = newSupplierConnection($distributor, attributes: ['contact_email' => 'other@supplier.test']);

    Product::factory()->count(2)->for($distributor)->create(['supplier_connection_id' => $acme->id]);
    Product::factory()->for($distributor)->reviewed(ProductReviewStatus::Approved)->create(['supplier_connection_id' => $acme->id]);
    Product::factory()->count(4)->for($distributor)->create(['supplier_connection_id' => $other->id]);

    filteredProducts($user, $distributor, ['connection' => $acme->id, 'status' => 'approved'])
        ->assertInertia(fn (Assert $page) => $page
            ->has('products.data', 1)
            ->where('statusCounts', [
                'draft' => 2,
                'in_review' => 0,
                'approved' => 1,
                'changes_requested' => 0,
            ]),
        );
});

test('the product list reads the most recently changed products first when asked', function () {
    [$user, $distributor] = newOrganizationMember();
    $connection = newSupplierConnection($distributor);

    Product::factory()->for($distributor)->create(['name' => 'Almond Milk', 'supplier_connection_id' => $connection->id, 'updated_at' => now()->subDays(3)]);
    Product::factory()->for($distributor)->create(['name' => 'Zebra Crackers', 'supplier_connection_id' => $connection->id, 'updated_at' => now()]);

    filteredProducts($user, $distributor, ['sort' => 'updated'])
        ->assertInertia(fn (Assert $page) => $page
            ->where('products.data.0.name', 'Zebra Crackers')
            ->where('filters.sort', 'updated'),
        );

    /** An order the list does not offer reads it alphabetically. */
    filteredProducts($user, $distributor, ['sort' => 'price'])
        ->assertInertia(fn (Assert $page) => $page
            ->where('products.data.0.name', 'Almond Milk')
            ->where('filters.sort', 'name'),
        );
});
