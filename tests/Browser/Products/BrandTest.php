<?php

use App\Enums\OrganizationRole;
use App\Models\Product;

test('a brand is created through the new brand dialog', function () {
    [$user, $distributor] = newOrganizationMember();

    $this->actingAs($user);

    $page = visit(route('brands.index', ['current_organization' => $distributor->slug]));

    $page->assertSee('No brands yet')
        ->click('@brands-new-brand-button')
        ->assertSee('Add a brand')
        ->fill('@brand-name', 'Magna-Tiles')
        ->click('@save-brand-submit')
        ->assertDontSee('Add a brand')
        ->assertSee('Brand created.')
        ->assertSee('Magna-Tiles')
        ->assertNoJavaScriptErrors();

    expect($distributor->brands()->where('name', 'Magna-Tiles')->exists())->toBeTrue();
});

test('the new brand dialog stays open and shows the message for a duplicate name', function () {
    [$user, $distributor] = newOrganizationMember();
    $distributor->brands()->create(['name' => 'Magna-Tiles']);

    $this->actingAs($user);

    $page = visit(route('brands.index', ['current_organization' => $distributor->slug]));

    $page->click('@brands-new-brand-button')
        ->fill('@brand-name', 'magna-tiles')
        ->click('@save-brand-submit')
        ->assertSee('Add a brand')
        ->assertSee('You already have a brand with this name.')
        ->assertNoJavaScriptErrors();

    expect($distributor->brands()->count())->toBe(1);
});

test('a brand is renamed through the edit dialog', function () {
    [$user, $distributor] = newOrganizationMember();
    $distributor->brands()->create(['name' => 'Magna Tiles']);

    $this->actingAs($user);

    $page = visit(route('brands.index', ['current_organization' => $distributor->slug]));

    $page->click('[data-test="brand-row"]:has(td:text-is("Magna Tiles")) [data-test="brand-edit-button"]')
        ->assertSee('Rename brand')
        ->fill('@brand-name', 'Magna-Tiles')
        ->click('@save-brand-submit')
        ->assertSee('Brand updated.')
        ->assertSee('Magna-Tiles')
        ->assertNoJavaScriptErrors();

    expect($distributor->brands()->where('name', 'Magna-Tiles')->exists())->toBeTrue();
});

test('an unused brand is deleted through the confirmation dialog', function () {
    [$user, $distributor] = newOrganizationMember();
    $distributor->brands()->create(['name' => 'tigerbox']);

    $this->actingAs($user);

    $page = visit(route('brands.index', ['current_organization' => $distributor->slug]));

    $page->click('[data-test="brand-row"]:has(td:text-is("tigerbox")) [data-test="brand-delete-button"]')
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

    $brand = $distributor->brands()->create(['name' => 'Magna-Tiles']);

    Product::factory()->count(2)->for($distributor)->ofBrand($brand)->create([
        'supplier_connection_id' => $connection->id,
    ]);

    $this->actingAs($user);

    $page = visit(route('brands.index', ['current_organization' => $distributor->slug]));

    $page->click('[data-test="brand-row"]:has(td:text-is("Magna-Tiles")) [data-test="brand-delete-button"]')
        ->assertSee('is still carried by 2 products')
        ->assertMissing('@delete-brand-confirm')
        ->assertNoJavaScriptErrors();

    expect($distributor->brands()->count())->toBe(1);
});

test('a member sees the brand list without the create, rename and delete controls', function () {
    [$user, $distributor] = newOrganizationMember(OrganizationRole::Member);
    $distributor->brands()->create(['name' => 'Magna-Tiles']);

    $this->actingAs($user);

    visit(route('brands.index', ['current_organization' => $distributor->slug]))
        ->assertSee('Magna-Tiles')
        ->assertMissing('@brands-new-brand-button')
        ->assertMissing('@brand-edit-button')
        ->assertMissing('@brand-delete-button')
        ->assertNoJavaScriptErrors();
});
