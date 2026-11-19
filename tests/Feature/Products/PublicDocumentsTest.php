<?php

use App\Enums\OrganizationRole;
use App\Enums\ProductDocumentType;
use App\Enums\ProductEventType;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductDocument;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Storage::fake(ProductDocument::DISK);
});

/**
 * A real, two-page PDF, as Ghostscript has to be able to read it.
 */
function samplePdf(): string
{
    return Pdf::loadHTML('<h1>EN 71-1 test report</h1><p style="page-break-after: always">Page one.</p><p>Page two.</p>')->output();
}

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
    Storage::disk(ProductDocument::DISK)->put($path, samplePdf());

    $document = ProductDocument::factory()->for($product)->ofType(ProductDocumentType::TestReport)->create([
        'name' => 'report.pdf',
        'path' => $path,
        'mime_type' => 'application/pdf',
        'size' => 2048,
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
            ->where('documents.0.url', route('products.public.document', ['product' => $product->uuid, 'document' => $document->id]))
            ->where('documents.0.downloadUrl', route('products.public.document.download', ['product' => $product->uuid, 'document' => $document->id])));

    $this->get(route('products.public.document', ['product' => $product->uuid, 'document' => $document->id]))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition', 'inline; filename=report.pdf');

    $this->get(route('products.public.document.download', ['product' => $product->uuid, 'document' => $document->id]))
        ->assertOk()
        ->assertDownload('report.pdf');
});

test('a released PDF is handed out watermarked, stamped once and kept', function () {
    [, , $product, $document] = productWithReport();
    $document->forceFill(['is_public' => true])->save();
    $original = Storage::disk(ProductDocument::DISK)->get($document->path);

    $url = route('products.public.document', ['product' => $product->uuid, 'document' => $document->id]);

    $first = $this->get($url)->assertOk()->streamedContent();
    $second = $this->get($url)->assertOk()->streamedContent();

    $copies = Storage::disk(ProductDocument::DISK)->files(ProductDocument::directoryFor($product).'/watermarked');

    expect($first)->toStartWith('%PDF')
        ->not->toBe($original)
        ->and($second)->toBe($first)
        ->and($copies)->toHaveCount(1)
        ->and(Storage::disk(ProductDocument::DISK)->get($document->path))->toBe($original);
});

test('a new watermark text gets a fresh copy and the old copy goes', function () {
    [, , $product, $document] = productWithReport();
    $document->forceFill(['is_public' => true])->save();
    $url = route('products.public.document', ['product' => $product->uuid, 'document' => $document->id]);
    $directory = ProductDocument::directoryFor($product).'/watermarked';

    $this->get($url)->assertOk();
    $before = Storage::disk(ProductDocument::DISK)->files($directory);

    config(['watermark.text' => 'swiss-product-seal.ch']);
    $this->get($url)->assertOk();
    $after = Storage::disk(ProductDocument::DISK)->files($directory);

    expect($after)->toHaveCount(1)->not->toBe($before);
});

test('a PDF that cannot be watermarked is not handed out at all', function () {
    Process::fake(['*' => Process::result(errorOutput: 'Unrecoverable error', exitCode: 1)]);

    [, , $product, $document] = productWithReport();
    $document->forceFill(['is_public' => true])->save();

    $this->get(route('products.public.document', ['product' => $product->uuid, 'document' => $document->id]))
        ->assertNotFound();

    expect(Storage::disk(ProductDocument::DISK)->files(ProductDocument::directoryFor($product).'/watermarked'))->toBeEmpty();
});

test('a deleted document takes its watermarked copies with it', function () {
    [, , $product, $document] = productWithReport();
    $document->forceFill(['is_public' => true])->save();

    $this->get(route('products.public.document', ['product' => $product->uuid, 'document' => $document->id]))->assertOk();

    $document->delete();

    expect(Storage::disk(ProductDocument::DISK)->files(ProductDocument::directoryFor($product).'/watermarked'))->toBeEmpty();
});

test('a Word file is never handed out in public, even one released before the rule', function () {
    [, , $product] = productWithReport();

    $path = ProductDocument::directoryFor($product).'/manual.docx';
    Storage::disk(ProductDocument::DISK)->put($path, 'PK word');

    $manual = ProductDocument::factory()->for($product)->ofType(ProductDocumentType::ManualOrInstructions)->create([
        'name' => 'manual.docx',
        'path' => $path,
        'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ]);
    $manual->forceFill(['is_public' => true])->save();

    $this->get($product->publicUrl())
        ->assertInertia(fn (Assert $page) => $page->where('documents', []));

    $this->get(route('products.public.document', ['product' => $product->uuid, 'document' => $manual->id]))
        ->assertNotFound();

    $this->get(route('products.public.document.download', ['product' => $product->uuid, 'document' => $manual->id]))
        ->assertNotFound();
});

test('a Word file cannot be released, but one released before can be taken back', function () {
    [$user, $organization, $product] = productWithReport();

    $manual = ProductDocument::factory()->for($product)->ofType(ProductDocumentType::ManualOrInstructions)->create([
        'name' => 'manual.docx',
        'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ]);
    $route = route('products.documents.visibility', ['current_organization' => $organization->slug, 'product' => $product->id, 'document' => $manual->id]);

    $this->actingAs($user)
        ->patch($route, ['is_public' => true])
        ->assertSessionHasErrors('is_public');

    expect($manual->fresh()->is_public)->toBeFalse();

    $manual->forceFill(['is_public' => true])->save();

    $this->actingAs($user)
        ->patch($route, ['is_public' => false])
        ->assertSessionHasNoErrors();

    expect($manual->fresh()->is_public)->toBeFalse();
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
            ->where('importer.name', $organization->name)
            ->missing('product.supplier_connection_id'));
});
