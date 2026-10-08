<?php

use App\Enums\OrganizationRole;
use App\Enums\SupplierConnectionStatus;
use App\Models\Product;

test('a brand is named under a supplier from the brands page', function () {
    [$user, $distributor] = newOrganizationMember();
    newSupplierConnection($distributor, attributes: ['company_name' => 'Acme Supplies']);

    $this->actingAs($user);

    $page = visit(route('brands.index', ['current_organization' => $distributor->slug]));

    $page->assertSee('No brands yet')
        ->click('@brand-add-button')
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

    $page->click('@brand-add-button')
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
        ->click('@brand-add-button')
        ->fill('@inline-brand-name', 'Magna-Tiles')
        ->click('@inline-brand-submit')
        ->assertSee('Brand created.')
        ->assertSeeIn('[data-test="brand-row"]:has-text("Magna-Tiles")', 'Alpine Trading AG')
        ->assertNoJavaScriptErrors();

    expect($supplier->suppliedBrands()->where('brands.name', 'Magna-Tiles')->exists())->toBeTrue();
});

test('a brand is renamed through the edit dialog', function () {
    [$user, $distributor] = newOrganizationMember();

    carriedBrand(newSupplierConnection($distributor), 'Magna Tiles');

    $this->actingAs($user);

    $page = visit(route('brands.index', ['current_organization' => $distributor->slug]));

    $page->click('[data-test="brand-row"]:has-text("Magna Tiles") [data-test="brand-actions"]')
        ->click('@brand-edit-button')
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

    $page->click('[data-test="brand-row"]:has-text("tigerbox") [data-test="brand-actions"]')
        ->click('@brand-delete-button')
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

    $page->click('[data-test="brand-row"]:has-text("Magna-Tiles") [data-test="brand-actions"]')
        ->click('@brand-delete-button')
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
        ->assertMissing('@brand-actions')
        ->assertNoJavaScriptErrors();
});

test('a trade that was turned down is labelled declined, not revoked', function () {
    [$user, $distributor] = newOrganizationMember();

    carriedBrand(newSupplierConnection($distributor, attributes: [
        'company_name' => 'Atlas Novelty',
        'status' => SupplierConnectionStatus::Declined,
    ]), 'Atlas');

    $this->actingAs($user);

    visit(route('brands.index', ['current_organization' => $distributor->slug]))
        ->assertSee('Atlas Novelty')
        ->assertSee('Declined')
        ->assertDontSee('Revoked')
        ->assertNoJavaScriptErrors();
});

/**
 * One list for every trade: narrowed to a supplier from the suppliers
 * page, and asked which supplier a new brand is for when it could be any.
 */
test('the brand list is narrowed to one supplier and a new brand asks which supplier it is for', function () {
    [$user, $distributor] = newOrganizationMember();

    $acme = newSupplierConnection($distributor, attributes: ['company_name' => 'Acme Supplies']);
    $other = newSupplierConnection($distributor, attributes: ['company_name' => 'Baltic Plastics']);

    carriedBrand($acme, 'Magna-Tiles');
    carriedBrand($other, 'Saarplast');

    $this->actingAs($user);

    visit(route('brands.index', ['current_organization' => $distributor->slug]))
        ->assertSee('Magna-Tiles')
        ->assertSee('Saarplast')
        ->fill('@brand-search', 'saar')
        ->assertSee('Saarplast')
        ->assertDontSee('Magna-Tiles')
        ->fill('@brand-search', '')
        ->click('@brand-add-button')
        ->click('[data-test="brand-add-for"]:has-text("Baltic Plastics")')
        ->fill('@inline-brand-name', 'Baltica')
        ->click('@inline-brand-submit')
        ->assertSee('Brand created.')
        ->assertNoJavaScriptErrors();

    expect($other->brands()->where('name', 'Baltica')->exists())->toBeTrue();

    visit(route('brands.index', ['current_organization' => $distributor->slug, 'supplier' => $acme->id]))
        ->assertSee('Magna-Tiles')
        ->assertDontSee('Saarplast')
        ->assertNoJavaScriptErrors();
});
