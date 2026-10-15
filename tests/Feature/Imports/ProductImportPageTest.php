<?php

use App\Enums\ImportRunStatus;
use App\Enums\OrganizationRole;
use App\Jobs\ImportProductPage;
use App\Models\ImportRun;
use App\Models\ImportSource;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Sleep;
use Inertia\Testing\AssertableInertia as Assert;

const PAGE_API = 'https://kontor.example.test/api/v1/kontor';
const PAGE_KEY = 'kontor-secret-key-9876';

beforeEach(function () {
    Sleep::fake();
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function importSourcePayload(array $overrides = []): array
{
    return [
        'provider' => 'kontor',
        'api_url' => PAGE_API,
        'api_key' => PAGE_KEY,
        'file_url_prefix' => 'https://images.example.test/products',
        'match_key' => 'sku',
        'on_existing' => 'update',
        'default_product_template_id' => null,
        'use_ai_for_templates' => '0',
        ...$overrides,
    ];
}

test('an owner connects a source and the key is stored encrypted and never sent back', function () {
    [$user, $organization] = newOrganizationMember();
    $template = familyTemplate($organization);

    $this->actingAs($user)
        ->put(route('product-import.source.update', $organization), importSourcePayload(['default_product_template_id' => $template->id]))
        ->assertRedirect(route('product-import.show', $organization))
        ->assertSessionHasNoErrors();

    $source = $organization->importSource()->sole();

    expect($source->api_key)->toBe(PAGE_KEY)
        ->and($source->getRawOriginal('api_key'))->not->toContain(PAGE_KEY)
        ->and($source->default_product_template_id)->toBe($template->id);

    $this->actingAs($user)
        ->get(route('product-import.show', $organization))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('product-import/index')
            ->where('source.keyHint', '9876')
            ->where('source.apiUrl', PAGE_API)
            ->missing('source.apiKey'))
        ->assertDontSee(PAGE_KEY);
});

test('a blank key keeps the one already stored', function () {
    [$user, $organization] = newOrganizationMember();
    ImportSource::factory()->for($organization)->create(['api_key' => PAGE_KEY]);

    $this->actingAs($user)
        ->put(route('product-import.source.update', $organization), importSourcePayload(['api_key' => '', 'on_existing' => 'skip']))
        ->assertSessionHasNoErrors();

    expect($organization->importSource->fresh()->api_key)->toBe(PAGE_KEY);
});

test('a key is required the first time', function () {
    [$user, $organization] = newOrganizationMember();

    $this->actingAs($user)
        ->put(route('product-import.source.update', $organization), importSourcePayload(['api_key' => '']))
        ->assertSessionHasErrors('api_key');
});

test('a default template must be the organization own', function () {
    [$user, $organization] = newOrganizationMember();
    [, $other] = newOrganizationMember();

    $this->actingAs($user)
        ->put(route('product-import.source.update', $organization), importSourcePayload(['default_product_template_id' => familyTemplate($other)->id]))
        ->assertSessionHasErrors('default_product_template_id');
});

test('a member cannot see or change the import', function () {
    [$user, $organization] = newOrganizationMember(OrganizationRole::Member);

    $this->actingAs($user)->get(route('product-import.show', $organization))->assertForbidden();
    $this->actingAs($user)->put(route('product-import.source.update', $organization), importSourcePayload())->assertForbidden();
    $this->actingAs($user)->post(route('product-import.runs.store', $organization))->assertForbidden();
});

test('a supplier organization has no import', function () {
    [$user, $organization] = newSupplierMember();

    $this->actingAs($user)->get(route('product-import.show', $organization))->assertNotFound();
});

test('the manufacturers are listed from the source', function () {
    [$user, $organization] = newOrganizationMember();
    ImportSource::factory()->for($organization)->create(['api_url' => PAGE_API]);
    Http::fake([PAGE_API.'/search' => Http::response(kontorEnvelope([['Herstellerid' => '038', 'Hersteller' => 'Grimms']]))]);

    $this->actingAs($user)
        ->getJson(route('product-import.manufacturers.index', $organization))
        ->assertOk()
        ->assertExactJson(['manufacturers' => [['id' => '038', 'name' => 'Grimms']], 'error' => null]);
});

test('a source that refuses the manufacturer list says why', function () {
    [$user, $organization] = newOrganizationMember();
    ImportSource::factory()->for($organization)->create(['api_url' => PAGE_API]);
    Http::fake([PAGE_API.'/search' => Http::response(['success' => false, 'message' => 'Ungültiger API-Schlüssel'], 401)]);

    $this->actingAs($user)
        ->getJson(route('product-import.manufacturers.index', $organization))
        ->assertOk()
        ->assertJson(['manufacturers' => [], 'error' => 'Ungültiger API-Schlüssel']);
});

test('the chosen manufacturers replace the ones chosen before', function () {
    [$user, $organization] = newOrganizationMember();
    $source = ImportSource::factory()->for($organization)->create();
    $source->manufacturers()->create(['external_id' => '001', 'name' => 'Dropped']);
    $supplier = newSupplierConnection($organization);
    $template = familyTemplate($organization);

    $this->actingAs($user)
        ->put(route('product-import.manufacturers.update', $organization), ['manufacturers' => [
            ['external_id' => '038', 'name' => 'Grimms', 'supplier_connection_id' => $supplier->id, 'product_template_id' => $template->id],
            ['external_id' => '084', 'name' => 'Plan Toys', 'supplier_connection_id' => null, 'product_template_id' => null],
        ]])
        ->assertSessionHasNoErrors();

    expect($source->manufacturers()->pluck('external_id')->all())->toBe(['038', '084'])
        ->and($source->manufacturers()->where('external_id', '038')->sole()->supplier_connection_id)->toBe($supplier->id);
});

test('a manufacturer cannot be pointed at another organization supplier or template', function () {
    [$user, $organization] = newOrganizationMember();
    [, $other] = newOrganizationMember();
    ImportSource::factory()->for($organization)->create();

    $this->actingAs($user)
        ->put(route('product-import.manufacturers.update', $organization), ['manufacturers' => [
            ['external_id' => '038', 'name' => 'Grimms', 'supplier_connection_id' => newSupplierConnection($other)->id, 'product_template_id' => familyTemplate($other)->id],
        ]])
        ->assertSessionHasErrors(['manufacturers.0.supplier_connection_id', 'manufacturers.0.product_template_id']);
});

test('an import is put on the queue once at a time', function () {
    Queue::fake();
    [$user, $organization] = newOrganizationMember();
    ImportSource::factory()->for($organization)->create()->manufacturers()->create(['external_id' => '038', 'name' => 'Grimms']);

    $this->actingAs($user)
        ->post(route('product-import.runs.store', $organization))
        ->assertSessionHasNoErrors();

    $this->actingAs($user)
        ->post(route('product-import.runs.store', $organization))
        ->assertSessionHasErrors('import');

    Queue::assertPushed(ImportProductPage::class, 1);
    expect($organization->importRuns()->sole()->status)->toBe(ImportRunStatus::Queued);
});

test('an import that stopped making progress no longer blocks a new one', function () {
    Queue::fake();
    [$user, $organization] = newOrganizationMember();
    ImportSource::factory()->for($organization)->create()->manufacturers()->create(['external_id' => '038', 'name' => 'Grimms']);
    $stale = ImportRun::factory()->for($organization)->running()->create();
    $stale->forceFill(['updated_at' => now()->subHour()])->saveQuietly();

    $this->actingAs($user)
        ->post(route('product-import.runs.store', $organization))
        ->assertSessionHasNoErrors();

    expect($stale->fresh()->status)->toBe(ImportRunStatus::Failed);
});

test('an import needs at least one manufacturer', function () {
    [$user, $organization] = newOrganizationMember();
    ImportSource::factory()->for($organization)->create();

    $this->actingAs($user)
        ->post(route('product-import.runs.store', $organization))
        ->assertSessionHasErrors('import');
});

test('adding a supplier with the name of one the import made gives it the address', function () {
    [$user, $organization] = newOrganizationMember();
    $imported = newSupplierConnection($organization, attributes: ['company_name' => 'Grimms', 'contact_email' => null, 'invited_at' => null, 'expires_at' => null]);

    $this->actingAs($user)
        ->post(route('suppliers.store', $organization), [
            'company_name' => 'grimms',
            'contact_email' => 'sales@grimms.example',
            'send_invitation' => false,
        ])
        ->assertSessionHasNoErrors();

    expect($organization->supplierConnections()->count())->toBe(1)
        ->and($imported->fresh()->contact_email)->toBe('sales@grimms.example');
});

test('a supplier without an address cannot be sent the claim link', function () {
    [$user, $organization] = newOrganizationMember();
    $imported = newSupplierConnection($organization, attributes: ['contact_email' => null, 'invited_at' => null, 'expires_at' => null]);

    $this->actingAs($user)
        ->post(route('suppliers.resend', [$organization, $imported]))
        ->assertNotFound();
});
