<?php

use App\Enums\OrganizationRole;
use App\Enums\ProductRequirement;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductTemplate;

/**
 * The payload the template editor sends: a name, and a flag for every
 * requirement in the register whether or not it is ticked.
 *
 * Sending them all is the point. A box that is cleared has to arrive as a
 * "0", or an edit could only ever add requirements and never take one away.
 *
 * @param  array<int, ProductRequirement>  $requirements
 * @return array<string, mixed>
 */
function templatePayload(string $name = 'EU toy safety', array $requirements = []): array
{
    $asked = array_map(fn (ProductRequirement $requirement) => $requirement->value, $requirements);

    return [
        'name' => $name,
        ...collect(ProductRequirement::columns())
            ->mapWithKeys(fn (string $column) => [
                $column => in_array($column, $asked, true) ? '1' : '0',
            ])
            ->all(),
    ];
}

/**
 * Build the route arguments for one category's templates.
 *
 * @return array<string, mixed>
 */
function templateRoute(ProductCategory $category, ?ProductTemplate $template = null): array
{
    return [
        'current_organization' => $category->organization->slug,
        'product_category' => $category->id,
        ...$template === null ? [] : ['template' => $template->id],
    ];
}

test('a category starts with no templates, because what it asks for is the distributor own reading', function () {
    [, $distributor] = newOrganizationMember();

    expect($distributor->productTemplates()->count())->toBe(0);
});

test('a template is created under a category with the requirements it asks for', function () {
    [$user, $distributor] = newOrganizationMember();
    $toy = legalFamily($distributor);

    $this
        ->actingAs($user)
        ->post(route('categories.templates.store', templateRoute($toy)), templatePayload('EU toy safety', [
            ProductRequirement::TestReport,
            ProductRequirement::WarningText,
        ]))
        ->assertRedirect(route('categories.index', ['current_organization' => $distributor->slug]))
        ->assertSessionHasNoErrors();

    $template = ProductTemplate::sole();

    expect($template)
        ->name->toBe('EU toy safety')
        ->product_category_id->toBe($toy->id)
        ->requires_test_report->toBeTrue()
        ->requires_warning_text->toBeTrue()
        ->requires_declaration_of_conformity->toBeFalse();
});

test('a template asking for nothing is allowed, because the requirements are filled in over time', function () {
    [$user, $distributor] = newOrganizationMember();

    $this
        ->actingAs($user)
        ->post(route('categories.templates.store', templateRoute(legalFamily($distributor))), templatePayload('Blank'))
        ->assertSessionHasNoErrors();

    expect(ProductTemplate::sole()->requirements())->toBeEmpty();
});

test('editing a template turns a requirement back off', function () {
    [$user, $distributor] = newOrganizationMember();
    $toy = legalFamily($distributor);

    $template = ProductTemplate::factory()
        ->requiring(ProductRequirement::TestReport, ProductRequirement::Ean)
        ->create(['product_category_id' => $toy->id]);

    $this
        ->actingAs($user)
        ->patch(
            route('categories.templates.update', templateRoute($toy, $template)),
            templatePayload('EU toy safety', [ProductRequirement::TestReport]),
        )
        ->assertSessionHasNoErrors();

    expect($template->fresh())
        ->name->toBe('EU toy safety')
        ->requires_test_report->toBeTrue()
        ->requires_ean->toBeFalse();
});

test('a template name has to be free within its category, whatever its casing', function () {
    [$user, $distributor] = newOrganizationMember();
    $toy = legalFamily($distributor);

    ProductTemplate::factory()->create(['product_category_id' => $toy->id, 'name' => 'Standard']);

    $this
        ->actingAs($user)
        ->post(route('categories.templates.store', templateRoute($toy)), templatePayload('standard'))
        ->assertSessionHasErrors('name');

    expect($toy->templates()->count())->toBe(1);
});

test('two categories may each have a template of the same name', function () {
    [$user, $distributor] = newOrganizationMember();

    ProductTemplate::factory()->create([
        'product_category_id' => legalFamily($distributor, 'Toy')->id,
        'name' => 'Standard',
    ]);

    $this
        ->actingAs($user)
        ->post(
            route('categories.templates.store', templateRoute(legalFamily($distributor, 'Filter'))),
            templatePayload('Standard'),
        )
        ->assertSessionHasNoErrors();

    expect(ProductTemplate::query()->where('name', 'Standard')->count())->toBe(2);
});

test('a template can be renamed without disturbing its own uniqueness check', function () {
    [$user, $distributor] = newOrganizationMember();
    $toy = legalFamily($distributor);

    $template = ProductTemplate::factory()->create(['product_category_id' => $toy->id, 'name' => 'Standard']);

    $this
        ->actingAs($user)
        ->patch(route('categories.templates.update', templateRoute($toy, $template)), templatePayload('Standard', [
            ProductRequirement::Certificate,
        ]))
        ->assertSessionHasNoErrors();

    expect($template->fresh()->requires_certificate)->toBeTrue();
});

test('a template nothing is held to is deleted', function () {
    [$user, $distributor] = newOrganizationMember();
    $toy = legalFamily($distributor);

    $template = ProductTemplate::factory()->create(['product_category_id' => $toy->id]);

    $this
        ->actingAs($user)
        ->delete(route('categories.templates.destroy', templateRoute($toy, $template)))
        ->assertSessionHasNoErrors();

    expect(ProductTemplate::query()->count())->toBe(0);
});

test('a template still held to by a product is kept, and says how many are in the way', function () {
    [$user, $distributor] = newOrganizationMember();
    $toy = legalFamily($distributor);

    $template = ProductTemplate::factory()->create(['product_category_id' => $toy->id, 'name' => 'EU toy safety']);
    Product::factory()->count(2)->for($distributor)->usingTemplate($template)->create();

    $this
        ->actingAs($user)
        ->delete(route('categories.templates.destroy', templateRoute($toy, $template)))
        ->assertRedirect(route('categories.index', ['current_organization' => $distributor->slug]))
        ->assertSessionHas('inertia.flash_data.toast', [
            'type' => 'error',
            'message' => 'EU toy safety is still used by 2 products. Move them to another template first.',
        ]);

    expect(ProductTemplate::query()->count())->toBe(1);
});

test('the categories page carries each category templates and what they ask for', function () {
    [$user, $distributor] = newOrganizationMember();
    $toy = legalFamily($distributor);

    $template = ProductTemplate::factory()
        ->requiring(ProductRequirement::TestReport)
        ->create(['product_category_id' => $toy->id, 'name' => 'EU toy safety']);

    Product::factory()->for($distributor)->usingTemplate($template)->create();

    $this
        ->actingAs($user)
        ->get(route('categories.index', ['current_organization' => $distributor->slug]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('categories.0.templates', [])
            ->where('categories.2.templates', [[
                'id' => $template->id,
                'name' => 'EU toy safety',
                'products_count' => 1,
                'requirements' => ['requires_test_report'],
            ]])
            ->has('availableRequirements', count(ProductRequirement::cases())),
        );
});

test('a template cannot be added to another organization category', function () {
    [$user, $distributor] = newOrganizationMember();
    [, $otherDistributor] = newOrganizationMember();

    $this
        ->actingAs($user)
        ->post(route('categories.templates.store', [
            'current_organization' => $distributor->slug,
            'product_category' => legalFamily($otherDistributor)->id,
        ]), templatePayload())
        ->assertNotFound();

    expect(ProductTemplate::query()->count())->toBe(0);
});

test('a template cannot be reached through another category of the same organization', function () {
    [$user, $distributor] = newOrganizationMember();

    $template = ProductTemplate::factory()->create([
        'product_category_id' => legalFamily($distributor, 'Toy')->id,
    ]);

    $this
        ->actingAs($user)
        ->delete(route('categories.templates.destroy', [
            'current_organization' => $distributor->slug,
            'product_category' => legalFamily($distributor, 'Filter')->id,
            'template' => $template->id,
        ]))
        ->assertNotFound();

    expect(ProductTemplate::query()->count())->toBe(1);
});

test('a supplier has no template screen at all', function () {
    [$supplierUser, $supplier] = newSupplierMember();
    [, $distributor] = newOrganizationMember();

    $toy = legalFamily($distributor);

    $this
        ->actingAs($supplierUser)
        ->post(route('categories.templates.store', [
            'current_organization' => $supplier->slug,
            'product_category' => $toy->id,
        ]), templatePayload())
        ->assertNotFound();
});

test('a member cannot manage templates', function () {
    [$user, $distributor] = newOrganizationMember(OrganizationRole::Member);
    $toy = legalFamily($distributor);

    $template = ProductTemplate::factory()->create(['product_category_id' => $toy->id]);

    $this
        ->actingAs($user)
        ->post(route('categories.templates.store', templateRoute($toy)), templatePayload())
        ->assertForbidden();

    $this
        ->actingAs($user)
        ->patch(route('categories.templates.update', templateRoute($toy, $template)), templatePayload())
        ->assertForbidden();

    $this
        ->actingAs($user)
        ->delete(route('categories.templates.destroy', templateRoute($toy, $template)))
        ->assertForbidden();
});

test('deleting a category takes its templates with it', function () {
    [$user, $distributor] = newOrganizationMember();

    $category = ProductCategory::factory()->for($distributor)->create(['name' => 'Retired family']);
    ProductTemplate::factory()->count(2)->create(['product_category_id' => $category->id]);

    $this
        ->actingAs($user)
        ->delete(route('categories.destroy', [
            'current_organization' => $distributor->slug,
            'product_category' => $category->id,
        ]))
        ->assertSessionHasNoErrors();

    expect(ProductTemplate::query()->count())->toBe(0);
});

test('the fillable attributes of a template match the requirement register', function () {
    $fillable = (new ProductTemplate)->getFillable();

    expect(array_diff(ProductRequirement::columns(), $fillable))->toBe([])
        ->and(array_diff($fillable, [...ProductRequirement::columns(), 'name']))->toBe([]);
});
