<?php

use App\Enums\OrganizationRole;
use App\Models\Product;
use App\Models\SupplierConnection;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * The compliance answers a product carries, as a whole payload.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function compliancePayload(SupplierConnection $connection, array $overrides = []): array
{
    return [
        'name' => 'Organic Oat Milk',
        'supplier_connection_id' => $connection->id,
        'product_category_id' => legalFamily($connection->distributorOrganization)->id,
        'product_template_id' => familyTemplate($connection->distributorOrganization)->id,
        'age_grading' => '3+',
        'safety_notice' => 'Keep the packaging until the product has been checked.',
        'warning_text' => 'Not suitable for children under 3 years. Small parts.',
        'material_information' => 'ABS plastic, neodymium magnets, water based paint.',
        'usage_restrictions' => 'Indoor use only. Not for use in water.',
        'safety_instructions' => 'Inspect for damage before each use and replace broken parts.',
        'additional_notes' => 'Replacement parts are available from the manufacturer.',
        ...$overrides,
    ];
}

/**
 * The seven fields, for the tests that walk all of them.
 *
 * @return array<int, string>
 */
function complianceFields(): array
{
    return [
        'age_grading',
        'safety_notice',
        'warning_text',
        'material_information',
        'usage_restrictions',
        'safety_instructions',
        'additional_notes',
    ];
}

test('the compliance details of a product are saved', function () {
    [$user, $organization] = newOrganizationMember();
    $connection = newSupplierConnection($organization);

    $product = Product::factory()->for($organization)->create([
        'supplier_connection_id' => $connection->id,
    ]);

    $this
        ->actingAs($user)
        ->patch(route('products.update', ['current_organization' => $organization->slug, 'product' => $product->id]), compliancePayload($connection))
        ->assertSessionHasNoErrors();

    expect($product->fresh())
        ->age_grading->toBe('3+')
        ->safety_notice->toBe('Keep the packaging until the product has been checked.')
        ->warning_text->toBe('Not suitable for children under 3 years. Small parts.')
        ->material_information->toBe('ABS plastic, neodymium magnets, water based paint.')
        ->usage_restrictions->toBe('Indoor use only. Not for use in water.')
        ->safety_instructions->toBe('Inspect for damage before each use and replace broken parts.')
        ->additional_notes->toBe('Replacement parts are available from the manufacturer.');
});

test('a product can be created without any compliance details', function () {
    [$user, $organization] = newOrganizationMember();
    $connection = newSupplierConnection($organization);

    $this
        ->actingAs($user)
        ->post(route('products.store', ['current_organization' => $organization->slug]), [
            'name' => 'Organic Oat Milk',
            'product_category_id' => legalFamily($organization)->id,
            'product_template_id' => familyTemplate($organization)->id,
            'supplier_connection_id' => $connection->id,
        ])
        ->assertSessionHasNoErrors();

    expect(Product::sole())
        ->age_grading->toBeNull()
        ->safety_notice->toBeNull()
        ->warning_text->toBeNull()
        ->material_information->toBeNull()
        ->usage_restrictions->toBeNull()
        ->safety_instructions->toBeNull()
        ->additional_notes->toBeNull();
});

test('a compliance detail can be cleared by submitting an empty value', function (string $field) {
    [$user, $organization] = newOrganizationMember();
    $connection = newSupplierConnection($organization);

    $product = Product::factory()->for($organization)->withComplianceDetails()->create([
        'supplier_connection_id' => $connection->id,
    ]);

    $this
        ->actingAs($user)
        ->patch(route('products.update', ['current_organization' => $organization->slug, 'product' => $product->id]), compliancePayload($connection, [
            $field => '',
        ]))
        ->assertSessionHasNoErrors();

    expect($product->fresh()->{$field})->toBeNull();
})->with(complianceFields());

test('the age grading is limited to the column length', function () {
    [$user, $organization] = newOrganizationMember();
    $connection = newSupplierConnection($organization);

    $this
        ->actingAs($user)
        ->post(route('products.store', ['current_organization' => $organization->slug]), compliancePayload($connection, [
            'age_grading' => str_repeat('a', 256),
        ]))
        ->assertSessionHasErrors(['age_grading' => 'The age grading field must not be greater than 255 characters.']);

    $this->assertDatabaseCount('products', 0);
});

test('a compliance note is limited to five thousand characters', function (string $field) {
    [$user, $organization] = newOrganizationMember();
    $connection = newSupplierConnection($organization);

    $this
        ->actingAs($user)
        ->post(route('products.store', ['current_organization' => $organization->slug]), compliancePayload($connection, [
            $field => str_repeat('a', 5001),
        ]))
        ->assertSessionHasErrors($field);

    $this->assertDatabaseCount('products', 0);
})->with([
    'safety_notice',
    'warning_text',
    'material_information',
    'usage_restrictions',
    'safety_instructions',
    'additional_notes',
]);

test('the compliance details are shared with the product page', function () {
    [$user, $organization] = newOrganizationMember();
    $connection = newSupplierConnection($organization);

    $product = Product::factory()->for($organization)->withComplianceDetails()->create([
        'supplier_connection_id' => $connection->id,
    ]);

    $this
        ->actingAs($user)
        ->get(route('products.edit', ['current_organization' => $organization->slug, 'product' => $product->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('products/edit')
            ->where('product.age_grading', '3+')
            ->where('product.warning_text', 'Not suitable for children under 3 years. Small parts.')
            ->where('product.usage_restrictions', 'Indoor use only. Not for use in water.'),
        );
});

/**
 * The prose belongs to the product's own page. A catalog of a thousand rows
 * showing nothing but names would otherwise carry seven paragraphs each.
 */
test('the compliance details are left off the product list', function () {
    [$user, $organization] = newOrganizationMember();
    $connection = newSupplierConnection($organization);

    Product::factory()->for($organization)->withComplianceDetails()->create([
        'supplier_connection_id' => $connection->id,
    ]);

    $this
        ->actingAs($user)
        ->get(route('products.index', ['current_organization' => $organization->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('products.data', 1)
            ->missing('products.data.0.safety_notice')
            ->missing('products.data.0.warning_text'),
        );
});

test('a supplier fills in the compliance details of a product assigned to them', function () {
    [$supplierUser, $supplier] = newSupplierMember();
    [, $distributor] = newOrganizationMember();

    $connection = newSupplierConnection($distributor, $supplier);

    $product = Product::factory()->for($distributor)->create([
        'supplier_connection_id' => $connection->id,
    ]);

    $this
        ->actingAs($supplierUser)
        ->patch(route('products.update', ['current_organization' => $supplier->slug, 'product' => $product->id]), [
            'name' => $product->name,
            'product_category_id' => $product->product_category_id,
            'product_template_id' => $product->product_template_id,
            'warning_text' => 'Contains small parts.',
            'age_grading' => '6+',
        ])
        ->assertSessionHasNoErrors();

    expect($product->fresh())
        ->warning_text->toBe('Contains small parts.')
        ->age_grading->toBe('6+');
});

test('a member cannot change the compliance details', function () {
    [$user, $organization] = newOrganizationMember(OrganizationRole::Member);
    $connection = newSupplierConnection($organization);

    $product = Product::factory()->for($organization)->create([
        'supplier_connection_id' => $connection->id,
    ]);

    $this
        ->actingAs($user)
        ->patch(route('products.update', ['current_organization' => $organization->slug, 'product' => $product->id]), compliancePayload($connection))
        ->assertForbidden();

    expect($product->fresh()->warning_text)->toBeNull();
});
