<?php

use App\Actions\Organizations\DeleteOrganization;
use App\Enums\OrganizationRole;
use App\Models\Brand;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductDocument;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Storage::fake(ProductDocument::DISK);
});

/**
 * Upload a logo for a brand through the endpoint, as the given user.
 */
function uploadBrandLogo(User $user, Organization $organization, Brand $brand, ?UploadedFile $file = null): TestResponse
{
    return test()
        ->actingAs($user)
        ->post(route('brands.logo.store', ['current_organization' => $organization->slug, 'brand' => $brand->id]), [
            'logo' => $file ?? UploadedFile::fake()->image('logo.png', 200, 200),
        ]);
}

test('a brand is given a logo, shown on the brands page to both sides of the trade', function () {
    [$user, $distributor] = newOrganizationMember();
    [$supplierUser, $supplier] = newSupplierMember();
    $brand = newSupplierConnection($distributor, $supplier)->brands()->create(['name' => 'tigerbox']);

    uploadBrandLogo($user, $distributor, $brand)->assertSessionHasNoErrors();

    $brand->refresh();

    expect($brand->logo_path)->toStartWith('brand-logos/'.$brand->id.'/');
    Storage::disk(ProductDocument::DISK)->assertExists($brand->logo_path);

    $this->actingAs($user)
        ->get(route('brands.index', ['current_organization' => $distributor->slug]))
        ->assertInertia(fn (Assert $page) => $page->where('connections.0.brands.0.logoUrl', fn (?string $url) => str_contains((string) $url, "/brands/{$brand->id}/logo")));

    $this->actingAs($supplierUser)
        ->get(route('brands.logo', ['current_organization' => $supplier->slug, 'brand' => $brand->id]))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png')
        ->assertHeader('Cache-Control', 'max-age=86400, private');
});

test('replacing a logo takes the old file away, and removing it clears it', function () {
    [$user, $distributor] = newOrganizationMember();
    $brand = newSupplierConnection($distributor)->brands()->create(['name' => 'tigerbox']);

    uploadBrandLogo($user, $distributor, $brand);
    $first = $brand->fresh()->logo_path;

    uploadBrandLogo($user, $distributor, $brand, UploadedFile::fake()->image('new.webp', 100, 100));
    $second = $brand->fresh()->logo_path;

    expect($second)->not->toBe($first);
    Storage::disk(ProductDocument::DISK)->assertMissing($first);
    Storage::disk(ProductDocument::DISK)->assertExists($second);

    $this->actingAs($user)
        ->delete(route('brands.logo.destroy', ['current_organization' => $distributor->slug, 'brand' => $brand->id]))
        ->assertSessionHasNoErrors();

    expect($brand->fresh()->logo_path)->toBeNull();
    Storage::disk(ProductDocument::DISK)->assertMissing($second);
});

test('a logo must be a raster image', function () {
    [$user, $distributor] = newOrganizationMember();
    $brand = newSupplierConnection($distributor)->brands()->create(['name' => 'tigerbox']);

    uploadBrandLogo($user, $distributor, $brand, UploadedFile::fake()->create('logo.svg', 4, 'image/svg+xml'))
        ->assertSessionHasErrors('logo');

    expect($brand->fresh()->logo_path)->toBeNull();
});

test('a member may see a brand logo but not change it', function () {
    [$user, $distributor] = newOrganizationMember(OrganizationRole::Member);
    $brand = newSupplierConnection($distributor)->brands()->create(['name' => 'tigerbox']);

    uploadBrandLogo($user, $distributor, $brand)->assertForbidden();

    $this->actingAs($user)
        ->delete(route('brands.logo.destroy', ['current_organization' => $distributor->slug, 'brand' => $brand->id]))
        ->assertForbidden();

    $brand->forceFill(['logo_path' => UploadedFile::fake()->image('logo.png')->store($brand->logoDirectory(), ProductDocument::DISK)])->save();

    $this->actingAs($user)
        ->get(route('brands.logo', ['current_organization' => $distributor->slug, 'brand' => $brand->id]))
        ->assertOk();
});

test('a brand logo of another organization cannot be reached', function () {
    [$user, $distributor] = newOrganizationMember();
    [, $other] = newOrganizationMember();
    $foreign = newSupplierConnection($other)->brands()->create(['name' => 'tigerbox']);
    $foreign->forceFill(['logo_path' => UploadedFile::fake()->image('logo.png')->store($foreign->logoDirectory(), ProductDocument::DISK)])->save();

    $this->actingAs($user)
        ->get(route('brands.logo', ['current_organization' => $distributor->slug, 'brand' => $foreign->id]))
        ->assertNotFound();

    uploadBrandLogo($user, $distributor, $foreign)->assertNotFound();
});

test('the public page shows the logo of the product brand, reached through the product', function () {
    [, $distributor] = newOrganizationMember();
    $connection = newSupplierConnection($distributor);
    $brand = $connection->brands()->create(['name' => 'tigerbox']);
    $product = Product::factory()->for($distributor)->create(['supplier_connection_id' => $connection->id, 'brand_id' => $brand->id]);

    $this->get($product->publicUrl())
        ->assertInertia(fn (Assert $page) => $page->where('product.brandLogoUrl', null));

    $this->get(route('products.public.brand-logo', ['product' => $product->uuid]))->assertNotFound();

    $brand->forceFill(['logo_path' => UploadedFile::fake()->image('logo.png')->store($brand->logoDirectory(), ProductDocument::DISK)])->save();

    $this->get($product->publicUrl())
        ->assertInertia(fn (Assert $page) => $page->where('product.brandLogoUrl', fn (?string $url) => str_contains((string) $url, "/p/{$product->uuid}/brand-logo")));

    $this->get(route('products.public.brand-logo', ['product' => $product->uuid]))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png');
});

test('deleting a brand, or the organization behind it, takes its logo with it', function () {
    [$user, $distributor] = newOrganizationMember();
    $connection = newSupplierConnection($distributor);

    $deleted = $connection->brands()->create(['name' => 'tigerbox']);
    $kept = $connection->brands()->create(['name' => 'Magna-Tiles']);

    uploadBrandLogo($user, $distributor, $deleted);
    uploadBrandLogo($user, $distributor, $kept);

    $this->actingAs($user)
        ->delete(route('brands.destroy', ['current_organization' => $distributor->slug, 'brand' => $deleted->id]));

    Storage::disk(ProductDocument::DISK)->assertMissing($deleted->logoDirectory());
    Storage::disk(ProductDocument::DISK)->assertExists($kept->fresh()->logo_path);

    app(DeleteOrganization::class)->handle($distributor);

    Storage::disk(ProductDocument::DISK)->assertMissing($kept->logoDirectory());
});
