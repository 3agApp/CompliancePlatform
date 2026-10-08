<?php

use App\Models\Product;

test('a distributor finds products by name, article number or EAN, with their suppliers and brands', function () {
    [$user, $distributor] = newOrganizationMember();
    $connection = newSupplierConnection($distributor, attributes: ['company_name' => 'Magna Supplies']);

    $product = Product::factory()->for($distributor)->create([
        'name' => 'Wooden Train',
        'ean' => '4006381333931',
        'supplier_connection_id' => $connection->id,
    ]);
    carriedBrand($connection, 'Magna-Tiles');

    $this->actingAs($user)
        ->getJson(route('search', ['current_organization' => $distributor->slug, 'q' => '400638']))
        ->assertOk()
        ->assertJsonPath('productsTotal', 1)
        ->assertJsonPath('products.0.id', $product->id)
        ->assertJsonPath('products.0.counterparty', 'Magna Supplies')
        ->assertJsonPath('products.0.reviewStatus', 'draft');

    $this->actingAs($user)
        ->getJson(route('search', ['current_organization' => $distributor->slug, 'q' => 'magna']))
        ->assertOk()
        ->assertJsonPath('connections.0.id', $connection->id)
        ->assertJsonPath('connections.0.url', route('products.index', ['current_organization' => $distributor->slug, 'connection' => $connection->id]))
        ->assertJsonPath('brands.0.name', 'Magna-Tiles')
        ->assertJsonPath('brands.0.counterparty', 'Magna Supplies');
});

test('the search sees only what the organization can see', function () {
    [$user, $distributor] = newOrganizationMember();
    [, $other] = newOrganizationMember();

    Product::factory()->for($other)->create([
        'name' => 'Wooden Train',
        'supplier_connection_id' => newSupplierConnection($other)->id,
    ]);

    $this->actingAs($user)
        ->getJson(route('search', ['current_organization' => $distributor->slug, 'q' => 'wooden']))
        ->assertOk()
        ->assertJsonPath('productsTotal', 0)
        ->assertJsonCount(0, 'products');
});

test('a supplier finds only the products assigned to it, named with the distributor', function () {
    [, $distributor] = newOrganizationMember(organizationAttributes: ['name' => 'Alpine Trading AG']);
    [$supplierUser, $supplier] = newSupplierMember();

    $connection = newSupplierConnection($distributor, $supplier);

    Product::factory()->for($distributor)->create(['name' => 'Wooden Train', 'supplier_connection_id' => $connection->id]);
    Product::factory()->for($distributor)->create(['name' => 'Wooden Boat', 'supplier_connection_id' => newSupplierConnection($distributor)->id]);

    $this->actingAs($supplierUser)
        ->getJson(route('search', ['current_organization' => $supplier->slug, 'q' => 'wooden']))
        ->assertOk()
        ->assertJsonPath('productsTotal', 1)
        ->assertJsonPath('products.0.name', 'Wooden Train')
        ->assertJsonPath('products.0.counterparty', 'Alpine Trading AG');
});

test('a term too short to mean anything finds nothing without asking the database', function () {
    [$user, $distributor] = newOrganizationMember();

    $this->actingAs($user)
        ->getJson(route('search', ['current_organization' => $distributor->slug, 'q' => 'a']))
        ->assertOk()
        ->assertExactJson(['products' => [], 'productsTotal' => 0, 'connections' => [], 'brands' => []]);
});
