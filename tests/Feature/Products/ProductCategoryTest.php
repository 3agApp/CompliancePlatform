<?php

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Visit the category list of an organization.
 */
function categoriesPage(User $user, Organization $organization): TestResponse
{
    return test()
        ->actingAs($user)
        ->get(route('categories.index', ['current_organization' => $organization->slug]));
}

test('a new distributor starts with the default legal families', function () {
    [, $distributor] = newOrganizationMember();

    expect($distributor->productCategories()->orderBy('name')->pluck('name')->all())
        ->toBe(['Filter', 'Magnetic toy', 'Toy']);
});

test('a new supplier gets no categories, because it never files a product under one', function () {
    [, $supplier] = newSupplierMember();

    expect($supplier->productCategories()->count())->toBe(0);
});

test('the categories page lists the organization categories with their product counts', function () {
    [$user, $distributor] = newOrganizationMember();
    $connection = newSupplierConnection($distributor);

    $toy = $distributor->productCategories()->where('name', 'Toy')->sole();

    Product::factory()->count(2)->for($distributor)->inCategory($toy)->create([
        'supplier_connection_id' => $connection->id,
    ]);

    categoriesPage($user, $distributor)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('categories/index')
            ->has('categories', 3)
            ->where('categories.0.name', 'Filter')
            ->where('categories.0.products_count', 0)
            ->where('categories.2.name', 'Toy')
            ->where('categories.2.products_count', 2)
            ->where('categories.2.uuid', $toy->uuid)
            ->where('permissions.canCreateCategory', true),
        );
});

test('a category never shows another organization categories', function () {
    [$user, $distributor] = newOrganizationMember();
    [, $otherDistributor] = newOrganizationMember();

    $otherDistributor->productCategories()->create(['name' => 'Pyrotechnic article']);

    categoriesPage($user, $distributor)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('categories', 3)
            ->where('categories.0.name', 'Filter'),
        );
});

test('categories can be created', function () {
    [$user, $distributor] = newOrganizationMember();

    $this
        ->actingAs($user)
        ->post(route('categories.store', ['current_organization' => $distributor->slug]), ['name' => 'Pyrotechnic article'])
        ->assertRedirect(route('categories.index', ['current_organization' => $distributor->slug]));

    $this->assertDatabaseHas('product_categories', [
        'organization_id' => $distributor->id,
        'name' => 'Pyrotechnic article',
    ]);
});

test('a category name is required', function () {
    [$user, $distributor] = newOrganizationMember();

    $this
        ->actingAs($user)
        ->post(route('categories.store', ['current_organization' => $distributor->slug]), ['name' => ''])
        ->assertSessionHasErrors('name');

    expect($distributor->productCategories()->count())->toBe(3);
});

test('a name the organization already uses is rejected whatever its casing', function (string $name) {
    [$user, $distributor] = newOrganizationMember();

    $this
        ->actingAs($user)
        ->post(route('categories.store', ['current_organization' => $distributor->slug]), ['name' => $name])
        ->assertSessionHasErrors(['name' => 'You already have a category with this name.']);

    expect($distributor->productCategories()->count())->toBe(3);
})->with([
    'Magnetic toy',
    'magnetic toy',
    'MAGNETIC TOY',
]);

test('a name another organization uses is free to take', function () {
    [$user, $distributor] = newOrganizationMember();
    [, $otherDistributor] = newOrganizationMember();

    $otherDistributor->productCategories()->create(['name' => 'Pyrotechnic article']);

    $this
        ->actingAs($user)
        ->post(route('categories.store', ['current_organization' => $distributor->slug]), ['name' => 'Pyrotechnic article'])
        ->assertSessionHasNoErrors();

    expect(ProductCategory::query()->where('name', 'Pyrotechnic article')->count())->toBe(2);
});

test('categories can be renamed', function () {
    [$user, $distributor] = newOrganizationMember();

    $category = $distributor->productCategories()->where('name', 'Toy')->sole();

    $this
        ->actingAs($user)
        ->patch(route('categories.update', ['current_organization' => $distributor->slug, 'product_category' => $category->uuid]), [
            'name' => 'Toy (EN 71)',
        ])
        ->assertRedirect(route('categories.index', ['current_organization' => $distributor->slug]));

    expect($category->fresh()->name)->toBe('Toy (EN 71)');
});

test('a category keeps its own name when it is saved unchanged', function () {
    [$user, $distributor] = newOrganizationMember();

    $category = $distributor->productCategories()->where('name', 'Toy')->sole();

    $this
        ->actingAs($user)
        ->patch(route('categories.update', ['current_organization' => $distributor->slug, 'product_category' => $category->uuid]), [
            'name' => 'Toy',
        ])
        ->assertSessionHasNoErrors();

    expect($category->fresh()->name)->toBe('Toy');
});

test('an unused category can be deleted', function () {
    [$user, $distributor] = newOrganizationMember();

    $category = $distributor->productCategories()->where('name', 'Filter')->sole();

    $this
        ->actingAs($user)
        ->delete(route('categories.destroy', ['current_organization' => $distributor->slug, 'product_category' => $category->uuid]))
        ->assertRedirect(route('categories.index', ['current_organization' => $distributor->slug]));

    $this->assertModelMissing($category);
});

test('a category still used by products is kept, and says how many', function () {
    [$user, $distributor] = newOrganizationMember();
    $connection = newSupplierConnection($distributor);

    $category = $distributor->productCategories()->where('name', 'Toy')->sole();

    $products = Product::factory()->count(2)->for($distributor)->inCategory($category)->create([
        'supplier_connection_id' => $connection->id,
    ]);

    $this
        ->actingAs($user)
        ->delete(route('categories.destroy', ['current_organization' => $distributor->slug, 'product_category' => $category->uuid]))
        ->assertRedirect(route('categories.index', ['current_organization' => $distributor->slug]));

    $this->assertModelExists($category);

    expect($products->first()->fresh()->product_category_id)->toBe($category->id);
});

test('a category of another organization cannot be reached through the current organization', function () {
    [$user, $distributor] = newOrganizationMember();
    [, $otherDistributor] = newOrganizationMember();

    $foreign = $otherDistributor->productCategories()->where('name', 'Toy')->sole();

    $this
        ->actingAs($user)
        ->patch(route('categories.update', ['current_organization' => $distributor->slug, 'product_category' => $foreign->uuid]), [
            'name' => 'Renamed from outside',
        ])
        ->assertNotFound();

    $this
        ->actingAs($user)
        ->delete(route('categories.destroy', ['current_organization' => $distributor->slug, 'product_category' => $foreign->uuid]))
        ->assertNotFound();

    expect($foreign->fresh()->name)->toBe('Toy');
});

test('a supplier organization has no categories page', function () {
    [$supplierUser, $supplier] = newSupplierMember();

    categoriesPage($supplierUser, $supplier)->assertNotFound();
});

test('members can view categories but cannot create, rename or delete them', function () {
    [$user, $distributor] = newOrganizationMember(OrganizationRole::Member);

    $category = $distributor->productCategories()->where('name', 'Filter')->sole();

    categoriesPage($user, $distributor)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('permissions.canCreateCategory', false)
            ->where('permissions.canUpdateCategory', false)
            ->where('permissions.canDeleteCategory', false),
        );

    $this
        ->actingAs($user)
        ->post(route('categories.store', ['current_organization' => $distributor->slug]), ['name' => 'Pyrotechnic article'])
        ->assertForbidden();

    $this
        ->actingAs($user)
        ->patch(route('categories.update', ['current_organization' => $distributor->slug, 'product_category' => $category->uuid]), ['name' => 'Renamed'])
        ->assertForbidden();

    $this
        ->actingAs($user)
        ->delete(route('categories.destroy', ['current_organization' => $distributor->slug, 'product_category' => $category->uuid]))
        ->assertForbidden();
});

test('admins can manage categories', function () {
    [$user, $distributor] = newOrganizationMember(OrganizationRole::Admin);

    $this
        ->actingAs($user)
        ->post(route('categories.store', ['current_organization' => $distributor->slug]), ['name' => 'Pyrotechnic article'])
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('product_categories', [
        'organization_id' => $distributor->id,
        'name' => 'Pyrotechnic article',
    ]);
});

test('users who do not belong to the organization cannot list its categories', function () {
    [, $distributor] = newOrganizationMember();

    $stranger = User::factory()->create();

    categoriesPage($stranger, $distributor)->assertForbidden();
});

test('guests are redirected to the login page', function () {
    $organization = Organization::factory()->create();

    $this
        ->get(route('categories.index', ['current_organization' => $organization->slug]))
        ->assertRedirect(route('login'));
});
