<?php

use App\Enums\OrganizationRole;
use App\Enums\ProductDocumentType;
use App\Enums\ProductEventType;
use App\Enums\ProductRequirement;
use App\Enums\ProductReviewStatus;
use App\Enums\ProductSealStatus;
use App\Models\Product;
use App\Models\ProductDocument;
use App\Models\ProductTemplate;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * A product held to a template that actually asks for something, so its
 * completeness score is a real number rather than the hundred a template
 * asking for nothing scores by definition.
 */
function productWithTemplate(array $attributes = [], ProductReviewStatus $status = ProductReviewStatus::Draft): array
{
    [$user, $organization] = newOrganizationMember();
    $connection = newSupplierConnection($organization);

    $template = ProductTemplate::factory()
        ->requiring(ProductRequirement::Ean, ProductRequirement::WarningText, ProductRequirement::TestReport)
        ->create(['product_category_id' => legalFamily($organization)->id]);

    $product = Product::factory()
        ->for($organization)
        ->usingTemplate($template)
        ->withoutOptionalDetails()
        ->reviewed($status)
        ->create([
            'name' => 'Magnetic Building Set',
            'supplier_connection_id' => $connection->id,
            ...$attributes,
        ]);

    return [$user, $organization, $product];
}

test('a product is given a uuid and answers to it in public without an account', function () {
    [, , $product] = productWithTemplate(['ean' => '7612345678900', 'internal_article_number' => 'ART-4711']);

    expect($product->uuid)->not->toBeEmpty();

    $this
        ->get(route('products.public', ['product' => $product->uuid]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('products/public')
            ->where('product.name', 'Magnetic Building Set')
            ->where('product.ean', '7612345678900')
            ->where('product.internal_article_number', 'ART-4711')
            ->where('product.uuid', $product->uuid),
        );
});

test('the public page says nothing about the trade behind the product', function () {
    [, , $product] = productWithTemplate();

    $this
        ->get(route('products.public', ['product' => $product->uuid]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->missing('product.supplier_connection_id')
            ->missing('product.counterparty')
            ->missing('product.category_label')
            ->missing('product.documents')
            ->missing('permissions'),
        );
});

test('a uuid nobody holds is not a page', function () {
    $this->get(route('products.public', ['product' => '3f2504e0-4f89-11d3-9a0c-0305e82c3301']))->assertNotFound();
});

test('an approved product is verified and shows the day it passed', function () {
    [, , $product] = productWithTemplate(status: ProductReviewStatus::Approved);

    $this
        ->get(route('products.public', ['product' => $product->uuid]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('seal.status', 'verified')
            ->where('seal.label', 'Verified')
            ->where('seal.isOverridden', false)
            ->where('seal.approvedAt', $product->reviewed_at?->toISOString()),
        );
});

test('a product with something filled in is a check in progress, with how far along it is', function () {
    [, , $product] = productWithTemplate(['ean' => '7612345678900']);

    /** One of the two points its template asks for, and none of the three the report is worth. */
    $this
        ->get(route('products.public', ['product' => $product->uuid]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('seal.status', 'in_progress')
            ->where('seal.score', 20)
            ->where('seal.approvedAt', null),
        );
});

test('a product nobody has started is not verified', function () {
    [, , $product] = productWithTemplate();

    $this
        ->get(route('products.public', ['product' => $product->uuid]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('seal.status', 'not_verified')
            ->where('seal.score', 0),
        );
});

test('a hand-set seal stands in front of the review and says that it was set', function () {
    [$user, $organization, $product] = productWithTemplate();

    $this
        ->actingAs($user)
        ->patch(route('products.seal.update', ['current_organization' => $organization->slug, 'product' => $product->id]), [
            'seal' => ProductSealStatus::Verified->value,
            'reason' => 'Certified under the previous article number.',
        ])
        ->assertSessionHasNoErrors();

    $this
        ->get(route('products.public', ['product' => $product->uuid]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('seal.status', 'verified')
            ->where('seal.isOverridden', true)
            /** Nothing approved it, so there is no day to show. */
            ->where('seal.approvedAt', null),
        );

    $event = $product->events()->sole();

    expect($event->type)->toBe(ProductEventType::SealOverridden)
        ->and($event->note)->toBe('Certified under the previous article number.')
        ->and($event->changes['seal_override']['to'])->toBe('Verified');
});

test('clearing the override hands the seal back to the review', function () {
    [$user, $organization, $product] = productWithTemplate(status: ProductReviewStatus::Approved);

    $product->forceFill([
        'seal_override' => ProductSealStatus::NotVerified,
        'seal_override_reason' => 'Held back while the report is re-issued.',
        'seal_overridden_by' => $user->id,
        'seal_overridden_at' => now(),
    ])->save();

    $this
        ->get(route('products.public', ['product' => $product->uuid]))
        ->assertInertia(fn (Assert $page) => $page->where('seal.status', 'not_verified'));

    $this
        ->actingAs($user)
        ->patch(route('products.seal.update', ['current_organization' => $organization->slug, 'product' => $product->id]), ['seal' => null])
        ->assertSessionHasNoErrors();

    expect($product->fresh()->seal_override)->toBeNull()
        ->and($product->fresh()->seal_overridden_by)->toBeNull()
        ->and($product->events()->sole()->type)->toBe(ProductEventType::SealOverrideCleared);

    $this
        ->get(route('products.public', ['product' => $product->uuid]))
        ->assertInertia(fn (Assert $page) => $page->where('seal.status', 'verified'));
});

test('a seal cannot be set by hand without a reason', function () {
    [$user, $organization, $product] = productWithTemplate();

    $this
        ->actingAs($user)
        ->patch(route('products.seal.update', ['current_organization' => $organization->slug, 'product' => $product->id]), [
            'seal' => ProductSealStatus::Verified->value,
        ])
        ->assertSessionHasErrors('reason');

    expect($product->fresh()->seal_override)->toBeNull();
});

test('only the distributor owner or admin may set the seal by hand', function () {
    [, $organization, $product] = productWithTemplate();

    [$member] = newOrganizationMember();
    $organization->members()->attach($member, ['role' => OrganizationRole::Member->value]);
    $member->switchOrganization($organization);

    $this
        ->actingAs($member)
        ->patch(route('products.seal.update', ['current_organization' => $organization->slug, 'product' => $product->id]), [
            'seal' => ProductSealStatus::Verified->value,
            'reason' => 'Because I say so.',
        ])
        ->assertForbidden();

    expect($product->fresh()->seal_override)->toBeNull();
});

test('the supplier being checked cannot set the seal on what they supply', function () {
    [, $distributor] = newOrganizationMember();
    [$supplierUser, $supplier] = newSupplierMember();

    $connection = newSupplierConnection($distributor, $supplier);

    $product = Product::factory()->for($distributor)->create([
        'supplier_connection_id' => $connection->id,
    ]);

    $this
        ->actingAs($supplierUser)
        ->patch(route('products.seal.update', ['current_organization' => $supplier->slug, 'product' => $product->id]), [
            'seal' => ProductSealStatus::Verified->value,
            'reason' => 'Ours is fine.',
        ])
        ->assertForbidden();

    expect($product->fresh()->seal_override)->toBeNull();
});

test('the public page shows the pictures of the article and serves them without an account', function () {
    Storage::fake('local');

    [$user, $organization, $product] = productWithTemplate();

    $this
        ->actingAs($user)
        ->post(route('products.documents.store', ['current_organization' => $organization->slug, 'product' => $product->id]), [
            'documents' => [
                ['type' => ProductDocumentType::ProductImage->value, 'file' => UploadedFile::fake()->image('front.png')],
            ],
        ])
        ->assertSessionHasNoErrors();

    $image = $product->documents()->sole();

    $this
        ->get(route('products.public', ['product' => $product->uuid]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('images', 1)
            ->where('images.0.id', $image->id),
        );

    $this
        ->get(route('products.public.image', ['product' => $product->uuid, 'document' => $image->id]))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png')
        ->assertHeader('X-Content-Type-Options', 'nosniff');
});

test('no paper other than a picture of the article is reachable in public', function () {
    [, , $product] = productWithTemplate();

    $report = ProductDocument::factory()
        ->for($product)
        ->ofType(ProductDocumentType::TestReport)
        ->create(['name' => 'en71-part-1.pdf', 'mime_type' => 'application/pdf']);

    /** Filed as a picture, but a PDF: a frame the browser cannot draw. */
    $pdfImage = ProductDocument::factory()
        ->for($product)
        ->ofType(ProductDocumentType::ProductImage)
        ->create(['name' => 'sheet.pdf', 'mime_type' => 'application/pdf']);

    $this
        ->get(route('products.public', ['product' => $product->uuid]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('images', 0));

    $this->get(route('products.public.image', ['product' => $product->uuid, 'document' => $report->id]))->assertNotFound();
    $this->get(route('products.public.image', ['product' => $product->uuid, 'document' => $pdfImage->id]))->assertNotFound();
});

test('a picture whose file is gone is a missing page rather than a crash', function () {
    Storage::fake('local');

    [, , $product] = productWithTemplate();

    $image = ProductDocument::factory()
        ->for($product)
        ->ofType(ProductDocumentType::ProductImage)
        ->create(['mime_type' => 'image/png']);

    $this
        ->get(route('products.public.image', ['product' => $product->uuid, 'document' => $image->id]))
        ->assertNotFound();
});

test('a picture of one product is not reachable through another product address', function () {
    [, , $product] = productWithTemplate();
    [, , $other] = productWithTemplate();

    $image = ProductDocument::factory()
        ->for($other)
        ->ofType(ProductDocumentType::ProductImage)
        ->create(['mime_type' => 'image/png']);

    $this
        ->get(route('products.public.image', ['product' => $product->uuid, 'document' => $image->id]))
        ->assertNotFound();
});
