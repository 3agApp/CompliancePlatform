<?php

use App\Enums\OrganizationRole;
use App\Models\Product;

test('a brand is named under a supplier from the brands page', function () {
    [$user, $distributor] = newOrganizationMember();
    newSupplierConnection($distributor, attributes: ['company_name' => 'Acme Supplies']);

    $this->actingAs($user);

    $page = visit(route('brands.index', ['current_organization' => $distributor->slug]));

    $page->assertSee('No brands yet')
        ->click('[data-test="brand-connection"]:has-text("Acme Supplies") [data-test="brand-add-button"]')
        ->assertSee('Add a brand')
        ->fill('@inline-brand-name', 'Magna-Tiles')
        ->click('@inline-brand-submit')
        ->assertDontSee('Add a brand')
        ->assertSee('Brand created.')
        ->assertSee('Magna-Tiles')
        ->assertNoJavaScriptErrors();

    expect($distributor->brands()->where('brands.name', 'Magna-Tiles')->exists())->toBeTrue();
});

test('the new brand dialog stays open and shows the message for a duplicate name', function () {
    [$user, $distributor] = newOrganizationMember();
    $connection = newSupplierConnection($distributor, attributes: ['company_name' => 'Acme Supplies']);

    carriedBrand($connection, 'Magna-Tiles');

    $this->actingAs($user);

    $page = visit(route('brands.index', ['current_organization' => $distributor->slug]));

    $page->click('[data-test="brand-connection"]:has-text("Acme Supplies") [data-test="brand-add-button"]')
        ->fill('@inline-brand-name', 'magna-tiles')
        ->click('@inline-brand-submit')
        ->assertSee('Add a brand')
        ->assertSee('This supplier already has a brand with this name.')
        ->assertNoJavaScriptErrors();

    expect($connection->brands()->count())->toBe(1);
});

test('a supplier names a brand under one of its distributors', function () {
    [$supplierUser, $supplier] = newSupplierMember();
    [, $distributor] = newOrganizationMember(organizationAttributes: ['name' => 'Alpine Trading AG']);

    newSupplierConnection($distributor, $supplier);

    $this->actingAs($supplierUser);

    visit(route('brands.index', ['current_organization' => $supplier->slug]))
        ->assertSee('Alpine Trading AG')
        ->click('[data-test="brand-connection"]:has-text("Alpine Trading AG") [data-test="brand-add-button"]')
        ->fill('@inline-brand-name', 'Magna-Tiles')
        ->click('@inline-brand-submit')
        ->assertSee('Brand created.')
        ->assertSee('Magna-Tiles')
        ->assertNoJavaScriptErrors();

    expect($supplier->suppliedBrands()->where('brands.name', 'Magna-Tiles')->exists())->toBeTrue();
});

test('a brand is renamed through the edit dialog', function () {
    [$user, $distributor] = newOrganizationMember();

    carriedBrand(newSupplierConnection($distributor), 'Magna Tiles');

    $this->actingAs($user);

    $page = visit(route('brands.index', ['current_organization' => $distributor->slug]));

    $page->click('[data-test="brand-row"]:has-text("Magna Tiles") [data-test="brand-edit-button"]')
        ->assertSee('Rename brand')
        ->fill('@brand-name', 'Magna-Tiles')
        ->click('@save-brand-submit')
        ->assertSee('Brand updated.')
        ->assertSee('Magna-Tiles')
        ->assertNoJavaScriptErrors();

    expect($distributor->brands()->where('brands.name', 'Magna-Tiles')->exists())->toBeTrue();
});

test('an unused brand is deleted through the confirmation dialog', function () {
    [$user, $distributor] = newOrganizationMember();

    carriedBrand(newSupplierConnection($distributor), 'tigerbox');

    $this->actingAs($user);

    $page = visit(route('brands.index', ['current_organization' => $distributor->slug]));

    $page->click('[data-test="brand-row"]:has-text("tigerbox") [data-test="brand-delete-button"]')
        ->assertSee('This action cannot be undone.')
        ->click('@delete-brand-confirm')
        ->assertSee('Brand deleted.')
        ->assertSee('No brands yet')
        ->assertNoJavaScriptErrors();

    expect($distributor->brands()->count())->toBe(0);
});

test('the delete dialog refuses a brand that is still on a product', function () {
    [$user, $distributor] = newOrganizationMember();
    $connection = newSupplierConnection($distributor);

    $brand = carriedBrand($connection, 'Magna-Tiles');

    Product::factory()->count(2)->for($distributor)->ofBrand($brand)->create([
        'supplier_connection_id' => $connection->id,
    ]);

    $this->actingAs($user);

    $page = visit(route('brands.index', ['current_organization' => $distributor->slug]));

    $page->click('[data-test="brand-row"]:has-text("Magna-Tiles") [data-test="brand-delete-button"]')
        ->assertSee('is still carried by 2 products')
        ->assertMissing('@delete-brand-confirm')
        ->assertNoJavaScriptErrors();

    expect($distributor->brands()->count())->toBe(1);
});

test('a member sees the brand list without the create, rename and delete controls', function () {
    [$user, $distributor] = newOrganizationMember(OrganizationRole::Member);

    carriedBrand(newSupplierConnection($distributor), 'Magna-Tiles');

    $this->actingAs($user);

    visit(route('brands.index', ['current_organization' => $distributor->slug]))
        ->assertSee('Magna-Tiles')
        ->assertMissing('@brand-add-button')
        ->assertMissing('@brand-edit-button')
        ->assertMissing('@brand-delete-button')
        ->assertNoJavaScriptErrors();
});
