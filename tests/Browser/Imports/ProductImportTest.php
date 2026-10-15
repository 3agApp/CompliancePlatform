<?php

use App\Models\ImportSource;
use App\Models\Product;
use App\Models\ProductDocument;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;

const BROWSER_IMPORT_API = 'https://kontor.example.test/api/v1/kontor';

test('an owner connects kontor from the import page', function () {
    [$user, $organization] = newOrganizationMember();

    $this->actingAs($user);

    visit(route('products.index', $organization))
        ->click('@products-product-import-button')
        ->assertSee('Product import')
        ->type('#import-api-url', BROWSER_IMPORT_API)
        ->type('#import-api-key', 'kontor-browser-key-4321')
        ->click('@save-import-source-submit')
        ->assertSee('Import source saved.')
        ->assertAttribute('#import-api-key', 'placeholder', '•••• 4321 — leave blank to keep this key')
        ->assertPresent('@import-manufacturers-section')
        ->assertNoJavaScriptErrors();

    expect($organization->importSource->api_key)->toBe('kontor-browser-key-4321');
});

test('an owner picks a manufacturer and imports its products', function () {
    Storage::fake(ProductDocument::DISK);
    Sleep::fake();
    [$user, $organization] = newOrganizationMember();
    ImportSource::factory()->for($organization)->create([
        'api_url' => BROWSER_IMPORT_API,
        'file_url_prefix' => null,
        'default_product_template_id' => familyTemplate($organization)->id,
    ]);

    Http::fake([BROWSER_IMPORT_API.'/search' => function ($request) {
        if ($request['entity'] === 'manufacturer') {
            return Http::response(kontorEnvelope([['Herstellerid' => '038', 'Hersteller' => 'Grimms']]));
        }

        return Http::response(kontorEnvelope($request['paging']['skip'] === 0 ? [kontorRow(['Ws_aktiv' => false])] : []));
    }]);

    $this->actingAs($user);

    visit(route('product-import.show', $organization))
        ->click('@fetch-import-manufacturers-button')
        ->assertSeeIn('@import-manufacturer-list', 'Grimms')
        ->click('@import-manufacturer-checkbox')
        ->click('@save-import-manufacturers-button')
        ->assertSee('Manufacturers saved.')
        ->click('@start-import-button')
        ->assertSee('Import started.')
        ->assertSeeIn('@import-run-status', 'Finished')
        ->assertSeeIn('@import-run-created', '1')
        ->assertNoJavaScriptErrors();

    expect(Product::query()->sole()->is_active)->toBeFalse();
});

test('a product no longer sold is marked in the list', function () {
    [$user, $organization] = newOrganizationMember();
    $product = Product::factory()->for($organization)->create(['name' => 'Retired dolphin']);
    $product->forceFill(['is_active' => false])->save();

    $this->actingAs($user);

    visit(route('products.index', $organization))
        ->assertSeeIn('@product-inactive-badge', 'Inactive')
        ->assertNoJavaScriptErrors();
});
