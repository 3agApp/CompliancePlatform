<?php

use App\Enums\OrganizationRole;
use App\Enums\ProductDocumentType;
use App\Enums\ProductEventType;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductDocument;
use App\Models\User;
use App\Support\PublicDocumentRenderer;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Process;
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

    Process::fake(['*pdfinfo*' => Process::result("Title: report\nPages:          2\n")]);

    $this->get(route('products.public.document', ['product' => $product->uuid, 'document' => $document->id]))
        ->assertOk()
        ->assertJsonPath('name', 'report.pdf')
        ->assertJsonPath('pages', [
            route('products.public.document.page', ['product' => $product->uuid, 'document' => $document->id, 'page' => 1]),
            route('products.public.document.page', ['product' => $product->uuid, 'document' => $document->id, 'page' => 2]),
        ]);
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

test('a released PDF is never handed over as the file that was uploaded', function () {
    [, , $product, $document] = productWithReport();
    $document->forceFill(['is_public' => true])->save();
    Process::fake(['*pdfinfo*' => Process::result("Pages: 1\n")]);

    $response = $this->get(route('products.public.document', ['product' => $product->uuid, 'document' => $document->id]))
        ->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff');

    expect($response->headers->get('Content-Disposition'))->toBeNull()
        ->and($response->getContent())->not->toContain('%PDF');
});

test('a page past the end of the document does not exist', function () {
    [, , $product, $document] = productWithReport();
    $document->forceFill(['is_public' => true])->save();
    Process::fake(['*pdfinfo*' => Process::result("Pages: 2\n")]);

    $this->get(route('products.public.document.page', ['product' => $product->uuid, 'document' => $document->id, 'page' => 3]))
        ->assertNotFound();

    $this->get(route('products.public.document.page', ['product' => $product->uuid, 'document' => $document->id, 'page' => 0]))
        ->assertNotFound();
});

test('a page of a document that is not public does not exist', function () {
    [, , $product, $document] = productWithReport();

    $this->get(route('products.public.document.page', ['product' => $product->uuid, 'document' => $document->id, 'page' => 1]))
        ->assertNotFound();
});

test('a PDF poppler cannot read reads as missing rather than as an error', function () {
    [, , $product, $document] = productWithReport();
    $document->forceFill(['is_public' => true])->save();
    Process::fake(['*pdfinfo*' => Process::result(errorOutput: 'Syntax Error', exitCode: 1)]);

    $this->get(route('products.public.document', ['product' => $product->uuid, 'document' => $document->id]))
        ->assertNotFound();
});

test('a released PDF page is drawn as a watermarked picture and kept', function () {
    if (! is_executable(trim((string) shell_exec('command -v pdftoppm')))) {
        $this->markTestSkipped('poppler is not installed.');
    }

    [, , $product, $document] = productWithReport();
    Storage::disk(ProductDocument::DISK)->put($document->path, Pdf::loadHTML('<h1>EN 71-1 test report</h1>')->output());
    $document->forceFill(['is_public' => true])->save();

    $response = $this->get(route('products.public.document.page', ['product' => $product->uuid, 'document' => $document->id, 'page' => 1]))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/webp')
        ->assertHeader('Content-Disposition', 'inline');

    expect(substr($response->getContent(), 8, 4))->toBe('WEBP')
        ->and(Storage::disk(ProductDocument::DISK)->allFiles(PublicDocumentRenderer::directoryFor($document)))->toHaveCount(1);
});

test('taking a document back or deleting it throws its drawn pages away', function () {
    [, , $product, $document] = productWithReport();
    $document->forceFill(['is_public' => true])->save();

    $cached = PublicDocumentRenderer::directoryFor($document).'/abc/1.webp';
    Storage::disk(ProductDocument::DISK)->put($cached, 'webp');

    $document->forceFill(['is_public' => false])->save();

    Storage::disk(ProductDocument::DISK)->assertMissing($cached);

    Storage::disk(ProductDocument::DISK)->put($cached, 'webp');
    $document->delete();

    Storage::disk(ProductDocument::DISK)->assertMissing($cached);
});

test('a Word file cannot be drawn and is still handed over as a download', function () {
    [, , $product] = productWithReport();
    $path = ProductDocument::directoryFor($product).'/manual.docx';
    Storage::disk(ProductDocument::DISK)->put($path, 'docx');

    $document = ProductDocument::factory()->for($product)->ofType(ProductDocumentType::ManualOrInstructions)->create([
        'name' => 'manual.docx',
        'path' => $path,
        'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'is_public' => true,
    ]);

    $this->get($product->publicUrl())
        ->assertInertia(fn (Assert $page) => $page->where('documents.0.viewable', false));

    $this->get(route('products.public.document', ['product' => $product->uuid, 'document' => $document->id]))
        ->assertOk()
        ->assertDownload('manual.docx');
});

test('the public page names the importer with the imprint they keep, and never the supplier', function () {
    [, $organization, $product] = productWithReport();
    $organization->update(['city' => 'Zürich', 'vat_number' => 'CHE-123.456.789 MWST']);

    $this->get($product->publicUrl())
        ->assertInertia(fn (Assert $page) => $page
            ->where('importer.name', $organization->name)
            ->where('importer.profile.city', 'Zürich')
            ->where('importer.profile.vat_number', 'CHE-123.456.789 MWST')
            ->missing('supplier')
            ->missing('product.supplier_connection_id'));
});

test('released warning pictograms sit beside the warning and are served as they are', function () {
    [, , $product] = productWithReport();
    $path = ProductDocument::directoryFor($product).'/age-warning.png';
    Storage::disk(ProductDocument::DISK)->put($path, UploadedFile::fake()->image('age-warning.png')->getContent());

    $pictogram = ProductDocument::factory()->for($product)->ofType(ProductDocumentType::SafetyImage)->create([
        'name' => 'age-warning.png',
        'path' => $path,
        'mime_type' => 'image/png',
        'is_public' => true,
    ]);

    $this->get($product->publicUrl())
        ->assertInertia(fn (Assert $page) => $page
            ->has('warningPictograms', 1)
            ->where('warningPictograms.0.id', $pictogram->id)
            ->where('documents', []));

    $this->get(route('products.public.image', ['product' => $product->uuid, 'document' => $pictogram->id]))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png');
});

test('a warning pictogram stays off the public page until it is released', function () {
    [, , $product] = productWithReport();
    $pictogram = ProductDocument::factory()->for($product)->ofType(ProductDocumentType::SafetyImage)->create([
        'name' => 'age-warning.png',
        'mime_type' => 'image/png',
    ]);

    $this->get($product->publicUrl())
        ->assertInertia(fn (Assert $page) => $page->where('warningPictograms', []));

    $this->get(route('products.public.image', ['product' => $product->uuid, 'document' => $pictogram->id]))
        ->assertNotFound();
});

test('a picture drawn with a palette is still served as a watermarked WebP', function () {
    [, , $product] = productWithReport();
    $path = ProductDocument::directoryFor($product).'/front.png';
    Storage::disk(ProductDocument::DISK)->put($path, base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
    ));

    $image = ProductDocument::factory()->for($product)->ofType(ProductDocumentType::ProductImage)->create([
        'name' => 'front.png',
        'path' => $path,
        'mime_type' => 'image/png',
    ]);

    $response = $this->get(route('products.public.image', ['product' => $product->uuid, 'document' => $image->id]))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/webp');

    expect(substr($response->getContent(), 8, 4))->toBe('WEBP');
});

test('a released packaging close-up joins the gallery watermarked', function () {
    [, , $product] = productWithReport();
    $path = ProductDocument::directoryFor($product).'/back.png';
    Storage::disk(ProductDocument::DISK)->put($path, UploadedFile::fake()->image('back.png', 400, 300)->getContent());

    $closeUp = ProductDocument::factory()->for($product)->ofType(ProductDocumentType::PackagingPhoto)->create([
        'name' => 'back.png',
        'path' => $path,
        'mime_type' => 'image/png',
    ]);

    $this->get($product->publicUrl())
        ->assertInertia(fn (Assert $page) => $page->where('images', []));

    $closeUp->forceFill(['is_public' => true])->save();

    $this->get($product->publicUrl())
        ->assertInertia(fn (Assert $page) => $page
            ->has('images', 1)
            ->where('images.0.id', $closeUp->id)
            ->where('documents', []));

    $this->get(route('products.public.image', ['product' => $product->uuid, 'document' => $closeUp->id]))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/webp');
});
