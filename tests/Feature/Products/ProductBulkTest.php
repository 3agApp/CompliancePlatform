<?php

use App\Enums\CountryOfOrigin;
use App\Enums\OrganizationRole;
use App\Enums\ProductEventType;
use App\Enums\ProductReviewStatus;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductDocument;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * @param  array<string, mixed>  $payload
 */
function bulkUpdate(User $user, Organization $organization, array $payload): TestResponse
{
    return test()
        ->actingAs($user)
        ->from(route('products.index', ['current_organization' => $organization->slug]))
        ->patch(route('products.bulk.update', ['current_organization' => $organization->slug]), $payload);
}

test('the product list offers what a bulk change may set', function () {
    [$user, $organization] = newOrganizationMember();
    familyTemplate($organization);

    $this
        ->actingAs($user)
        ->get(route('products.index', ['current_organization' => $organization->slug]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('availableTemplates', 1)
            ->has('availableCountries'),
        );
});

test('a distributor can change the active flag and country of many products at once', function () {
    [$user, $organization] = newOrganizationMember();

    $products = Product::factory()->for($organization)->count(2)->create(['is_active' => true, 'country_of_origin' => 'DE']);
    $untouched = Product::factory()->for($organization)->create(['is_active' => true]);

    bulkUpdate($user, $organization, [
        'ids' => $products->pluck('id')->all(),
        'is_active' => false,
        'country_of_origin' => 'CN',
    ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('products.index', ['current_organization' => $organization->slug]));

    foreach ($products as $product) {
        $product->refresh();

        expect($product->is_active)->toBeFalse()
            ->and($product->country_of_origin)->toBe(CountryOfOrigin::from('CN'));

        $event = $product->events()->sole();

        expect($event->type)->toBe(ProductEventType::Updated)
            ->and($event->changedFields())->toHaveCount(2);
    }

    expect($untouched->fresh()->is_active)->toBeTrue()
        ->and($untouched->events()->exists())->toBeFalse();
});

test('a field left out of a bulk change is left as each product has it', function () {
    [$user, $organization] = newOrganizationMember();

    $product = Product::factory()->for($organization)->create(['country_of_origin' => 'DE', 'is_active' => true]);

    bulkUpdate($user, $organization, ['ids' => [$product->id], 'is_active' => false])
        ->assertSessionHasNoErrors();

    expect($product->fresh())
        ->is_active->toBeFalse()
        ->country_of_origin->toBe(CountryOfOrigin::from('DE'));
});

test('a bulk change can move products to another category and template', function () {
    [$user, $organization, $connection] = distributorWithSupplier();

    $template = familyTemplate($organization, 'Toy', 'Plush');
    $products = Product::factory()->for($organization)->count(2)->create(['supplier_connection_id' => $connection->id]);

    bulkUpdate($user, $organization, [
        'ids' => $products->pluck('id')->all(),
        'product_category_id' => $template->product_category_id,
        'product_template_id' => $template->id,
    ])->assertSessionHasNoErrors();

    foreach ($products as $product) {
        expect($product->fresh())
            ->product_category_id->toBe($template->product_category_id)
            ->product_template_id->toBe($template->id);
    }
});

test('a bulk change rejects a template from another category', function () {
    [$user, $organization] = newOrganizationMember();

    $product = Product::factory()->for($organization)->create([
        'product_category_id' => legalFamily($organization)->id,
        'product_template_id' => familyTemplate($organization)->id,
    ]);
    $otherTemplate = familyTemplate($organization, 'Filter');

    bulkUpdate($user, $organization, [
        'ids' => [$product->id],
        'product_category_id' => $product->product_category_id,
        'product_template_id' => $otherTemplate->id,
    ])->assertSessionHasErrors('product_template_id');

    expect($product->fresh()->product_template_id)->not->toBe($otherTemplate->id);
});

test('a brand can be set across products of one supplier', function () {
    [$user, $organization, $connection] = distributorWithSupplier();

    $brand = carriedBrand($connection);
    $products = Product::factory()->for($organization)->count(2)->create(['supplier_connection_id' => $connection->id]);

    bulkUpdate($user, $organization, ['ids' => $products->pluck('id')->all(), 'brand_id' => $brand->id])
        ->assertSessionHasNoErrors();

    foreach ($products as $product) {
        expect($product->fresh()->brand_id)->toBe($brand->id);
    }
});

test('a brand cannot be set across products of several suppliers', function () {
    [$user, $organization, $connection] = distributorWithSupplier();

    $brand = carriedBrand($connection);
    $mine = Product::factory()->for($organization)->create(['supplier_connection_id' => $connection->id]);
    $other = Product::factory()->for($organization)->create(['supplier_connection_id' => newSupplierConnection($organization)->id]);

    bulkUpdate($user, $organization, ['ids' => [$mine->id, $other->id], 'brand_id' => $brand->id])
        ->assertSessionHasErrors('brand_id');

    expect($mine->fresh()->brand_id)->toBeNull()
        ->and($other->fresh()->brand_id)->toBeNull();
});

test('a brand of another supplier is rejected', function () {
    [$user, $organization, $connection] = distributorWithSupplier();

    $foreignBrand = carriedBrand(newSupplierConnection($organization), 'Oatly');
    $product = Product::factory()->for($organization)->create(['supplier_connection_id' => $connection->id]);

    bulkUpdate($user, $organization, ['ids' => [$product->id], 'brand_id' => $foreignBrand->id])
        ->assertSessionHasErrors('brand_id');
});

test('products moved to another supplier lose a brand that was not theirs', function () {
    [$user, $organization, $connection] = distributorWithSupplier();

    $newConnection = newSupplierConnection($organization);
    $product = Product::factory()->for($organization)->create([
        'supplier_connection_id' => $connection->id,
        'brand_id' => carriedBrand($connection)->id,
    ]);

    bulkUpdate($user, $organization, ['ids' => [$product->id], 'supplier_connection_id' => $newConnection->id])
        ->assertSessionHasNoErrors();

    expect($product->fresh())
        ->supplier_connection_id->toBe($newConnection->id)
        ->brand_id->toBeNull();
});

test('products moved to another supplier can take one of its brands in the same change', function () {
    [$user, $organization, $connection] = distributorWithSupplier();

    $newConnection = newSupplierConnection($organization);
    $newBrand = carriedBrand($newConnection, 'Oatly');
    $products = Product::factory()->for($organization)->count(2)->create(['supplier_connection_id' => $connection->id]);
    $products->push(Product::factory()->for($organization)->create(['supplier_connection_id' => newSupplierConnection($organization)->id]));

    bulkUpdate($user, $organization, [
        'ids' => $products->pluck('id')->all(),
        'supplier_connection_id' => $newConnection->id,
        'brand_id' => $newBrand->id,
    ])->assertSessionHasNoErrors();

    foreach ($products as $product) {
        expect($product->fresh())
            ->supplier_connection_id->toBe($newConnection->id)
            ->brand_id->toBe($newBrand->id);
    }
});

test('a product of another organization cannot be part of a bulk change', function () {
    [$user, $organization] = newOrganizationMember();

    $mine = Product::factory()->for($organization)->create(['is_active' => true]);
    $theirs = Product::factory()->create(['is_active' => true]);

    bulkUpdate($user, $organization, ['ids' => [$mine->id, $theirs->id], 'is_active' => false])
        ->assertNotFound();

    expect($mine->fresh()->is_active)->toBeTrue()
        ->and($theirs->fresh()->is_active)->toBeTrue();
});

test('a member who may not edit products cannot make a bulk change', function () {
    [$user, $organization] = newOrganizationMember(OrganizationRole::Member);

    $product = Product::factory()->for($organization)->create(['is_active' => true]);

    bulkUpdate($user, $organization, ['ids' => [$product->id], 'is_active' => false])
        ->assertForbidden();

    expect($product->fresh()->is_active)->toBeTrue();
});

test('a supplier can change the active flag of assigned products but not reassign them', function () {
    [$supplierUser, $supplier] = newSupplierMember();
    [, $distributor] = newOrganizationMember();

    $connection = newSupplierConnection($distributor, $supplier);
    $otherConnection = newSupplierConnection($distributor);
    $product = Product::factory()->for($distributor)->create([
        'supplier_connection_id' => $connection->id,
        'is_active' => true,
    ]);

    bulkUpdate($supplierUser, $supplier, [
        'ids' => [$product->id],
        'is_active' => false,
        'supplier_connection_id' => $otherConnection->id,
    ])->assertSessionHasNoErrors();

    expect($product->fresh())
        ->is_active->toBeFalse()
        ->supplier_connection_id->toBe($connection->id);
});

test('a supplier bulk change puts a submitted product back in draft', function () {
    [$supplierUser, $supplier] = newSupplierMember();
    [, $distributor] = newOrganizationMember();

    $connection = newSupplierConnection($distributor, $supplier);
    $product = Product::factory()->for($distributor)->create([
        'supplier_connection_id' => $connection->id,
        'country_of_origin' => 'DE',
        'review_status' => ProductReviewStatus::InReview,
        'submitted_at' => now(),
    ]);

    bulkUpdate($supplierUser, $supplier, ['ids' => [$product->id], 'country_of_origin' => 'CN'])
        ->assertSessionHasNoErrors();

    expect($product->fresh()->review_status)->toBe(ProductReviewStatus::Draft);
});

test('a distributor can delete many products and their files at once', function () {
    Storage::fake(ProductDocument::DISK);

    [$user, $organization] = newOrganizationMember();

    $products = Product::factory()->for($organization)->count(2)->create();
    $kept = Product::factory()->for($organization)->create();

    foreach ($products as $product) {
        Storage::disk(ProductDocument::DISK)->put(ProductDocument::directoryFor($product).'/report.pdf', 'pdf');
    }

    $this
        ->actingAs($user)
        ->from(route('products.index', ['current_organization' => $organization->slug]))
        ->delete(route('products.bulk.destroy', ['current_organization' => $organization->slug]), [
            'ids' => $products->pluck('id')->all(),
        ])
        ->assertRedirect(route('products.index', ['current_organization' => $organization->slug]));

    foreach ($products as $product) {
        $this->assertModelMissing($product);
        Storage::disk(ProductDocument::DISK)->assertMissing(ProductDocument::directoryFor($product).'/report.pdf');
    }

    $this->assertModelExists($kept);
});

test('a supplier cannot bulk delete assigned products', function () {
    [$supplierUser, $supplier] = newSupplierMember();
    [, $distributor] = newOrganizationMember();

    $product = Product::factory()->for($distributor)->create([
        'supplier_connection_id' => newSupplierConnection($distributor, $supplier)->id,
    ]);

    $this
        ->actingAs($supplierUser)
        ->delete(route('products.bulk.destroy', ['current_organization' => $supplier->slug]), [
            'ids' => [$product->id],
        ])
        ->assertForbidden();

    $this->assertModelExists($product);
});
