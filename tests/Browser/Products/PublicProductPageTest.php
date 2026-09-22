<?php

use App\Enums\ProductDocumentType;
use App\Enums\ProductRequirement;
use App\Enums\ProductReviewStatus;
use App\Enums\ProductSealStatus;
use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductDocument;
use App\Models\ProductTemplate;
use Illuminate\Support\Facades\Storage;

/**
 * A one pixel PNG, written where the document row says its file is.
 *
 * The public page draws the picture, so there has to be one: a row pointing
 * at nothing renders a broken frame and the test would pass on it.
 */
function aStoredImage(Product $product, string $name = 'front.png'): ProductDocument
{
    $path = ProductDocument::directoryFor($product).'/'.$name;

    Storage::disk(ProductDocument::DISK)->put($path, base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
    ));

    return ProductDocument::factory()
        ->for($product)
        ->ofType(ProductDocumentType::ProductImage)
        ->create(['name' => $name, 'path' => $path, 'mime_type' => 'image/png']);
}

/**
 * A product held to a template that asks for something, so the score on its
 * seal is a real reading rather than the hundred an empty template scores.
 */
function publishableProduct(array $attributes = [], ProductReviewStatus $status = ProductReviewStatus::Draft): Product
{
    [, $organization] = newOrganizationMember();
    $connection = newSupplierConnection($organization);

    $template = ProductTemplate::factory()
        ->requiring(ProductRequirement::Ean, ProductRequirement::WarningText, ProductRequirement::TestReport)
        ->create(['product_category_id' => legalFamily($organization)->id]);

    return Product::factory()
        ->for($organization)
        ->usingTemplate($template)
        ->withoutOptionalDetails()
        ->reviewed($status)
        ->create([
            'name' => 'Magnetic Building Set',
            'supplier_connection_id' => $connection->id,
            'brand_id' => Brand::factory()->for($connection, 'supplierConnection')->create(['name' => 'Brio'])->id,
            ...$attributes,
        ]);
}

test('the public page is the article, its numbers and an unfinished check', function () {
    Storage::fake(ProductDocument::DISK);

    $product = publishableProduct([
        'ean' => '7612345678900',
        'internal_article_number' => 'ART-4711',
    ]);

    aStoredImage($product);

    $page = visit(route('products.public', ['product' => $product->uuid]));

    $page->assertSee('Magnetic Building Set')
        ->assertSee('Brio')
        ->assertSee('7612345678900')
        ->assertSee('ART-4711')
        ->assertPresent('@product-photo')
        /** The check is running, so the seal is amber and carries the bar. */
        ->assertSee('In progress')
        ->assertSee('The compliance check has not finished yet.')
        ->assertPresent('@product-seal-bar')
        ->assertSee('20%')
        /** And none of the trade behind it is on the page. */
        ->assertDontSee('Supplier')
        ->assertDontSee('Requirements')
        ->assertNoJavaScriptErrors();
});

test('an approved product carries a verified seal with the day it passed', function () {
    $product = publishableProduct(status: ProductReviewStatus::Approved);

    $page = visit(route('products.public', ['product' => $product->uuid]));

    $page->assertSee('Verified')
        ->assertSee('This product meets Swiss compliance requirements.')
        ->assertPresent('@product-seal-approved-at')
        ->assertSee('Approved')
        /** A passed check has nothing left to be a percentage of. */
        ->assertMissing('@product-seal-bar')
        ->assertNoJavaScriptErrors();
});

test('a product nobody has started reads as not verified, with no bar', function () {
    $product = publishableProduct();

    $page = visit(route('products.public', ['product' => $product->uuid]));

    $page->assertSee('Not verified')
        ->assertSee('No completed compliance check yet.')
        ->assertMissing('@product-seal-bar')
        ->assertSee('No picture of this article yet')
        ->assertNoJavaScriptErrors();
});

test('a hand-set seal is shown as set rather than earned', function () {
    $product = publishableProduct();

    $product->forceFill([
        'seal_override' => ProductSealStatus::Verified,
        'seal_override_reason' => 'Certified under the previous article number.',
        'seal_overridden_at' => now(),
    ])->save();

    $page = visit(route('products.public', ['product' => $product->uuid]));

    $page->assertSee('Verified')
        ->assertSee('Set by the distributor rather than by a completed check.')
        /** Nothing approved it, so there is no date to claim. */
        ->assertMissing('@product-seal-approved-at')
        ->assertNoJavaScriptErrors();
});

test('a distributor sets the public seal by hand from the product page', function () {
    [$user, $organization] = newOrganizationMember();
    $connection = newSupplierConnection($organization);

    $product = Product::factory()->for($organization)->create([
        'name' => 'Magnetic Building Set',
        'supplier_connection_id' => $connection->id,
    ]);

    $this->actingAs($user);

    $page = visit(route('products.edit', [
        'current_organization' => $organization->slug,
        'product' => $product->id,
    ]));

    $page->assertSee('Public page')
        ->assertPresent('@product-public-url')
        ->click('@product-override-seal')
        ->assertSee('The seal normally follows this product')
        ->click('@seal-choice')
        ->click('@seal-option-verified')
        ->fill('@seal-reason', 'Certified under the previous article number.')
        ->click('@seal-confirm')
        ->assertSee('Public seal updated.')
        ->assertSee('Set by the distributor rather than by a completed check.')
        ->assertNoJavaScriptErrors();

    expect($product->fresh()->seal_override)->toBe(ProductSealStatus::Verified);
});
