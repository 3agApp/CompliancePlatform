<?php

use App\Enums\ProductDocumentType;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductDocument;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

/**
 * Put a product with one filed paper in front of a distributor owner.
 *
 * The disk is faked before the bytes are written. Without that the file lands
 * in the developer's real storage, where the ids restart at one and a test
 * writes over somebody's actual certificate.
 *
 * @return array{0: User, 1: Organization, 2: Product, 3: ProductDocument}
 */
function productWithFiledPaper(string $name, string $mimeType, string $bytes, ProductDocumentType $type = ProductDocumentType::TestReport): array
{
    Storage::fake(ProductDocument::DISK);

    [$user, $organization] = newOrganizationMember();
    $connection = newSupplierConnection($organization);

    $product = Product::factory()->for($organization)->create([
        'name' => 'Organic Oat Milk',
        'supplier_connection_id' => $connection->id,
    ]);

    $document = ProductDocument::factory()
        ->for($product)
        ->ofType($type)
        ->create([
            'name' => $name,
            'mime_type' => $mimeType,
            'path' => ProductDocument::directoryFor($product).'/'.$name,
            'uploaded_by' => $user->id,
        ]);

    Storage::disk(ProductDocument::DISK)->put($document->path, $bytes);

    return [$user, $organization, $product, $document];
}

test('a pdf opens in the viewer when its name is clicked', function () {
    [$user, $organization, $product, $document] = productWithFiledPaper(
        'en71-part-1.pdf',
        'application/pdf',
        "%PDF-1.4\n% a paper with a name and some bytes\n",
    );

    $this->actingAs($user);

    visit(route('products.edit', [
        'current_organization' => $organization->slug,
        'product' => $product->id,
        'tab' => 'documents',
    ]))
        ->assertSee('en71-part-1.pdf')
        ->click('@product-document-preview')
        ->assertPresent('@preview-document-modal')
        ->assertPresent('@document-preview-frame')
        /* The kind is named in the dialog, so a paper opened from a long list says what it is. */
        ->assertSeeIn('@preview-document-modal', 'Test report')
        /* The file itself is still one click away, for when a glance is not enough. */
        ->assertPresent('@document-preview-download')
        ->assertScript(
            "document.querySelector('[data-test=\"document-preview-frame\"]').data.endsWith('/documents/{$document->id}/preview')",
            true,
        )
        /*
         * A browser with no PDF viewer -- the headless one this runs in, and
         * a good many phones -- shows the fallback rather than an empty box.
         */
        ->assertPresent('@document-preview-new-tab')
        ->assertNoJavaScriptErrors();
});

test('an image opens as a picture rather than in a frame', function () {
    [$user, $organization, $product] = productWithFiledPaper(
        'packaging.png',
        'image/png',
        base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8AAAwAB/wFbqlPUAAAAAElFTkSuQmCC'),
        ProductDocumentType::ProductImage,
    );

    $this->actingAs($user);

    visit(route('products.edit', [
        'current_organization' => $organization->slug,
        'product' => $product->id,
        'tab' => 'documents',
    ]))
        ->click('@product-document-preview')
        ->assertPresent('@document-preview-image')
        ->assertMissing('@document-preview-frame')
        /*
         * Really drawn, not merely requested: a broken image is present in
         * the page too, and says nothing about whether the preview works.
         */
        ->assertScript(
            "document.querySelector('[data-test=\"document-preview-image\"]').naturalWidth > 0",
            true,
        )
        ->assertNoJavaScriptErrors();
});

/**
 * Photos are recognised by sight, so they get a gallery. A paper filed as a
 * picture is still looked for under its kind.
 */
test('product photos form a gallery while a scanned certificate stays with the certificates', function () {
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8AAAwAB/wFbqlPUAAAAAElFTkSuQmCC');

    [$user, $organization, $product] = productWithFiledPaper('packaging.png', 'image/png', $png, ProductDocumentType::ProductImage);

    $scan = ProductDocument::factory()
        ->for($product)
        ->ofType(ProductDocumentType::Certificate)
        ->create([
            'name' => 'ce-certificate-scan.png',
            'mime_type' => 'image/png',
            'path' => ProductDocument::directoryFor($product).'/ce-certificate-scan.png',
            'uploaded_by' => $user->id,
        ]);

    Storage::disk(ProductDocument::DISK)->put($scan->path, $png);

    $this->actingAs($user);

    visit(route('products.edit', [
        'current_organization' => $organization->slug,
        'product' => $product->id,
        'tab' => 'documents',
    ]))
        ->assertScript(
            "[...document.querySelectorAll('[data-test=\"product-document-images\"] [data-test=\"product-document-preview\"]')].map((name) => name.textContent)",
            ['packaging.png'],
        )
        ->assertSee('ce-certificate-scan.png')
        /** The photo is really drawn, not a broken image. */
        ->assertScript(
            "document.querySelector('[data-test=\"product-document-images\"] img').naturalWidth > 0",
            true,
        )
        ->assertNoJavaScriptErrors();
});

test('a file the browser cannot show is a download rather than a preview', function () {
    [$user, $organization, $product] = productWithFiledPaper(
        'manual.docx',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'a word file',
        ProductDocumentType::ManualOrInstructions,
    );

    $this->actingAs($user);

    visit(route('products.edit', [
        'current_organization' => $organization->slug,
        'product' => $product->id,
        'tab' => 'documents',
    ]))
        ->assertSee('manual.docx')
        /* No viewer is offered for it, because there is nothing to view it with. */
        ->assertMissing('@product-document-preview')
        ->assertPresent('@product-document-download')
        ->assertMissing('@preview-document-modal')
        ->assertNoJavaScriptErrors();
});
