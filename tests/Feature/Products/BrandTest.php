<?php

use App\Enums\OrganizationRole;
use App\Enums\SupplierConnectionStatus;
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

/**
 * The address of the brand list, which is also where a write comes back to.
 */
function brandsUrl(Organization $organization): string
{
    return route('brands.index', ['current_organization' => $organization->slug]);
}

test('a new trade starts with no brands, because nobody can guess the makers behind it', function () {
    [, $distributor] = newOrganizationMember();

    expect(newSupplierConnection($distributor)->brands()->count())->toBe(0);
});

test('the brands page groups the makers under the trade each is named in', function () {
    [$user, $distributor] = newOrganizationMember();

    $acme = newSupplierConnection($distributor, attributes: ['company_name' => 'Acme Supplies']);
    $nordic = newSupplierConnection($distributor, attributes: ['company_name' => 'Nordic Toys']);

    $magnaTiles = carriedBrand($acme, 'Magna-Tiles');
    carriedBrand($nordic, 'BRIO');

    Product::factory()->count(2)->for($distributor)->ofBrand($magnaTiles)->create([
        'supplier_connection_id' => $acme->id,
    ]);

    brandsPage($user, $distributor)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('brands/index')
            ->has('connections', 2)
            ->where('connections.0.label', 'Acme Supplies')
            ->where('connections.0.brands.0.name', 'Magna-Tiles')
            ->where('connections.0.brands.0.products_count', 2)
            ->where('connections.0.canAddBrand', true)
            ->where('connections.1.label', 'Nordic Toys')
            ->where('connections.1.brands.0.name', 'BRIO')
            ->where('connections.1.brands.0.products_count', 0)
            ->where('permissions.canCreateBrand', true),
        );
});

test('the brands page names the state of a trade rather than guessing at it', function () {
    [$user, $distributor] = newOrganizationMember();

    carriedBrand(newSupplierConnection($distributor, attributes: [
        'company_name' => 'Atlas Novelty',
        'status' => SupplierConnectionStatus::Declined,
    ]), 'Atlas');

    carriedBrand(newSupplierConnection($distributor, attributes: [
        'company_name' => 'Kyoto Precision',
        'status' => SupplierConnectionStatus::Revoked,
    ]), 'Kyosei');

    brandsPage($user, $distributor)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('connections.0.label', 'Atlas Novelty')
            ->where('connections.0.status', 'declined')
            ->where('connections.0.statusLabel', 'Declined')
            ->where('connections.1.label', 'Kyoto Precision')
            ->where('connections.1.status', 'revoked')
            ->where('connections.1.statusLabel', 'Revoked'),
        );
});

test('the brands page never shows the makers behind another organization trades', function () {
    [$user, $distributor] = newOrganizationMember();
    [, $otherDistributor] = newOrganizationMember();

    carriedBrand(newSupplierConnection($distributor), 'Magna-Tiles');
    carriedBrand(newSupplierConnection($otherDistributor), 'tigerbox');

    brandsPage($user, $distributor)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('connections', 1)
            ->has('connections.0.brands', 1)
            ->where('connections.0.brands.0.name', 'Magna-Tiles'),
        );
});

test('a distributor names a maker under one of its suppliers', function () {
    [$user, $distributor] = newOrganizationMember();
    $connection = newSupplierConnection($distributor);

    $this
        ->actingAs($user)
        ->from(brandsUrl($distributor))
        ->post(route('brands.store', ['current_organization' => $distributor->slug]), [
            'name' => 'tigerbox',
            'supplier_connection_id' => $connection->id,
        ])
        ->assertRedirect(brandsUrl($distributor));

    $this->assertDatabaseHas('brands', [
        'supplier_connection_id' => $connection->id,
        'name' => 'tigerbox',
    ]);
});

test('a supplier names a maker under one of its distributors', function () {
    [$supplierUser, $supplier] = newSupplierMember();
    [, $distributor] = newOrganizationMember();

    $connection = newSupplierConnection($distributor, $supplier);

    $this
        ->actingAs($supplierUser)
        ->from(brandsUrl($supplier))
        ->post(route('brands.store', ['current_organization' => $supplier->slug]), [
            'name' => 'tigerbox',
            'supplier_connection_id' => $connection->id,
        ])
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('brands', [
        'supplier_connection_id' => $connection->id,
        'name' => 'tigerbox',
    ]);
});

test('a supplier reads the brands of its live trades and no others', function () {
    [$supplierUser, $supplier] = newSupplierMember();
    [, $distributor] = newOrganizationMember();
    [, $otherDistributor] = newOrganizationMember();

    $live = newSupplierConnection($distributor, $supplier);
    $revoked = newSupplierConnection($otherDistributor, $supplier, [
        'status' => SupplierConnectionStatus::Revoked,
    ]);

    carriedBrand($live, 'Magna-Tiles');
    carriedBrand($revoked, 'tigerbox');

    brandsPage($supplierUser, $supplier)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('connections', 1)
            ->where('connections.0.label', $distributor->name)
            ->where('connections.0.brands.0.name', 'Magna-Tiles'),
        );
});

test('a supplier cannot name a maker under a trade that is not its own', function () {
    [$supplierUser, $supplier] = newSupplierMember();
    [, $distributor] = newOrganizationMember();

    $someoneElses = newSupplierConnection($distributor);

    $this
        ->actingAs($supplierUser)
        ->from(brandsUrl($supplier))
        ->post(route('brands.store', ['current_organization' => $supplier->slug]), [
            'name' => 'tigerbox',
            'supplier_connection_id' => $someoneElses->id,
        ])
        ->assertSessionHasErrors('supplier_connection_id');

    expect(Brand::query()->count())->toBe(0);
});

test('a brand cannot be named under a revoked trade', function () {
    [$user, $distributor] = newOrganizationMember();

    $revoked = newSupplierConnection($distributor, attributes: [
        'status' => SupplierConnectionStatus::Revoked,
    ]);

    $this
        ->actingAs($user)
        ->from(brandsUrl($distributor))
        ->post(route('brands.store', ['current_organization' => $distributor->slug]), [
            'name' => 'tigerbox',
            'supplier_connection_id' => $revoked->id,
        ])
        ->assertSessionHasErrors('supplier_connection_id');

    expect(Brand::query()->count())->toBe(0);
});

test('a brand name is required', function () {
    [$user, $distributor] = newOrganizationMember();
    $connection = newSupplierConnection($distributor);

    $this
        ->actingAs($user)
        ->from(brandsUrl($distributor))
        ->post(route('brands.store', ['current_organization' => $distributor->slug]), [
            'name' => '',
            'supplier_connection_id' => $connection->id,
        ])
        ->assertSessionHasErrors('name');

    expect(Brand::query()->count())->toBe(0);
});

test('a supplier is required', function () {
    [$user, $distributor] = newOrganizationMember();

    $this
        ->actingAs($user)
        ->from(brandsUrl($distributor))
        ->post(route('brands.store', ['current_organization' => $distributor->slug]), ['name' => 'tigerbox'])
        ->assertSessionHasErrors('supplier_connection_id');

    expect(Brand::query()->count())->toBe(0);
});

test('a name the trade already uses is rejected whatever its casing', function (string $name) {
    [$user, $distributor] = newOrganizationMember();
    $connection = newSupplierConnection($distributor);

    carriedBrand($connection, 'Magna-Tiles');

    $this
        ->actingAs($user)
        ->from(brandsUrl($distributor))
        ->post(route('brands.store', ['current_organization' => $distributor->slug]), [
            'name' => $name,
            'supplier_connection_id' => $connection->id,
        ])
        ->assertSessionHasErrors(['name' => 'This supplier already has a brand with this name.']);

    expect($connection->brands()->count())->toBe(1);
})->with([
    'Magna-Tiles',
    'magna-tiles',
    'MAGNA-TILES',
]);

test('two suppliers of the same distributor may each carry a maker of the same name', function () {
    [$user, $distributor] = newOrganizationMember();

    $acme = newSupplierConnection($distributor);
    $nordic = newSupplierConnection($distributor);

    carriedBrand($acme, 'tigerbox');

    $this
        ->actingAs($user)
        ->from(brandsUrl($distributor))
        ->post(route('brands.store', ['current_organization' => $distributor->slug]), [
            'name' => 'tigerbox',
            'supplier_connection_id' => $nordic->id,
        ])
        ->assertSessionHasNoErrors();

    expect(Brand::query()->where('name', 'tigerbox')->count())->toBe(2);
});

test('brands can be renamed', function () {
    [$user, $distributor] = newOrganizationMember();

    $brand = carriedBrand(newSupplierConnection($distributor), 'Magna Tiles');

    $this
        ->actingAs($user)
        ->patch(route('brands.update', ['current_organization' => $distributor->slug, 'brand' => $brand->id]), [
            'name' => 'Magna-Tiles',
        ])
        ->assertRedirect(brandsUrl($distributor));

    expect($brand->fresh()->name)->toBe('Magna-Tiles');
});

test('renaming a brand never moves it to another trade', function () {
    [$user, $distributor] = newOrganizationMember();

    $acme = newSupplierConnection($distributor);
    $nordic = newSupplierConnection($distributor);

    $brand = carriedBrand($acme, 'Magna-Tiles');

    $this
        ->actingAs($user)
        ->patch(route('brands.update', ['current_organization' => $distributor->slug, 'brand' => $brand->id]), [
            'name' => 'Magna-Tiles',
            'supplier_connection_id' => $nordic->id,
        ])
        ->assertSessionHasNoErrors();

    expect($brand->fresh()->supplier_connection_id)->toBe($acme->id);
});

test('a brand keeps its own name when it is saved unchanged', function () {
    [$user, $distributor] = newOrganizationMember();

    $brand = carriedBrand(newSupplierConnection($distributor), 'Magna-Tiles');

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

    $brand = carriedBrand(newSupplierConnection($distributor), 'tigerbox');

    $this
        ->actingAs($user)
        ->delete(route('brands.destroy', ['current_organization' => $distributor->slug, 'brand' => $brand->id]))
        ->assertRedirect(brandsUrl($distributor));

    $this->assertModelMissing($brand);
});

test('a brand still carried by products is kept, and says how many', function () {
    [$user, $distributor] = newOrganizationMember();
    $connection = newSupplierConnection($distributor);

    $brand = carriedBrand($connection, 'Magna-Tiles');

    $products = Product::factory()->count(2)->for($distributor)->ofBrand($brand)->create([
        'supplier_connection_id' => $connection->id,
    ]);

    $this
        ->actingAs($user)
        ->delete(route('brands.destroy', ['current_organization' => $distributor->slug, 'brand' => $brand->id]))
        ->assertRedirect(brandsUrl($distributor));

    $this->assertModelExists($brand);

    expect($products->first()->fresh()->brand_id)->toBe($brand->id);
});

test('a brand of another organization cannot be reached through the current organization', function () {
    [$user, $distributor] = newOrganizationMember();
    [, $otherDistributor] = newOrganizationMember();

    $foreign = carriedBrand(newSupplierConnection($otherDistributor), 'tigerbox');

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

test('a supplier cannot reach a brand through a trade it has lost', function () {
    [$supplierUser, $supplier] = newSupplierMember();
    [, $distributor] = newOrganizationMember();

    $revoked = newSupplierConnection($distributor, $supplier, [
        'status' => SupplierConnectionStatus::Revoked,
    ]);

    $brand = carriedBrand($revoked, 'tigerbox');

    $this
        ->actingAs($supplierUser)
        ->patch(route('brands.update', ['current_organization' => $supplier->slug, 'brand' => $brand->id]), [
            'name' => 'Renamed after revocation',
        ])
        ->assertNotFound();

    expect($brand->fresh()->name)->toBe('tigerbox');
});

test('members can view brands but cannot create, rename or delete them', function () {
    [$user, $distributor] = newOrganizationMember(OrganizationRole::Member);
    $connection = newSupplierConnection($distributor);

    $brand = carriedBrand($connection, 'Magna-Tiles');

    brandsPage($user, $distributor)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('permissions.canCreateBrand', false)
            ->where('permissions.canUpdateBrand', false)
            ->where('permissions.canDeleteBrand', false)
            ->where('connections.0.canAddBrand', false),
        );

    $this
        ->actingAs($user)
        ->from(brandsUrl($distributor))
        ->post(route('brands.store', ['current_organization' => $distributor->slug]), [
            'name' => 'tigerbox',
            'supplier_connection_id' => $connection->id,
        ])
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
    $connection = newSupplierConnection($distributor);

    $this
        ->actingAs($user)
        ->from(brandsUrl($distributor))
        ->post(route('brands.store', ['current_organization' => $distributor->slug]), [
            'name' => 'tigerbox',
            'supplier_connection_id' => $connection->id,
        ])
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('brands', [
        'supplier_connection_id' => $connection->id,
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
