<?php

use App\Enums\SupplierConnectionStatus;
use App\Models\Product;

test('a distributor narrows the product list with the supplier filter', function () {
    [$user, $distributor] = newOrganizationMember();

    $acme = newSupplierConnection($distributor, attributes: ['company_name' => 'Acme Supplies']);
    $other = newSupplierConnection($distributor, attributes: [
        'company_name' => 'Bern Dairy',
        'contact_email' => 'other@supplier.test',
    ]);

    Product::factory()->for($distributor)->create(['name' => 'Organic Oat Milk', 'supplier_connection_id' => $acme->id]);
    Product::factory()->for($distributor)->create(['name' => 'Whipping Cream', 'supplier_connection_id' => $other->id]);

    $this->actingAs($user);

    $page = visit(route('products.index', ['current_organization' => $distributor->slug]));

    $page->assertSee('Organic Oat Milk')
        ->assertSee('Whipping Cream')
        ->click('@product-filter-connection')
        ->click('[role="option"]:has-text("Acme Supplies")')
        ->assertSee('Organic Oat Milk')
        ->assertDontSee('Whipping Cream')
        ->assertNoJavaScriptErrors();
});

test('the product list offers to clear the filters when nothing matches', function () {
    [$user, $distributor] = newOrganizationMember();

    $connection = newSupplierConnection($distributor, attributes: ['company_name' => 'Acme Supplies']);

    Product::factory()->for($distributor)->create(['name' => 'Organic Oat Milk', 'supplier_connection_id' => $connection->id]);

    $this->actingAs($user);

    $page = visit(route('products.index', ['current_organization' => $distributor->slug]));

    $page->assertSee('Organic Oat Milk')
        ->fill('@product-filter-search', 'zzzznothing')
        ->assertSee('No products match these filters')
        ->click('@product-filter-clear')
        ->assertSee('Organic Oat Milk')
        ->assertNoJavaScriptErrors();
});

test('a distributor narrows the product list with the category filter', function () {
    [$user, $distributor] = newOrganizationMember();

    $connection = newSupplierConnection($distributor, attributes: ['company_name' => 'Acme Supplies']);

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

    $this->actingAs($user);

    $page = visit(route('products.index', ['current_organization' => $distributor->slug]));

    $page->assertSee('Magna-Tiles 32')
        ->assertSee('Water Filter Cartridge')
        ->click('@product-filter-category')
        ->click('[role="option"]:has-text("Magnetic toy")')
        ->assertSee('Magna-Tiles 32')
        ->assertDontSee('Water Filter Cartridge')
        ->click('@product-filter-clear')
        ->assertSee('Water Filter Cartridge')
        ->assertNoJavaScriptErrors();
});

test('the product list is searched by an article number', function () {
    [$user, $distributor] = newOrganizationMember();

    $connection = newSupplierConnection($distributor, attributes: ['company_name' => 'Acme Supplies']);

    Product::factory()->for($distributor)->create([
        'name' => 'Organic Oat Milk',
        'internal_article_number' => 'ART-10294',
        'supplier_connection_id' => $connection->id,
    ]);
    Product::factory()->for($distributor)->create([
        'name' => 'Whipping Cream',
        'internal_article_number' => 'ART-55555',
        'supplier_connection_id' => $connection->id,
    ]);

    $this->actingAs($user);

    $page = visit(route('products.index', ['current_organization' => $distributor->slug]));

    $page->assertSee('Whipping Cream')
        ->fill('@product-filter-search', 'ART-10294')
        ->assertSee('Organic Oat Milk')
        ->assertDontSee('Whipping Cream')
        ->assertNoJavaScriptErrors();
});

test('a distributor opens the product list filtered to one supplier from the suppliers page', function () {
    [$user, $distributor] = newOrganizationMember();

    $acme = newSupplierConnection($distributor, attributes: ['company_name' => 'Acme Supplies']);
    $other = newSupplierConnection($distributor, attributes: [
        'company_name' => 'Bern Dairy',
        'contact_email' => 'other@supplier.test',
    ]);

    Product::factory()->for($distributor)->create(['name' => 'Organic Oat Milk', 'supplier_connection_id' => $acme->id]);
    Product::factory()->for($distributor)->create(['name' => 'Whipping Cream', 'supplier_connection_id' => $other->id]);

    $this->actingAs($user);

    $page = visit(route('suppliers.index', ['current_organization' => $distributor->slug]));

    $page->click('[data-test="supplier-products-link"]:has-text("Acme Supplies")')
        ->assertSee('Organic Oat Milk')
        ->assertDontSee('Whipping Cream')
        ->assertNoJavaScriptErrors();
});

test('a supplier opens the product list filtered to one distributor from the distributors page', function () {
    [$supplierUser, $supplier] = newSupplierMember();
    [, $distributorA] = newOrganizationMember(organizationAttributes: ['name' => 'Alpine Trading AG']);
    [, $distributorB] = newOrganizationMember(organizationAttributes: ['name' => 'Coop Trading AG']);

    $connectionA = newSupplierConnection($distributorA, $supplier);
    $connectionB = newSupplierConnection($distributorB, $supplier);

    Product::factory()->for($distributorA)->create(['name' => 'Organic Oat Milk', 'supplier_connection_id' => $connectionA->id]);
    Product::factory()->for($distributorB)->create(['name' => 'Whipping Cream', 'supplier_connection_id' => $connectionB->id]);

    $this->actingAs($supplierUser);

    $page = visit(route('distributors.index', ['current_organization' => $supplier->slug]));

    $page->click('[data-test="distributor-products-link"]:has-text("Alpine Trading AG")')
        ->assertSee('Organic Oat Milk')
        ->assertDontSee('Whipping Cream')
        ->assertNoJavaScriptErrors();
});

test('a distributor invites a revoked supplier again from the suppliers table', function () {
    [$user, $distributor] = newOrganizationMember();

    $connection = newSupplierConnection($distributor, attributes: [
        'company_name' => 'Acme Supplies',
        'status' => SupplierConnectionStatus::Revoked,
    ]);

    $this->actingAs($user);

    $page = visit(route('suppliers.index', ['current_organization' => $distributor->slug]));

    $page->assertSee('Revoked')
        ->click('@supplier-resend-button')
        ->assertSee('Pending')
        ->assertNoJavaScriptErrors();

    expect($connection->fresh()->status)->toBe(SupplierConnectionStatus::Pending);
});
