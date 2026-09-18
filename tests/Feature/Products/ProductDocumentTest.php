<?php

use App\Enums\OrganizationRole;
use App\Enums\ProductDocumentType;
use App\Enums\SupplierConnectionStatus;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductDocument;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Create a distributor owner holding a product a supplier is responsible for.
 *
 * @return array{0: User, 1: Organization, 2: Product}
 */
function distributorWithFiledProduct(OrganizationRole $role = OrganizationRole::Owner): array
{
    [$user, $organization] = newOrganizationMember($role);
    $connection = newSupplierConnection($organization);

    $product = Product::factory()->for($organization)->create([
        'name' => 'Organic Oat Milk',
        'supplier_connection_id' => $connection->id,
    ]);

    return [$user, $organization, $product];
}

/**
 * File a document against a product through the endpoint.
 */
function fileDocument(Organization $organization, Product $product, UploadedFile $file, string $type = 'test_report'): TestResponse
{
    return test()->post(route('products.documents.store', [
        'current_organization' => $organization->slug,
        'product' => $product->id,
    ]), ['type' => $type, 'file' => $file]);
}

test('a document is filed against a product', function () {
    Storage::fake(ProductDocument::DISK);

    [$user, $organization, $product] = distributorWithFiledProduct();

    $this->actingAs($user);

    fileDocument($organization, $product, UploadedFile::fake()->create('en71-part-1.pdf', 120, 'application/pdf'))
        ->assertRedirect(route('products.edit', ['current_organization' => $organization->slug, 'product' => $product->id]));

    $document = ProductDocument::sole();

    expect($document)
        ->product_id->toBe($product->id)
        ->type->toBe(ProductDocumentType::TestReport)
        ->name->toBe('en71-part-1.pdf')
        ->mime_type->toBe('application/pdf')
        ->size->toBe(120 * 1024)
        ->uploaded_by->toBe($user->id);

    Storage::disk(ProductDocument::DISK)->assertExists($document->path);
});

/**
 * The reason this is a table rather than a column per kind: a product needs
 * a test report per component and a certificate per standard.
 */
test('several documents of the same kind can be filed against one product', function () {
    Storage::fake(ProductDocument::DISK);

    [$user, $organization, $product] = distributorWithFiledProduct();

    $this->actingAs($user);

    fileDocument($organization, $product, UploadedFile::fake()->create('magnets.pdf', 10, 'application/pdf'))
        ->assertSessionHasNoErrors();
    fileDocument($organization, $product, UploadedFile::fake()->create('paint.pdf', 10, 'application/pdf'))
        ->assertSessionHasNoErrors();

    $documents = $product->documents()->get();

    expect($documents)->toHaveCount(2)
        ->and($documents->pluck('name')->all())->toBe(['magnets.pdf', 'paint.pdf'])
        ->and($documents->pluck('path')->unique())->toHaveCount(2);
});

test('the name the file was uploaded with never decides where it is stored', function () {
    Storage::fake(ProductDocument::DISK);

    [$user, $organization, $product] = distributorWithFiledProduct();

    $this->actingAs($user);

    fileDocument($organization, $product, UploadedFile::fake()->create('../../secrets.pdf', 10, 'application/pdf'))
        ->assertSessionHasNoErrors();

    $document = ProductDocument::sole();

    expect($document->path)
        ->toStartWith('product-documents/'.$product->id.'/')
        ->not->toContain('..');
});

test('a document must be one of the known kinds', function () {
    Storage::fake(ProductDocument::DISK);

    [$user, $organization, $product] = distributorWithFiledProduct();

    $this
        ->actingAs($user)
        ->post(route('products.documents.store', ['current_organization' => $organization->slug, 'product' => $product->id]), [
            'type' => 'invoice',
            'file' => UploadedFile::fake()->create('invoice.pdf', 10, 'application/pdf'),
        ])
        ->assertSessionHasErrors(['type' => 'Choose one of the available kinds of document.']);

    $this->assertDatabaseCount('product_documents', 0);
});

test('a kind and a file are both required', function () {
    [$user, $organization, $product] = distributorWithFiledProduct();

    $this
        ->actingAs($user)
        ->post(route('products.documents.store', ['current_organization' => $organization->slug, 'product' => $product->id]), [])
        ->assertSessionHasErrors([
            'type' => 'Choose what kind of document this is.',
            'file' => 'Choose a file to upload.',
        ]);

    $this->assertDatabaseCount('product_documents', 0);
});

test('a file larger than ten megabytes is refused', function () {
    Storage::fake(ProductDocument::DISK);

    [$user, $organization, $product] = distributorWithFiledProduct();

    $this->actingAs($user);

    fileDocument($organization, $product, UploadedFile::fake()->create('report.pdf', 10241, 'application/pdf'))
        ->assertSessionHasErrors(['file' => 'The file must be no larger than 10 MB.']);

    $this->assertDatabaseCount('product_documents', 0);
});

test('a file that is neither a document nor an image is refused', function () {
    Storage::fake(ProductDocument::DISK);

    [$user, $organization, $product] = distributorWithFiledProduct();

    $this->actingAs($user);

    fileDocument($organization, $product, UploadedFile::fake()->create('reports.zip', 10, 'application/zip'))
        ->assertSessionHasErrors(['file' => 'Upload a PDF, an image, or a Word or Excel file.']);

    $this->assertDatabaseCount('product_documents', 0);
});

test('an image can be filed as a product image', function () {
    Storage::fake(ProductDocument::DISK);

    [$user, $organization, $product] = distributorWithFiledProduct();

    $this->actingAs($user);

    fileDocument($organization, $product, UploadedFile::fake()->image('packaging.png'), 'product_image')
        ->assertSessionHasNoErrors();

    expect(ProductDocument::sole()->type)->toBe(ProductDocumentType::ProductImage);
});

test('the file is handed back under the name it was uploaded with', function () {
    Storage::fake(ProductDocument::DISK);

    [$user, $organization, $product] = distributorWithFiledProduct();

    $this->actingAs($user);

    fileDocument($organization, $product, UploadedFile::fake()->create('en71-part-1.pdf', 10, 'application/pdf'));

    $this
        ->actingAs($user)
        ->get(route('products.documents.show', [
            'current_organization' => $organization->slug,
            'product' => $product->id,
            'document' => ProductDocument::sole()->id,
        ]))
        ->assertOk()
        ->assertDownload('en71-part-1.pdf');
});

test('the documents of a product are shared with its page', function () {
    [$user, $organization, $product] = distributorWithFiledProduct();

    ProductDocument::factory()
        ->for($product)
        ->ofType(ProductDocumentType::DeclarationOfConformity)
        ->create(['name' => 'doc.pdf', 'size' => 2048, 'uploaded_by' => $user->id]);

    $this
        ->actingAs($user)
        ->get(route('products.edit', ['current_organization' => $organization->slug, 'product' => $product->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('product.documents', 1)
            ->where('product.documents.0.type', 'declaration_of_conformity')
            ->where('product.documents.0.type_label', 'Declaration of conformity')
            ->where('product.documents.0.name', 'doc.pdf')
            ->where('product.documents.0.size', 2048)
            ->where('product.documents.0.uploaded_by', $user->name)
            ->has('availableDocumentTypes', 8),
        );
});

test('a supplier files and reads the documents of a product assigned to them', function () {
    Storage::fake(ProductDocument::DISK);

    [$supplierUser, $supplier] = newSupplierMember();
    [, $distributor] = newOrganizationMember();

    $connection = newSupplierConnection($distributor, $supplier);
    $product = Product::factory()->for($distributor)->create(['supplier_connection_id' => $connection->id]);

    $this->actingAs($supplierUser);

    fileDocument($supplier, $product, UploadedFile::fake()->create('conformity.pdf', 10, 'application/pdf'), 'declaration_of_conformity')
        ->assertSessionHasNoErrors();

    $document = ProductDocument::sole();

    expect($document->uploaded_by)->toBe($supplierUser->id);

    $this
        ->actingAs($supplierUser)
        ->get(route('products.documents.show', [
            'current_organization' => $supplier->slug,
            'product' => $product->id,
            'document' => $document->id,
        ]))
        ->assertOk();
});

test('a supplier whose connection is revoked can no longer reach the documents', function () {
    [$supplierUser, $supplier] = newSupplierMember();
    [, $distributor] = newOrganizationMember();

    $connection = newSupplierConnection($distributor, $supplier);
    $product = Product::factory()->for($distributor)->create(['supplier_connection_id' => $connection->id]);
    $document = ProductDocument::factory()->for($product)->create();

    $connection->update(['status' => SupplierConnectionStatus::Revoked]);

    $this
        ->actingAs($supplierUser)
        ->get(route('products.documents.show', [
            'current_organization' => $supplier->slug,
            'product' => $product->id,
            'document' => $document->id,
        ]))
        ->assertNotFound();
});

test('a document filed against another product cannot be reached through this one', function () {
    [$user, $organization, $product] = distributorWithFiledProduct();

    $otherProduct = Product::factory()->for($organization)->create();
    $otherDocument = ProductDocument::factory()->for($otherProduct)->create();

    $this
        ->actingAs($user)
        ->get(route('products.documents.show', [
            'current_organization' => $organization->slug,
            'product' => $product->id,
            'document' => $otherDocument->id,
        ]))
        ->assertNotFound();
});

test('the documents of another organization product cannot be reached', function () {
    [$user, $organization] = newOrganizationMember();

    $otherProduct = Product::factory()->create();
    $otherDocument = ProductDocument::factory()->for($otherProduct)->create();

    $this
        ->actingAs($user)
        ->get(route('products.documents.show', [
            'current_organization' => $organization->slug,
            'product' => $otherProduct->id,
            'document' => $otherDocument->id,
        ]))
        ->assertNotFound();
});

test('a member can read the documents but cannot file or delete them', function () {
    Storage::fake(ProductDocument::DISK);

    [$user, $organization, $product] = distributorWithFiledProduct(OrganizationRole::Member);
    $document = ProductDocument::factory()->for($product)->create();

    $this->actingAs($user);

    fileDocument($organization, $product, UploadedFile::fake()->create('report.pdf', 10, 'application/pdf'))
        ->assertForbidden();

    $this
        ->actingAs($user)
        ->delete(route('products.documents.destroy', [
            'current_organization' => $organization->slug,
            'product' => $product->id,
            'document' => $document->id,
        ]))
        ->assertForbidden();

    $this->assertDatabaseCount('product_documents', 1);
});

test('deleting a document takes the file with it', function () {
    Storage::fake(ProductDocument::DISK);

    [$user, $organization, $product] = distributorWithFiledProduct();

    $this->actingAs($user);

    fileDocument($organization, $product, UploadedFile::fake()->create('report.pdf', 10, 'application/pdf'));

    $document = ProductDocument::sole();

    $this
        ->actingAs($user)
        ->delete(route('products.documents.destroy', [
            'current_organization' => $organization->slug,
            'product' => $product->id,
            'document' => $document->id,
        ]))
        ->assertRedirect(route('products.edit', ['current_organization' => $organization->slug, 'product' => $product->id]));

    $this->assertModelMissing($document);
    Storage::disk(ProductDocument::DISK)->assertMissing($document->path);
});

/**
 * The foreign key takes the rows without firing a model event, so the files
 * have to be cleared deliberately or they outlive everything that named them.
 */
test('deleting a product takes the files of its documents with it', function () {
    Storage::fake(ProductDocument::DISK);

    [$user, $organization, $product] = distributorWithFiledProduct();

    $this->actingAs($user);

    fileDocument($organization, $product, UploadedFile::fake()->create('report.pdf', 10, 'application/pdf'));

    $path = ProductDocument::sole()->path;

    $this
        ->actingAs($user)
        ->delete(route('products.destroy', ['current_organization' => $organization->slug, 'product' => $product->id]))
        ->assertRedirect(route('products.index', ['current_organization' => $organization->slug]));

    $this->assertDatabaseCount('product_documents', 0);
    Storage::disk(ProductDocument::DISK)->assertMissing($path);
});

test('guests are redirected to the login page', function () {
    $organization = Organization::factory()->create();
    $product = Product::factory()->for($organization)->create();
    $document = ProductDocument::factory()->for($product)->create();

    $this
        ->get(route('products.documents.show', [
            'current_organization' => $organization->slug,
            'product' => $product->id,
            'document' => $document->id,
        ]))
        ->assertRedirect(route('login'));
});
