<?php

use App\Enums\OrganizationRole;
use App\Models\Brand;
use App\Models\Organization;
use App\Models\Product;
use App\Models\User;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Visit the brand list of an organization.
 */
function brandsPage(User $user, Organization $organization): TestResponse
{
    return test()
        ->actingAs($user)
        ->get(route('brands.index', ['current_organization' => $organization->slug]));
}

test('a new organization starts with no brands, because nobody can guess the makers it carries', function () {
    [, $distributor] = newOrganizationMember();
    [, $supplier] = newSupplierMember();

    expect($distributor->brands()->count())->toBe(0);
    expect($supplier->brands()->count())->toBe(0);
});

test('the brands page lists the organization brands with their product counts', function () {
    [$user, $distributor] = newOrganizationMember();
    $connection = newSupplierConnection($distributor);

    $magnaTiles = $distributor->brands()->create(['name' => 'Magna-Tiles']);
    $distributor->brands()->create(['name' => 'tigerbox']);

    Product::factory()->count(2)->for($distributor)->ofBrand($magnaTiles)->create([
        'supplier_connection_id' => $connection->id,
    ]);

    brandsPage($user, $distributor)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('brands/index')
            ->has('brands', 2)
            ->where('brands.0.name', 'Magna-Tiles')
            ->where('brands.0.id', $magnaTiles->id)
            ->where('brands.0.products_count', 2)
            ->where('brands.1.name', 'tigerbox')
            ->where('brands.1.products_count', 0)
            ->where('permissions.canCreateBrand', true),
        );
});

test('the brands page never shows another organization brands', function () {
    [$user, $distributor] = newOrganizationMember();
    [, $otherDistributor] = newOrganizationMember();

    $distributor->brands()->create(['name' => 'Magna-Tiles']);
    $otherDistributor->brands()->create(['name' => 'tigerbox']);

    brandsPage($user, $distributor)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('brands', 1)
            ->where('brands.0.name', 'Magna-Tiles'),
        );
});

test('brands can be created', function () {
    [$user, $distributor] = newOrganizationMember();

    $this
        ->actingAs($user)
        ->post(route('brands.store', ['current_organization' => $distributor->slug]), ['name' => 'tigerbox'])
        ->assertRedirect(route('brands.index', ['current_organization' => $distributor->slug]));

    $this->assertDatabaseHas('brands', [
        'organization_id' => $distributor->id,
        'name' => 'tigerbox',
    ]);
});

test('a brand name is required', function () {
    [$user, $distributor] = newOrganizationMember();

    $this
        ->actingAs($user)
        ->post(route('brands.store', ['current_organization' => $distributor->slug]), ['name' => ''])
        ->assertSessionHasErrors('name');

    expect($distributor->brands()->count())->toBe(0);
});

test('a name the organization already uses is rejected whatever its casing', function (string $name) {
    [$user, $distributor] = newOrganizationMember();

    $distributor->brands()->create(['name' => 'Magna-Tiles']);

    $this
        ->actingAs($user)
        ->post(route('brands.store', ['current_organization' => $distributor->slug]), ['name' => $name])
        ->assertSessionHasErrors(['name' => 'You already have a brand with this name.']);

    expect($distributor->brands()->count())->toBe(1);
})->with([
    'Magna-Tiles',
    'magna-tiles',
    'MAGNA-TILES',
]);

test('a name another organization uses is free to take', function () {
    [$user, $distributor] = newOrganizationMember();
    [, $otherDistributor] = newOrganizationMember();

    $otherDistributor->brands()->create(['name' => 'tigerbox']);

    $this
        ->actingAs($user)
        ->post(route('brands.store', ['current_organization' => $distributor->slug]), ['name' => 'tigerbox'])
        ->assertSessionHasNoErrors();

    expect(Brand::query()->where('name', 'tigerbox')->count())->toBe(2);
});

test('brands can be renamed', function () {
    [$user, $distributor] = newOrganizationMember();

    $brand = $distributor->brands()->create(['name' => 'Magna Tiles']);

    $this
        ->actingAs($user)
        ->patch(route('brands.update', ['current_organization' => $distributor->slug, 'brand' => $brand->id]), [
            'name' => 'Magna-Tiles',
        ])
        ->assertRedirect(route('brands.index', ['current_organization' => $distributor->slug]));

    expect($brand->fresh()->name)->toBe('Magna-Tiles');
});

test('a brand keeps its own name when it is saved unchanged', function () {
    [$user, $distributor] = newOrganizationMember();

    $brand = $distributor->brands()->create(['name' => 'Magna-Tiles']);

    $this
        ->actingAs($user)
        ->patch(route('brands.update', ['current_organization' => $distributor->slug, 'brand' => $brand->id]), [
            'name' => 'Magna-Tiles',
        ])
        ->assertSessionHasNoErrors();

    expect($brand->fresh()->name)->toBe('Magna-Tiles');
});

test('an unused brand can be deleted', function () {
    [$user, $distributor] = newOrganizationMember();

    $brand = $distributor->brands()->create(['name' => 'tigerbox']);

    $this
        ->actingAs($user)
        ->delete(route('brands.destroy', ['current_organization' => $distributor->slug, 'brand' => $brand->id]))
        ->assertRedirect(route('brands.index', ['current_organization' => $distributor->slug]));

    $this->assertModelMissing($brand);
});

test('a brand still carried by products is kept, and says how many', function () {
    [$user, $distributor] = newOrganizationMember();
    $connection = newSupplierConnection($distributor);

    $brand = $distributor->brands()->create(['name' => 'Magna-Tiles']);

    $products = Product::factory()->count(2)->for($distributor)->ofBrand($brand)->create([
        'supplier_connection_id' => $connection->id,
    ]);

    $this
        ->actingAs($user)
        ->delete(route('brands.destroy', ['current_organization' => $distributor->slug, 'brand' => $brand->id]))
        ->assertRedirect(route('brands.index', ['current_organization' => $distributor->slug]));

    $this->assertModelExists($brand);

    expect($products->first()->fresh()->brand_id)->toBe($brand->id);
});

test('a brand of another organization cannot be reached through the current organization', function () {
    [$user, $distributor] = newOrganizationMember();
    [, $otherDistributor] = newOrganizationMember();

    $foreign = $otherDistributor->brands()->create(['name' => 'tigerbox']);

    $this
        ->actingAs($user)
        ->patch(route('brands.update', ['current_organization' => $distributor->slug, 'brand' => $foreign->id]), [
            'name' => 'Renamed from outside',
        ])
        ->assertNotFound();

    $this
        ->actingAs($user)
        ->delete(route('brands.destroy', ['current_organization' => $distributor->slug, 'brand' => $foreign->id]))
        ->assertNotFound();

    expect($foreign->fresh()->name)->toBe('tigerbox');
});

test('a supplier organization has no brands page', function () {
    [$supplierUser, $supplier] = newSupplierMember();

    brandsPage($supplierUser, $supplier)->assertNotFound();
});

test('members can view brands but cannot create, rename or delete them', function () {
    [$user, $distributor] = newOrganizationMember(OrganizationRole::Member);

    $brand = $distributor->brands()->create(['name' => 'Magna-Tiles']);

    brandsPage($user, $distributor)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('permissions.canCreateBrand', false)
            ->where('permissions.canUpdateBrand', false)
            ->where('permissions.canDeleteBrand', false),
        );

    $this
        ->actingAs($user)
        ->post(route('brands.store', ['current_organization' => $distributor->slug]), ['name' => 'tigerbox'])
        ->assertForbidden();

    $this
        ->actingAs($user)
        ->patch(route('brands.update', ['current_organization' => $distributor->slug, 'brand' => $brand->id]), ['name' => 'Renamed'])
        ->assertForbidden();

    $this
        ->actingAs($user)
        ->delete(route('brands.destroy', ['current_organization' => $distributor->slug, 'brand' => $brand->id]))
        ->assertForbidden();
});

test('admins can manage brands', function () {
    [$user, $distributor] = newOrganizationMember(OrganizationRole::Admin);

    $this
        ->actingAs($user)
        ->post(route('brands.store', ['current_organization' => $distributor->slug]), ['name' => 'tigerbox'])
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('brands', [
        'organization_id' => $distributor->id,
        'name' => 'tigerbox',
    ]);
});

test('users who do not belong to the organization cannot list its brands', function () {
    [, $distributor] = newOrganizationMember();

    $stranger = User::factory()->create();

    brandsPage($stranger, $distributor)->assertForbidden();
});

test('guests are redirected to the login page', function () {
    $organization = Organization::factory()->create();

    $this
        ->get(route('brands.index', ['current_organization' => $organization->slug]))
        ->assertRedirect(route('login'));
});
