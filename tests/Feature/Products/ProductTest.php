<?php

use App\Enums\CountryOfOrigin;
use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\SupplierConnection;
use App\Models\User;
use Illuminate\Database\QueryException;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Create a distributor owner together with a supplier they can assign
 * products to. A supplier is required on every product, so every write test
 * needs one.
 *
 * @return array{0: User, 1: Organization, 2: SupplierConnection}
 */
function distributorWithSupplier(OrganizationRole $role = OrganizationRole::Owner): array
{
    [$user, $organization] = newOrganizationMember($role);

    return [$user, $organization, newSupplierConnection($organization)];
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function productPayload(SupplierConnection $connection, array $overrides = []): array
{
    return [
        'name' => 'Organic Oat Milk',
        'brand_id' => carriedBrand($connection)->id,
        'product_category_id' => legalFamily($connection->distributorOrganization)->id,
        'product_template_id' => familyTemplate($connection->distributorOrganization)->id,
        'ean' => '4006381333931',
        'internal_article_number' => 'ART-10294',
        'supplier_article_number' => 'MT-BLUE-32',
        'order_number' => 'PO-2026-0148',
        'customs_tariff_number' => '95030075',
        'country_of_origin' => 'DE',
        'supplier_connection_id' => $connection->id,
        ...$overrides,
    ];
}

test('the products index page lists the products of the current organization', function () {
    [$user, $organization, $connection] = distributorWithSupplier();

    $product = Product::factory()->for($organization)->create(['name' => 'Organic Oat Milk']);
    Product::factory()->create(['name' => 'Someone Else Product']);

    $this
        ->actingAs($user)
        ->get(route('products.index', ['current_organization' => $organization->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('products/index')
            ->has('products', 1)
            ->where('products.0.id', $product->id)
            ->where('products.0.name', 'Organic Oat Milk')
            ->where('permissions.canCreateProduct', true),
        );
});

test('products can be created', function () {
    [$user, $organization, $connection] = distributorWithSupplier();

    $this
        ->actingAs($user)
        ->post(route('products.store', ['current_organization' => $organization->slug]), productPayload($connection))
        ->assertRedirect(route('products.index', ['current_organization' => $organization->slug]));

    $this->assertDatabaseHas('products', [
        'organization_id' => $organization->id,
        'name' => 'Organic Oat Milk',
        'brand_id' => carriedBrand($connection)->id,
        'product_category_id' => legalFamily($connection->distributorOrganization)->id,
        'product_template_id' => familyTemplate($connection->distributorOrganization)->id,
        'ean' => '4006381333931',
        'internal_article_number' => 'ART-10294',
        'supplier_article_number' => 'MT-BLUE-32',
        'order_number' => 'PO-2026-0148',
        'customs_tariff_number' => '95030075',
        'country_of_origin' => 'DE',
    ]);
});

test('products can be created without any of the optional identification details', function () {
    [$user, $organization, $connection] = distributorWithSupplier();

    $this
        ->actingAs($user)
        ->post(route('products.store', ['current_organization' => $organization->slug]), productPayload($connection, [
            'brand_id' => null,
            'internal_article_number' => null,
            'supplier_article_number' => null,
            'order_number' => null,
            'customs_tariff_number' => null,
        ]))
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('products', [
        'name' => 'Organic Oat Milk',
        'brand_id' => null,
        'internal_article_number' => null,
        'supplier_article_number' => null,
        'order_number' => null,
        'customs_tariff_number' => null,
    ]);
});

test('an identification field can be cleared by submitting an empty value', function (string $field) {
    [$user, $organization, $connection] = distributorWithSupplier();

    $product = Product::factory()->for($organization)->inCategory(legalFamily($organization))->create();

    $this
        ->actingAs($user)
        ->patch(route('products.update', ['current_organization' => $organization->slug, 'product' => $product->id]), productPayload($connection, [
            $field => '',
        ]))
        ->assertSessionHasNoErrors();

    expect($product->fresh()->{$field})->toBeNull();
})->with([
    'brand_id',
    'internal_article_number',
    'supplier_article_number',
    'order_number',
    'customs_tariff_number',
]);

test('an identification field is limited to the column length', function (string $field) {
    [$user, $organization, $connection] = distributorWithSupplier();

    $this
        ->actingAs($user)
        ->post(route('products.store', ['current_organization' => $organization->slug]), productPayload($connection, [
            $field => str_repeat('a', 256),
        ]))
        ->assertSessionHasErrors($field);

    $this->assertDatabaseCount('products', 0);
})->with([
    'internal_article_number',
    'supplier_article_number',
    'order_number',
]);

test('the category must be one of the legal families on the platform', function () {
    [$user, $organization, $connection] = distributorWithSupplier();

    $this
        ->actingAs($user)
        ->post(route('products.store', ['current_organization' => $organization->slug]), productPayload($connection, [
            'product_category_id' => legalFamily($organization)->id + 9999,
        ]))
        ->assertSessionHasErrors('product_category_id');

    $this->assertDatabaseCount('products', 0);
});

test('a product cannot carry another organization brand', function () {
    [$user, $organization, $connection] = distributorWithSupplier();
    [, $otherDistributor] = newOrganizationMember();

    $this
        ->actingAs($user)
        ->post(route('products.store', ['current_organization' => $organization->slug]), productPayload($connection, [
            'brand_id' => carriedBrand(newSupplierConnection($otherDistributor), 'tigerbox')->id,
        ]))
        ->assertSessionHasErrors('brand_id');

    $this->assertDatabaseCount('products', 0);
});

test('a product cannot carry a brand named under one of the distributor other suppliers', function () {
    [$user, $organization, $connection] = distributorWithSupplier();

    /**
     * A maker belongs to a trade, so a brand this distributor holds is
     * still the wrong brand if it is not the one supplying this product.
     */
    $anotherSupplier = newSupplierConnection($organization);

    $this
        ->actingAs($user)
        ->post(route('products.store', ['current_organization' => $organization->slug]), productPayload($connection, [
            'brand_id' => carriedBrand($anotherSupplier, 'tigerbox')->id,
        ]))
        ->assertSessionHasErrors('brand_id');

    $this->assertDatabaseCount('products', 0);
});

test('the brands offered on the product pages are the ones named under the trade', function () {
    [$supplierUser, $supplier] = newSupplierMember();
    [, $distributor] = newOrganizationMember();

    $connection = newSupplierConnection($distributor, $supplier);
    $brand = carriedBrand($connection, 'Magna-Tiles');

    $product = Product::factory()->for($distributor)->create([
        'supplier_connection_id' => $connection->id,
    ]);

    $this
        ->actingAs($supplierUser)
        ->get(route('products.edit', ['current_organization' => $supplier->slug, 'product' => $product->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('availableBrands', 1)
            ->where('availableBrands.0.label', 'Magna-Tiles'),
        );

    $this
        ->actingAs($supplierUser)
        ->patch(route('products.update', ['current_organization' => $supplier->slug, 'product' => $product->id]), [
            'name' => $product->name,
            'product_category_id' => $product->product_category_id,
            'product_template_id' => $product->product_template_id,
            'brand_id' => $brand->id,
        ])
        ->assertSessionHasNoErrors();

    expect($product->fresh()->brand_id)->toBe($brand->id);
});

test('a product cannot be filed under another organization category', function () {
    [$user, $organization, $connection] = distributorWithSupplier();
    [, $otherDistributor] = newOrganizationMember();

    $this
        ->actingAs($user)
        ->post(route('products.store', ['current_organization' => $organization->slug]), productPayload($connection, [
            'product_category_id' => legalFamily($otherDistributor)->id,
        ]))
        ->assertSessionHasErrors('product_category_id');

    $this->assertDatabaseCount('products', 0);
});

test('a supplier files a product under one of the owning distributor families', function () {
    [$supplierUser, $supplier] = newSupplierMember();
    [, $distributor] = newOrganizationMember();

    $connection = newSupplierConnection($distributor, $supplier);
    $family = legalFamily($distributor, 'Magnetic toy');
    $familyTemplate = familyTemplate($distributor, 'Magnetic toy');

    $product = Product::factory()->for($distributor)->create([
        'supplier_connection_id' => $connection->id,
    ]);

    /**
     * The categories offered come from the distributor that owns the product,
     * never from the supplier doing the editing -- a supplier keeps no list
     * of its own.
     */
    $this
        ->actingAs($supplierUser)
        ->get(route('products.edit', ['current_organization' => $supplier->slug, 'product' => $product->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('availableCategories', 3)
            ->where('availableCategories.1.label', 'Magnetic toy'),
        );

    $this
        ->actingAs($supplierUser)
        ->patch(route('products.update', ['current_organization' => $supplier->slug, 'product' => $product->id]), [
            'name' => $product->name,
            'product_category_id' => $family->id,
            'product_template_id' => $familyTemplate->id,
        ])
        ->assertSessionHasNoErrors();

    expect($product->fresh()->product_category_id)->toBe($family->id);
});

test('the legal families are shared with the product pages', function () {
    [$user, $organization, $connection] = distributorWithSupplier();

    $product = Product::factory()
        ->for($organization)
        ->inCategory(legalFamily($organization, 'Magnetic toy'))
        ->create();

    $this
        ->actingAs($user)
        ->get(route('products.index', ['current_organization' => $organization->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('products.0.category_label', 'Magnetic toy'),
        );

    /**
     * The list itself offers no families to choose from -- it files
     * nothing. They are shared with the two pages that do.
     */
    $this
        ->actingAs($user)
        ->get(route('products.create', ['current_organization' => $organization->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('availableCategories', [
                ['id' => legalFamily($organization, 'Filter')->id, 'label' => 'Filter'],
                ['id' => legalFamily($organization, 'Magnetic toy')->id, 'label' => 'Magnetic toy'],
                ['id' => legalFamily($organization, 'Toy')->id, 'label' => 'Toy'],
            ]),
        );

    $this
        ->actingAs($user)
        ->get(route('products.edit', ['current_organization' => $organization->slug, 'product' => $product->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('product.product_category_id', legalFamily($organization, 'Magnetic toy')->id)
            ->where('product.category_label', 'Magnetic toy')
            ->has('availableCategories', 3),
        );
});

test('a category still on a product cannot be deleted out from under it', function () {
    [, $organization] = distributorWithSupplier();

    $category = ProductCategory::factory()->for($organization)->create(['name' => 'Retired family']);
    Product::factory()->for($organization)->inCategory($category)->create();

    /**
     * The category screen refuses this with a sentence long before it gets
     * here. The constraint is the backstop for every other way a row could
     * be reached -- a console command, a future admin screen -- because a
     * product with no legal family is a product nobody can say which rules
     * it answers to.
     */
    expect(fn () => $category->delete())->toThrow(QueryException::class);
});

test('products can be created without a barcode', function () {
    [$user, $organization, $connection] = distributorWithSupplier();

    $this
        ->actingAs($user)
        ->post(route('products.store', ['current_organization' => $organization->slug]), productPayload($connection, ['ean' => null]))
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('products', [
        'name' => 'Organic Oat Milk',
        'ean' => null,
    ]);
});

test('products can be created without a country of origin', function () {
    [$user, $organization, $connection] = distributorWithSupplier();

    $this
        ->actingAs($user)
        ->post(route('products.store', ['current_organization' => $organization->slug]), productPayload($connection, [
            'country_of_origin' => null,
        ]))
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('products', [
        'name' => 'Organic Oat Milk',
        'country_of_origin' => null,
    ]);
});

test('a country of origin can be removed by submitting an empty value', function () {
    [$user, $organization, $connection] = distributorWithSupplier();

    $product = Product::factory()->for($organization)->create([
        'country_of_origin' => CountryOfOrigin::Germany,
    ]);

    $this
        ->actingAs($user)
        ->patch(route('products.update', ['current_organization' => $organization->slug, 'product' => $product->id]), productPayload($connection, [
            'country_of_origin' => '',
        ]))
        ->assertSessionHasNoErrors();

    expect($product->fresh()->country_of_origin)->toBeNull();
});

test('a product name is required', function () {
    [$user, $organization, $connection] = distributorWithSupplier();

    $this
        ->actingAs($user)
        ->post(route('products.store', ['current_organization' => $organization->slug]), productPayload($connection, ['name' => '']))
        ->assertSessionHasErrors('name');

    $this->assertDatabaseCount('products', 0);
});

test('a barcode must be a valid ean length', function (string $ean, bool $valid) {
    [$user, $organization, $connection] = distributorWithSupplier();

    $response = $this
        ->actingAs($user)
        ->post(route('products.store', ['current_organization' => $organization->slug]), productPayload($connection, ['ean' => $ean]));

    $valid
        ? $response->assertSessionHasNoErrors()
        : $response->assertSessionHasErrors('ean');
})->with([
    ['40063813', true],
    ['400638133393', true],
    ['4006381333931', true],
    ['40063813339312', true],
    ['4006381', false],
    ['400638133393123', false],
    ['400638133393A', false],
]);

test('a customs tariff number must be 6 to 12 digits', function (string $tariffNumber, bool $valid) {
    [$user, $organization, $connection] = distributorWithSupplier();

    $response = $this
        ->actingAs($user)
        ->post(route('products.store', ['current_organization' => $organization->slug]), productPayload($connection, [
            'customs_tariff_number' => $tariffNumber,
        ]));

    $valid
        ? $response->assertSessionHasNoErrors()
        : $response->assertSessionHasErrors('customs_tariff_number');
})->with([
    ['950300', true],
    ['95030075', true],
    ['9503007500', true],
    ['950300750000', true],
    ['95030', false],
    ['9503007500000', false],
    ['9503.00.7A', false],
]);

test('a customs tariff number is stored as bare digits however it was typed', function (string $typed) {
    [$user, $organization, $connection] = distributorWithSupplier();

    $this
        ->actingAs($user)
        ->post(route('products.store', ['current_organization' => $organization->slug]), productPayload($connection, [
            'customs_tariff_number' => $typed,
        ]))
        ->assertSessionHasNoErrors();

    expect(Product::sole()->customs_tariff_number)->toBe('95030075');
})->with([
    'bare' => '95030075',
    'dotted' => '9503.00.75',
    'spaced' => '9503 00 75',
    'hyphenated' => '9503-00-75',
    'padded' => '  9503.00.75  ',
]);

test('a customs tariff number of nothing but separators is no number at all', function () {
    [$user, $organization, $connection] = distributorWithSupplier();

    $this
        ->actingAs($user)
        ->post(route('products.store', ['current_organization' => $organization->slug]), productPayload($connection, [
            'customs_tariff_number' => ' . . ',
        ]))
        ->assertSessionHasNoErrors();

    expect(Product::sole()->customs_tariff_number)->toBeNull();
});

test('the country of origin is limited to germany and switzerland', function (?string $country, bool $valid) {
    [$user, $organization, $connection] = distributorWithSupplier();

    $response = $this
        ->actingAs($user)
        ->post(route('products.store', ['current_organization' => $organization->slug]), productPayload($connection, [
            'country_of_origin' => $country,
        ]));

    $valid
        ? $response->assertSessionHasNoErrors()
        : $response->assertSessionHasErrors('country_of_origin');
})->with([
    ['DE', true],
    ['CH', true],
    [null, true],
    ['', true],
    ['AT', false],
    ['Germany', false],
    ['de', false],
]);

test('the available countries of origin are shared with the product pages', function () {
    [$user, $organization, $connection] = distributorWithSupplier();

    $product = Product::factory()->for($organization)->create([
        'country_of_origin' => CountryOfOrigin::Switzerland,
    ]);

    $this
        ->actingAs($user)
        ->get(route('products.index', ['current_organization' => $organization->slug]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('products.0.country_of_origin', 'CH')
            ->where('products.0.country_of_origin_label', 'Switzerland'),
        );

    $this
        ->actingAs($user)
        ->get(route('products.create', ['current_organization' => $organization->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('availableCountries', [
                ['value' => 'DE', 'label' => 'Germany'],
                ['value' => 'CH', 'label' => 'Switzerland'],
            ]),
        );

    $this
        ->actingAs($user)
        ->get(route('products.edit', ['current_organization' => $organization->slug, 'product' => $product->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('product.country_of_origin', 'CH')
            ->has('availableCountries', 2),
        );
});

test('products can be updated', function () {
    [$user, $organization, $connection] = distributorWithSupplier();

    $product = Product::factory()->for($organization)->create(['name' => 'Oat Milk']);

    $this
        ->actingAs($user)
        ->patch(route('products.update', ['current_organization' => $organization->slug, 'product' => $product->id]), productPayload($connection))
        ->assertRedirect(route('products.edit', ['current_organization' => $organization->slug, 'product' => $product->id]));

    expect($product->fresh())
        ->name->toBe('Organic Oat Milk')
        ->brand_id->toBe(carriedBrand($connection)->id)
        ->product_category_id->toBe(legalFamily($organization)->id)
        ->ean->toBe('4006381333931')
        ->internal_article_number->toBe('ART-10294')
        ->supplier_article_number->toBe('MT-BLUE-32')
        ->order_number->toBe('PO-2026-0148')
        ->customs_tariff_number->toBe('95030075')
        ->country_of_origin->toBe(CountryOfOrigin::Germany);
});

test('products can be deleted', function () {
    [$user, $organization, $connection] = distributorWithSupplier();

    $product = Product::factory()->for($organization)->create();

    $this
        ->actingAs($user)
        ->delete(route('products.destroy', ['current_organization' => $organization->slug, 'product' => $product->id]))
        ->assertRedirect(route('products.index', ['current_organization' => $organization->slug]));

    $this->assertDatabaseMissing('products', ['id' => $product->id]);
});

test('products of another organization cannot be reached through the current organization', function () {
    [$user, $organization, $connection] = distributorWithSupplier();

    $otherProduct = Product::factory()->create();

    $this
        ->actingAs($user)
        ->get(route('products.edit', ['current_organization' => $organization->slug, 'product' => $otherProduct->id]))
        ->assertNotFound();
});

test('users who do not belong to the organization cannot list its products', function () {
    [, $organization] = distributorWithSupplier();

    $stranger = User::factory()->create();

    $this
        ->actingAs($stranger)
        ->get(route('products.index', ['current_organization' => $organization->slug]))
        ->assertForbidden();
});

test('members can view products but cannot create, update or delete them', function () {
    [$user, $organization, $connection] = distributorWithSupplier(OrganizationRole::Member);

    $product = Product::factory()->for($organization)->create();

    $this
        ->actingAs($user)
        ->get(route('products.index', ['current_organization' => $organization->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('permissions.canCreateProduct', false)
            ->where('permissions.canUpdateProduct', false)
            ->where('permissions.canDeleteProduct', false),
        );

    $this
        ->actingAs($user)
        ->post(route('products.store', ['current_organization' => $organization->slug]), productPayload($connection))
        ->assertForbidden();

    $this
        ->actingAs($user)
        ->patch(route('products.update', ['current_organization' => $organization->slug, 'product' => $product->id]), productPayload($connection))
        ->assertForbidden();

    $this
        ->actingAs($user)
        ->delete(route('products.destroy', ['current_organization' => $organization->slug, 'product' => $product->id]))
        ->assertForbidden();
});

test('admins can manage products', function () {
    [$user, $organization, $connection] = distributorWithSupplier(OrganizationRole::Admin);

    $this
        ->actingAs($user)
        ->post(route('products.store', ['current_organization' => $organization->slug]), productPayload($connection))
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('products', ['organization_id' => $organization->id, 'name' => 'Organic Oat Milk']);
});

test('guests are redirected to the login page', function () {
    $organization = Organization::factory()->create();

    $this
        ->get(route('products.index', ['current_organization' => $organization->slug]))
        ->assertRedirect(route('login'));
});
