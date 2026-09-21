<?php

use App\Enums\OrganizationRole;
use App\Enums\SupplierConnectionStatus;
use App\Models\Product;
use Inertia\Testing\AssertableInertia as Assert;

test('two distributors sharing one supplier never see each other products', function () {
    [$supplierUser, $supplier] = newSupplierMember();
    [$userA, $distributorA] = newOrganizationMember();
    [, $distributorB] = newOrganizationMember();

    $connectionA = newSupplierConnection($distributorA, $supplier);
    $connectionB = newSupplierConnection($distributorB, $supplier);

    $productA = Product::factory()->for($distributorA)->create([
        'name' => 'Oat Milk',
        'supplier_connection_id' => $connectionA->id,
    ]);
    $productB = Product::factory()->for($distributorB)->create([
        'name' => 'Rice Milk',
        'supplier_connection_id' => $connectionB->id,
    ]);

    $this
        ->actingAs($supplierUser)
        ->get(route('products.index', ['current_organization' => $supplier->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('products.data', 2)
            ->where('viewerType', 'supplier')
            ->where('products.data.0.name', 'Oat Milk')
            ->where('products.data.0.counterparty', $distributorA->name)
            ->where('products.data.1.name', 'Rice Milk')
            ->where('products.data.1.counterparty', $distributorB->name),
        );

    $this
        ->actingAs($userA)
        ->get(route('products.index', ['current_organization' => $distributorA->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('products.data', 1)
            ->where('products.data.0.id', $productA->id),
        );

    $this
        ->actingAs($userA)
        ->get(route('products.edit', ['current_organization' => $distributorA->slug, 'product' => $productB->id]))
        ->assertNotFound();
});

test('a product assigned to an unclaimed connection becomes visible once the connection is accepted', function () {
    [, $distributor] = newOrganizationMember();
    [$supplierUser, $supplier] = newSupplierMember();

    $connection = newSupplierConnection($distributor, attributes: ['contact_email' => $supplierUser->email]);

    $product = Product::factory()->for($distributor)->create([
        'supplier_connection_id' => $connection->id,
    ]);

    $this
        ->actingAs($supplierUser)
        ->get(route('products.index', ['current_organization' => $supplier->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('products.data', 0));

    $this
        ->actingAs($supplierUser)
        ->post(route('connections.store', ['connection' => $connection->code]), [
            'mode' => 'existing',
            'organization' => $supplier->slug,
        ])
        ->assertSessionHasNoErrors();

    $this
        ->actingAs($supplierUser)
        ->get(route('products.index', ['current_organization' => $supplier->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('products.data', 1)
            ->where('products.data.0.id', $product->id),
        );

    expect($product->fresh())
        ->supplier_connection_id->toBe($connection->id)
        ->organization_id->toBe($distributor->id);
});

test('a pending connection does not expose products to the supplier organization', function () {
    [, $distributor] = newOrganizationMember();
    [$supplierUser, $supplier] = newSupplierMember();

    $connection = newSupplierConnection($distributor);
    $connection->update(['supplier_organization_id' => $supplier->id]);

    $product = Product::factory()->for($distributor)->create([
        'supplier_connection_id' => $connection->id,
    ]);

    $this
        ->actingAs($supplierUser)
        ->get(route('products.index', ['current_organization' => $supplier->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('products.data', 0));

    $this
        ->actingAs($supplierUser)
        ->get(route('products.edit', ['current_organization' => $supplier->slug, 'product' => $product->id]))
        ->assertNotFound();
});

test('a supplier can update an assigned product', function () {
    [$supplierUser, $supplier] = newSupplierMember();
    [, $distributor] = newOrganizationMember();

    $connection = newSupplierConnection($distributor, $supplier);
    $product = Product::factory()->for($distributor)->create([
        'name' => 'Oat Milk',
        'supplier_connection_id' => $connection->id,
    ]);

    $this
        ->actingAs($supplierUser)
        ->patch(route('products.update', ['current_organization' => $supplier->slug, 'product' => $product->id]), [
            'name' => 'Organic Oat Milk',
            'product_category_id' => $product->product_category_id,
            'product_template_id' => $product->product_template_id,
            'ean' => '4006381333931',
            'country_of_origin' => 'DE',
        ])
        ->assertSessionHasNoErrors();

    expect($product->fresh())
        ->name->toBe('Organic Oat Milk')
        ->ean->toBe('4006381333931');
});

test('a supplier cannot delete an assigned product', function () {
    [$supplierUser, $supplier] = newSupplierMember();
    [, $distributor] = newOrganizationMember();

    $connection = newSupplierConnection($distributor, $supplier);
    $product = Product::factory()->for($distributor)->create([
        'supplier_connection_id' => $connection->id,
    ]);

    $this
        ->actingAs($supplierUser)
        ->get(route('products.index', ['current_organization' => $supplier->slug]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('permissions.canCreateProduct', false)
            ->where('permissions.canDeleteProduct', false)
            ->where('permissions.canUpdateProduct', true),
        );

    $this
        ->actingAs($supplierUser)
        ->delete(route('products.destroy', ['current_organization' => $supplier->slug, 'product' => $product->id]))
        ->assertForbidden();

    $this->assertModelExists($product);
});

test('a supplier cannot reassign a product to another connection', function () {
    [$supplierUser, $supplier] = newSupplierMember();
    [, $distributor] = newOrganizationMember();

    $connection = newSupplierConnection($distributor, $supplier);
    $otherConnection = newSupplierConnection($distributor);

    $product = Product::factory()->for($distributor)->create([
        'supplier_connection_id' => $connection->id,
    ]);

    $this
        ->actingAs($supplierUser)
        ->patch(route('products.update', ['current_organization' => $supplier->slug, 'product' => $product->id]), [
            'name' => 'Oat Milk',
            'product_category_id' => $product->product_category_id,
            'product_template_id' => $product->product_template_id,
            'supplier_connection_id' => $otherConnection->id,
        ])
        ->assertSessionHasNoErrors();

    expect($product->fresh()->supplier_connection_id)->toBe($connection->id);
});

test('a supplier cannot create products', function () {
    [$supplierUser, $supplier] = newSupplierMember();

    $this
        ->actingAs($supplierUser)
        ->post(route('products.store', ['current_organization' => $supplier->slug]), ['name' => 'Oat Milk'])
        ->assertForbidden();

    $this->assertDatabaseCount('products', 0);
});

test('a distributor cannot assign a product to another distributor connection', function () {
    [$user, $distributor] = newOrganizationMember();
    [, $otherDistributor] = newOrganizationMember();

    $connection = newSupplierConnection($distributor);
    $foreignConnection = newSupplierConnection($otherDistributor);

    $this
        ->actingAs($user)
        ->post(route('products.store', ['current_organization' => $distributor->slug]), [
            'name' => 'Oat Milk',
            'supplier_connection_id' => $foreignConnection->id,
        ])
        ->assertSessionHasErrors('supplier_connection_id');

    $this->assertDatabaseCount('products', 0);

    $this
        ->actingAs($user)
        ->post(route('products.store', ['current_organization' => $distributor->slug]), [
            'name' => 'Oat Milk',
            'product_category_id' => legalFamily($distributor)->id,
            'product_template_id' => familyTemplate($distributor)->id,
            'supplier_connection_id' => $connection->id,
        ])
        ->assertSessionHasNoErrors();
});

test('a supplier member can view but not update an assigned product', function () {
    [$supplierUser, $supplier] = newSupplierMember(OrganizationRole::Member);
    [, $distributor] = newOrganizationMember();

    $connection = newSupplierConnection($distributor, $supplier);
    $product = Product::factory()->for($distributor)->create([
        'name' => 'Oat Milk',
        'supplier_connection_id' => $connection->id,
    ]);

    $this
        ->actingAs($supplierUser)
        ->get(route('products.edit', ['current_organization' => $supplier->slug, 'product' => $product->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('permissions.canUpdateProduct', false));

    $this
        ->actingAs($supplierUser)
        ->patch(route('products.update', ['current_organization' => $supplier->slug, 'product' => $product->id]), [
            'name' => 'Organic Oat Milk',
        ])
        ->assertForbidden();

    expect($product->fresh()->name)->toBe('Oat Milk');
});

test('a product of a revoked connection returns 404 for the supplier', function () {
    [$supplierUser, $supplier] = newSupplierMember();
    [, $distributor] = newOrganizationMember();

    $connection = newSupplierConnection($distributor, $supplier);
    $product = Product::factory()->for($distributor)->create([
        'supplier_connection_id' => $connection->id,
    ]);

    $connection->update(['status' => SupplierConnectionStatus::Revoked]);

    $this
        ->actingAs($supplierUser)
        ->get(route('products.edit', ['current_organization' => $supplier->slug, 'product' => $product->id]))
        ->assertNotFound();

    $this
        ->actingAs($supplierUser)
        ->get(route('products.index', ['current_organization' => $supplier->slug]))
        ->assertInertia(fn (Assert $page) => $page->has('products.data', 0));

    expect($product->fresh()->supplier_connection_id)->toBe($connection->id);
});

test('a supplier gets a 404 for a product of a connection they do not hold', function () {
    [$supplierUser, $supplier] = newSupplierMember();
    [, $distributor] = newOrganizationMember();

    newSupplierConnection($distributor, $supplier);

    $otherProduct = Product::factory()->create();

    $this
        ->actingAs($supplierUser)
        ->get(route('products.edit', ['current_organization' => $supplier->slug, 'product' => $otherProduct->id]))
        ->assertNotFound();
});

test('guests are redirected to the login page', function () {
    [, $supplier] = newSupplierMember();

    $this
        ->get(route('products.index', ['current_organization' => $supplier->slug]))
        ->assertRedirect(route('login'));
});
