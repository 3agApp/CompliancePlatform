<?php

use App\Enums\OrganizationRole;
use App\Enums\ProductDocumentType;
use App\Enums\ProductEventType;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductDocument;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Storage::fake(ProductDocument::DISK);
});

/**
 * A distributor's product with one test report filed against it.
 *
 * @return array{0: User, 1: Organization, 2: Product, 3: ProductDocument}
 */
function productWithReport(): array
{
    [$user, $organization] = newOrganizationMember();

    $product = Product::factory()->for($organization)->create([
        'name' => 'Magnetic Building Set',
        'supplier_connection_id' => newSupplierConnection($organization)->id,
    ]);

    $path = ProductDocument::directoryFor($product).'/report.pdf';
    Storage::disk(ProductDocument::DISK)->put($path, '%PDF-1.4 report');

    $document = ProductDocument::factory()->for($product)->ofType(ProductDocumentType::TestReport)->create([
        'name' => 'report.pdf',
        'path' => $path,
        'mime_type' => 'application/pdf',
        'size' => 15,
    ]);

    return [$user, $organization, $product, $document];
}

test('a document stays off the public page until the distributor releases it', function () {
    [$user, $organization, $product, $document] = productWithReport();

    $this->get($product->publicUrl())
        ->assertInertia(fn (Assert $page) => $page->where('documents', []));

    $this->get(route('products.public.document', ['product' => $product->uuid, 'document' => $document->id]))
        ->assertNotFound();

    $this->actingAs($user)
        ->patch(route('products.documents.visibility', ['current_organization' => $organization->slug, 'product' => $product->id, 'document' => $document->id]), ['is_public' => true])
        ->assertRedirect();

    expect($document->fresh()->is_public)->toBeTrue()
        ->and($product->events()->first()->type)->toBe(ProductEventType::DocumentPublished);

    auth()->logout();

    $this->get($product->publicUrl())
        ->assertInertia(fn (Assert $page) => $page
            ->has('documents', 1)
            ->where('documents.0.type', 'test_report')
            ->where('documents.0.name', 'report.pdf')
            ->where('documents.0.url', route('products.public.document', ['product' => $product->uuid, 'document' => $document->id])));

    $this->get(route('products.public.document', ['product' => $product->uuid, 'document' => $document->id]))
        ->assertOk()
        ->assertDownload('report.pdf');
});

test('a released document can be taken back', function () {
    [$user, $organization, $product, $document] = productWithReport();
    $document->forceFill(['is_public' => true])->save();

    $this->actingAs($user)
        ->patch(route('products.documents.visibility', ['current_organization' => $organization->slug, 'product' => $product->id, 'document' => $document->id]), ['is_public' => false]);

    expect($document->fresh()->is_public)->toBeFalse()
        ->and($product->events()->first()->type)->toBe(ProductEventType::DocumentUnpublished);

    $this->get(route('products.public.document', ['product' => $product->uuid, 'document' => $document->id]))
        ->assertNotFound();
});

test('a released document of one product is not reachable through another', function () {
    [, , , $document] = productWithReport();
    [, , $other] = productWithReport();
    $document->forceFill(['is_public' => true])->save();

    $this->get(route('products.public.document', ['product' => $other->uuid, 'document' => $document->id]))
        ->assertNotFound();
});

test('only those who may edit the distributor\'s product decide what is public', function () {
    [, $organization, $product, $document] = productWithReport();

    [$member] = newOrganizationMember();
    $organization->members()->attach($member, ['role' => OrganizationRole::Member->value]);
    $member->switchOrganization($organization);

    $this->actingAs($member)
        ->patch(route('products.documents.visibility', ['current_organization' => $organization->slug, 'product' => $product->id, 'document' => $document->id]), ['is_public' => true])
        ->assertForbidden();

    [$supplierUser, $supplier] = newSupplierMember();
    $product->update(['supplier_connection_id' => newSupplierConnection($organization, $supplier)->id]);

    $this->actingAs($supplierUser)
        ->patch(route('products.documents.visibility', ['current_organization' => $supplier->slug, 'product' => $product->id, 'document' => $document->id]), ['is_public' => true])
        ->assertForbidden();

    expect($document->fresh()->is_public)->toBeFalse();
});

test('the public page carries the safety information and who places the product on the market', function () {
    [, $organization, $product] = productWithReport();
    $product->update(['warning_text' => 'Not suitable for children under 3 years.', 'age_grading' => '3+']);

    $this->get($product->publicUrl())
        ->assertInertia(fn (Assert $page) => $page
            ->where('safety.warning_text', 'Not suitable for children under 3 years.')
            ->where('safety.age_grading', '3+')
            ->where('importer', $organization->name)
            ->missing('product.supplier_connection_id'));
});
