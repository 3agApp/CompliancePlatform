<?php

use App\Enums\OrganizationRole;
use App\Models\Product;

test('a category is created through the new category dialog', function () {
    [$user, $distributor] = newOrganizationMember();

    $this->actingAs($user);

    $page = visit(route('categories.index', ['current_organization' => $distributor->slug]));

    $page->assertSee('Magnetic toy')
        ->click('@categories-new-category-button')
        ->assertSee('Add a category')
        ->fill('@category-name', 'Pyrotechnic article')
        ->click('@save-category-submit')
        ->assertDontSee('Add a category')
        ->assertSee('Category created.')
        ->assertSee('Pyrotechnic article')
        ->assertNoJavaScriptErrors();

    expect($distributor->productCategories()->where('name', 'Pyrotechnic article')->exists())->toBeTrue();
});

test('the new category dialog stays open and shows the message for a duplicate name', function () {
    [$user, $distributor] = newOrganizationMember();

    $this->actingAs($user);

    $page = visit(route('categories.index', ['current_organization' => $distributor->slug]));

    $page->click('@categories-new-category-button')
        ->fill('@category-name', 'magnetic toy')
        ->click('@save-category-submit')
        ->assertSee('Add a category')
        ->assertSee('You already have a category with this name.')
        ->assertNoJavaScriptErrors();

    expect($distributor->productCategories()->count())->toBe(3);
});

test('a category is renamed through the edit dialog', function () {
    [$user, $distributor] = newOrganizationMember();

    $this->actingAs($user);

    $page = visit(route('categories.index', ['current_organization' => $distributor->slug]));

    $page->click('[data-test="category-row"]:has(td:text-is("Toy")) [data-test="category-edit-button"]')
        ->assertSee('Rename category')
        ->fill('@category-name', 'Toy (EN 71)')
        ->click('@save-category-submit')
        ->assertSee('Category updated.')
        ->assertSee('Toy (EN 71)')
        ->assertNoJavaScriptErrors();

    expect($distributor->productCategories()->where('name', 'Toy (EN 71)')->exists())->toBeTrue();
});

test('an unused category is deleted through the confirmation dialog', function () {
    [$user, $distributor] = newOrganizationMember();

    $this->actingAs($user);

    $page = visit(route('categories.index', ['current_organization' => $distributor->slug]));

    $page->click('[data-test="category-row"]:has(td:text-is("Filter")) [data-test="category-delete-button"]')
        ->assertSee('This action cannot be undone.')
        ->click('@delete-category-confirm')
        ->assertSee('Category deleted.')
        ->assertDontSee('Filter')
        ->assertNoJavaScriptErrors();

    expect($distributor->productCategories()->where('name', 'Filter')->exists())->toBeFalse();
});

test('the delete dialog refuses a category that is still on a product', function () {
    [$user, $distributor] = newOrganizationMember();
    $connection = newSupplierConnection($distributor);

    $category = $distributor->productCategories()->where('name', 'Toy')->sole();

    Product::factory()->count(2)->for($distributor)->inCategory($category)->create([
        'supplier_connection_id' => $connection->id,
    ]);

    $this->actingAs($user);

    $page = visit(route('categories.index', ['current_organization' => $distributor->slug]));

    $page->click('[data-test="category-row"]:has(td:text-is("Toy")) [data-test="category-delete-button"]')
        ->assertSee('is still used by 2 products')
        ->assertMissing('@delete-category-confirm')
        ->assertNoJavaScriptErrors();

    expect($distributor->productCategories()->where('name', 'Toy')->exists())->toBeTrue();
});

test('a member sees the category list without the create, rename and delete controls', function () {
    [$user, $distributor] = newOrganizationMember(OrganizationRole::Member);

    $this->actingAs($user);

    visit(route('categories.index', ['current_organization' => $distributor->slug]))
        ->assertSee('Magnetic toy')
        ->assertMissing('@categories-new-category-button')
        ->assertMissing('@category-edit-button')
        ->assertMissing('@category-delete-button')
        ->assertNoJavaScriptErrors();
});
