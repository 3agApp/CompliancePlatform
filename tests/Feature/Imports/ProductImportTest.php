<?php

use App\Actions\Imports\StartProductImport;
use App\Ai\Agents\ProductTemplateAgent;
use App\Enums\AiProvider;
use App\Enums\ImportExistingProductAction;
use App\Enums\ImportMatchKey;
use App\Enums\ImportRunStatus;
use App\Enums\ProductDocumentType;
use App\Enums\ProductEventType;
use App\Enums\SupplierConnectionStatus;
use App\Models\ImportRun;
use App\Models\ImportSource;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductDocument;
use App\Models\ProductTemplate;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;

const IMPORT_API = 'https://kontor.example.test/api/v1/kontor';
const IMPORT_FILES = 'https://images.example.test/products';

beforeEach(function () {
    Sleep::fake();
    Storage::fake(ProductDocument::DISK);
    Notification::fake();
    Mail::fake();
});

/**
 * A distributor with a Kontor source and one chosen manufacturer, Grimms.
 *
 * @param  array<string, mixed>  $sourceAttributes
 * @return array{0: User, 1: Organization, 2: ImportSource}
 */
function distributorWithImportSource(array $sourceAttributes = []): array
{
    [$user, $organization] = newOrganizationMember();

    $source = ImportSource::factory()->for($organization)->create([
        'api_url' => IMPORT_API,
        'file_url_prefix' => IMPORT_FILES,
        'default_product_template_id' => familyTemplate($organization)->id,
        ...$sourceAttributes,
    ]);

    $source->manufacturers()->create(['external_id' => '038', 'name' => 'Grimms']);

    return [$user, $organization, $source];
}

/**
 * Answer Kontor's search with the given rows on the first page and nothing
 * after, once per run, and every image with a small jpeg.
 *
 * @param  array<int, array<string, mixed>>  $rows
 */
function fakeKontorCatalogue(array $rows, int $runs = 1): void
{
    $sequence = Http::sequence();

    for ($run = 0; $run < $runs; $run++) {
        $sequence->push(kontorEnvelope($rows))->push(kontorEnvelope([]));
    }

    Http::fake([
        IMPORT_API.'/search' => $sequence,
        IMPORT_FILES.'/*' => Http::response('jpeg-bytes', 200, ['Content-Type' => 'image/jpeg']),
    ]);
}

function runImport(User $user, Organization $organization): ImportRun
{
    return app(StartProductImport::class)->handle($organization, $user)->fresh();
}

test('an article becomes a product filed under the manufacturer as a new supplier', function () {
    [$user, $organization] = distributorWithImportSource();
    fakeKontorCatalogue([kontorRow(['Ws_aktiv' => false])]);

    $run = runImport($user, $organization);

    $product = $organization->products()->sole();
    $supplier = $product->supplierConnection;

    expect($run->status)->toBe(ImportRunStatus::Completed)
        ->and($run->created)->toBe(1)
        ->and($run->processed)->toBe(1)
        ->and($product->name)->toBe('Steckfigur zwei Delphine')
        ->and($product->internal_article_number)->toBe('gri04040')
        ->and($product->ean)->toBe('4048565040402')
        ->and($product->supplier_article_number)->toBe('04040')
        ->and($product->short_description)->toBe('Diese kleine handbemalte Steckfigur.')
        ->and($product->long_description)->toContain('Lindenholz')
        ->and($product->is_active)->toBeFalse()
        ->and($product->product_template_id)->toBe(familyTemplate($organization)->id)
        ->and($product->brand->name)->toBe('Grimms')
        ->and($supplier->company_name)->toBe('Grimms')
        ->and($supplier->contact_email)->toBeNull()
        ->and($supplier->status)->toBe(SupplierConnectionStatus::Pending)
        ->and($supplier->isInvited())->toBeFalse()
        ->and($product->events()->where('type', ProductEventType::Created)->exists())->toBeTrue();

    Notification::assertNothingSent();
});

test('the images are fetched from the file prefix and filed once', function () {
    [$user, $organization] = distributorWithImportSource();
    fakeKontorCatalogue([kontorRow()], runs: 2);

    runImport($user, $organization);

    $documents = $organization->products()->sole()->documents()->orderBy('id')->get();

    expect($documents->pluck('source_url')->all())->toBe([
        IMPORT_FILES.'/gri04040.jpg',
        IMPORT_FILES.'/gri04040_2.JPG',
    ])
        ->and($documents->first()->type)->toBe(ProductDocumentType::ProductImage)
        ->and($documents->first()->name)->toBe('gri04040.jpg');

    Storage::disk(ProductDocument::DISK)->assertExists($documents->first()->path);

    $fetchedBefore = Http::recorded(fn ($request) => str_starts_with($request->url(), IMPORT_FILES))->count();

    runImport($user, $organization);

    expect($organization->products()->sole()->documents()->count())->toBe(2)
        ->and(Http::recorded(fn ($request) => str_starts_with($request->url(), IMPORT_FILES))->count())->toBe($fetchedBefore);
});

test('an image that cannot be fetched is noted on the run and the product stays', function () {
    [$user, $organization] = distributorWithImportSource();
    Http::fake([
        IMPORT_API.'/search' => Http::sequence()->push(kontorEnvelope([kontorRow(['ImageURL_2' => null])]))->push(kontorEnvelope([])),
        IMPORT_FILES.'/*' => Http::response('Not found', 404),
    ]);

    $run = runImport($user, $organization);

    expect($run->status)->toBe(ImportRunStatus::Completed)
        ->and($run->created)->toBe(1)
        ->and($run->failures)->toHaveCount(1)
        ->and($run->failures[0]['reason'])->toContain('HTTP 404')
        ->and($organization->products()->sole()->documents()->count())->toBe(0);
});

test('without a file prefix no images are fetched', function () {
    [$user, $organization] = distributorWithImportSource(['file_url_prefix' => null]);
    fakeKontorCatalogue([kontorRow()]);

    runImport($user, $organization);

    expect(ProductDocument::query()->count())->toBe(0);
});

test('an existing product is matched by the chosen key and updated', function (ImportMatchKey $key, array $existing) {
    [$user, $organization] = distributorWithImportSource(['match_key' => $key]);
    $product = Product::factory()->for($organization)->create([
        'name' => 'Old name',
        'ean' => null,
        'internal_article_number' => null,
        'supplier_article_number' => null,
        ...$existing,
    ]);
    $templateBefore = $product->product_template_id;
    $supplierBefore = $product->supplier_connection_id;
    fakeKontorCatalogue([kontorRow()]);

    $run = runImport($user, $organization);

    $product->refresh();

    expect($run->updated)->toBe(1)
        ->and($run->created)->toBe(0)
        ->and($organization->products()->count())->toBe(1)
        ->and($product->name)->toBe('Steckfigur zwei Delphine')
        ->and($product->internal_article_number)->toBe('gri04040')
        ->and($product->product_template_id)->toBe($templateBefore)
        ->and($product->supplier_connection_id)->toBe($supplierBefore)
        ->and($product->events()->where('type', ProductEventType::Updated)->sole()->changes)->toHaveKey('name');
})->with([
    'sku' => [ImportMatchKey::Sku, ['internal_article_number' => 'gri04040']],
    'ean' => [ImportMatchKey::Ean, ['ean' => '4048565040402']],
    'mpn' => [ImportMatchKey::Mpn, ['supplier_article_number' => '04040']],
]);

test('an existing product is left alone when the source says to skip it', function () {
    [$user, $organization] = distributorWithImportSource(['on_existing' => ImportExistingProductAction::Skip]);
    $product = Product::factory()->for($organization)->create(['name' => 'Kept', 'internal_article_number' => 'gri04040']);
    fakeKontorCatalogue([kontorRow()]);

    $run = runImport($user, $organization);

    expect($run->skipped)->toBe(1)
        ->and($product->fresh()->name)->toBe('Kept');
});

test('an unchanged product counts as skipped and gets no history line', function () {
    [$user, $organization] = distributorWithImportSource(['file_url_prefix' => null]);
    fakeKontorCatalogue([kontorRow()], runs: 2);
    runImport($user, $organization);
    $product = $organization->products()->sole();

    $run = runImport($user, $organization);

    expect($run->skipped)->toBe(1)
        ->and($run->updated)->toBe(0)
        ->and($product->events()->count())->toBe(1);
});

test('two products sharing the key are both left alone', function () {
    [$user, $organization] = distributorWithImportSource();
    Product::factory()->for($organization)->count(2)->create(['name' => 'Twin', 'internal_article_number' => 'gri04040']);
    fakeKontorCatalogue([kontorRow()]);

    $run = runImport($user, $organization);

    expect($run->failed)->toBe(1)
        ->and($run->failures[0]['article'])->toBe('gri04040')
        ->and($organization->products()->where('name', 'Twin')->count())->toBe(2);
});

test('a supplier already known by the manufacturer name is reused and remembered', function () {
    [$user, $organization] = distributorWithImportSource();
    $grimms = newSupplierConnection($organization, attributes: ['company_name' => 'GRIMMS']);
    fakeKontorCatalogue([kontorRow()]);

    runImport($user, $organization);

    expect($organization->products()->sole()->supplier_connection_id)->toBe($grimms->id)
        ->and($organization->importSource->manufacturers()->sole()->supplier_connection_id)->toBe($grimms->id)
        ->and($organization->supplierConnections()->count())->toBe(1);
});

test('the supplier chosen for a manufacturer wins over the name', function () {
    [$user, $organization, $source] = distributorWithImportSource();
    $chosen = newSupplierConnection($organization, attributes: ['company_name' => 'Spielwaren GmbH']);
    $source->manufacturers()->sole()->update(['supplier_connection_id' => $chosen->id]);
    fakeKontorCatalogue([kontorRow()]);

    runImport($user, $organization);

    expect($organization->products()->sole()->supplier_connection_id)->toBe($chosen->id);
});

test('a chosen supplier that was disconnected fails the article rather than filing it there', function () {
    [$user, $organization, $source] = distributorWithImportSource();
    $revoked = newSupplierConnection($organization, attributes: ['status' => SupplierConnectionStatus::Revoked]);
    $source->manufacturers()->sole()->update(['supplier_connection_id' => $revoked->id]);
    fakeKontorCatalogue([kontorRow()]);

    $run = runImport($user, $organization);

    expect($run->failed)->toBe(1)
        ->and($organization->products()->count())->toBe(0);
});

test('the manufacturer template wins over the default', function () {
    [$user, $organization, $source] = distributorWithImportSource();
    $template = familyTemplate($organization, 'Toy', 'Wooden toys');
    $source->manufacturers()->sole()->update(['product_template_id' => $template->id]);
    fakeKontorCatalogue([kontorRow()]);

    runImport($user, $organization);

    $product = $organization->products()->sole();

    expect($product->product_template_id)->toBe($template->id)
        ->and($product->product_category_id)->toBe($template->product_category_id);
});

test('a new article with no template to go under is left out and said so', function () {
    [$user, $organization] = distributorWithImportSource(['default_product_template_id' => null]);
    fakeKontorCatalogue([kontorRow()]);

    $run = runImport($user, $organization);

    expect($run->failed)->toBe(1)
        ->and($run->failures[0]['reason'])->toContain('No template could be assigned')
        ->and($organization->products()->count())->toBe(0);
});

/**
 * Give the organization an AI provider to pay with.
 */
function withAiProvider(Organization $organization): void
{
    $organization->aiSetting()->create([
        'provider' => AiProvider::Gemini,
        'model' => AiProvider::Gemini->defaultModel(),
        'api_key' => 'AIzaSyD-ImportKeyForTests-0123456789',
    ]);
}

test('the ai chooses the template when the manufacturer has none, and the product says so', function () {
    [$user, $organization] = distributorWithImportSource(['use_ai_for_templates' => true]);
    withAiProvider($organization);
    $toys = familyTemplate($organization, 'Toy', 'Wooden toys');
    ProductTemplateAgent::fake([['choices' => [['index' => 0, 'template' => (string) $toys->id, 'confidence' => 'high']]]]);
    fakeKontorCatalogue([kontorRow()]);

    $run = runImport($user, $organization);

    $product = $organization->products()->sole();

    expect($product->product_template_id)->toBe($toys->id)
        ->and($run->ai_assigned)->toBe(1)
        ->and($product->events()->where('type', ProductEventType::Created)->sole()->note)->toContain('Template suggested by AI');
});

test('a template the ai invents falls back to the default', function () {
    [$user, $organization] = distributorWithImportSource(['use_ai_for_templates' => true]);
    withAiProvider($organization);
    $foreign = ProductTemplate::factory()->create();
    ProductTemplateAgent::fake([['choices' => [['index' => 0, 'template' => (string) $foreign->id, 'confidence' => 'high']]]]);
    fakeKontorCatalogue([kontorRow()]);

    $run = runImport($user, $organization);

    expect($organization->products()->sole()->product_template_id)->toBe(familyTemplate($organization)->id)
        ->and($run->ai_assigned)->toBe(0);
});

test('a provider that fails leaves the default template in place', function () {
    [$user, $organization] = distributorWithImportSource(['use_ai_for_templates' => true]);
    withAiProvider($organization);
    ProductTemplateAgent::fake(function (): never {
        throw new RuntimeException('Provider down');
    });
    fakeKontorCatalogue([kontorRow()]);

    $run = runImport($user, $organization);

    expect($run->created)->toBe(1)
        ->and($organization->products()->sole()->product_template_id)->toBe(familyTemplate($organization)->id);
});

test('the ai is not asked about articles that already exist or whose manufacturer has a template', function () {
    [$user, $organization] = distributorWithImportSource(['use_ai_for_templates' => true]);
    withAiProvider($organization);
    Product::factory()->for($organization)->create(['internal_article_number' => 'gri04040']);
    ProductTemplateAgent::fake()->preventStrayPrompts();
    fakeKontorCatalogue([kontorRow()]);

    $run = runImport($user, $organization);

    expect($run->updated)->toBe(1);
});

test('a source that refuses fails the run with its own words', function () {
    [$user, $organization] = distributorWithImportSource();
    Http::fake([IMPORT_API.'/search' => Http::response(['success' => false, 'message' => 'Ungültiger API-Schlüssel', 'errorCode' => 'ERR-401'], 401)]);

    try {
        runImport($user, $organization);
    } catch (Throwable) {
        //
    }

    $run = $organization->importRuns()->sole();

    expect($run->status)->toBe(ImportRunStatus::Failed)
        ->and($run->failure_reason)->toBe('Ungültiger API-Schlüssel (ERR-401)');
});

test('the pages are walked until an empty one', function () {
    [$user, $organization] = distributorWithImportSource(['file_url_prefix' => null]);
    Http::fake([IMPORT_API.'/search' => Http::sequence()
        ->push(kontorEnvelope([kontorRow(['Artnr' => 'a1']), kontorRow(['Artnr' => '']), kontorRow(['Artnr' => 'a3'])], 4))
        ->push(kontorEnvelope([kontorRow(['Artnr' => 'a4'])], 4))
        ->push(kontorEnvelope([], 4))]);

    $run = runImport($user, $organization);

    expect($run->created)->toBe(3)
        ->and($run->failed)->toBe(1)
        ->and($run->processed)->toBe(4)
        ->and($run->total)->toBe(4);

    Http::assertSent(fn ($request) => ($request['paging']['skip'] ?? null) === 3);
});
