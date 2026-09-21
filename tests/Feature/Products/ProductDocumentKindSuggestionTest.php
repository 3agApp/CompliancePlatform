<?php

use App\Ai\Agents\DocumentKindAgent;
use App\Enums\AiProvider;
use App\Enums\OrganizationRole;
use App\Enums\SupplierConnectionStatus;
use App\Models\Organization;
use App\Models\Product;
use App\Models\User;
use Illuminate\Testing\TestResponse;
use Laravel\Ai\Prompts\AgentPrompt;

const SUGGESTION_KEY = 'AIzaSyD-ExampleKeyForTests-0123456789';

/**
 * Create a distributor whose organization pays for its own AI provider.
 *
 * @return array{0: User, 1: Organization, 2: Product}
 */
function distributorWithAiProvider(OrganizationRole $role = OrganizationRole::Owner): array
{
    [$user, $organization] = newOrganizationMember($role);
    $connection = newSupplierConnection($organization);

    $product = Product::factory()->for($organization)->create([
        'name' => 'Organic Oat Milk',
        'supplier_connection_id' => $connection->id,
    ]);

    $organization->aiSetting()->create([
        'provider' => AiProvider::Gemini,
        'model' => AiProvider::Gemini->defaultModel(),
        'api_key' => SUGGESTION_KEY,
    ]);

    return [$user, $organization, $product];
}

/**
 * Ask for the kinds of a batch of files.
 *
 * @param  array<int, string>  $names
 */
function askForKinds(Organization $organization, Product $product, array $names): TestResponse
{
    return test()->postJson(route('products.documents.suggest', [
        'current_organization' => $organization->slug,
        'product' => $product->id,
    ]), [
        'files' => array_map(fn (string $name): array => [
            'name' => $name,
            'mime_type' => 'application/pdf',
            'size' => 91204,
        ], $names),
    ]);
}

/**
 * A well-formed answer covering the given indexes.
 *
 * @param  array<int, string>  $types
 * @return array<string, mixed>
 */
function guessesFor(array $types, string $confidence = 'high'): array
{
    return ['guesses' => collect($types)
        ->map(fn (string $type, int $index): array => [
            'index' => $index,
            'type' => $type,
            'confidence' => $confidence,
        ])
        ->all()];
}

test('the kinds of a batch of files are guessed from their names', function () {
    DocumentKindAgent::fake([
        guessesFor(['test_report', 'declaration_of_conformity']),
    ]);

    [$user, $organization, $product] = distributorWithAiProvider();

    $this->actingAs($user);

    askForKinds($organization, $product, ['EN71-1_test_report.pdf', 'DoC-2026.pdf'])
        ->assertOk()
        ->assertExactJson([
            'guesses' => [
                ['type' => 'test_report', 'confidence' => 'high'],
                ['type' => 'declaration_of_conformity', 'confidence' => 'high'],
            ],
            'unavailable' => null,
        ]);
});

test('every file name is put to the provider, and nothing else is', function () {
    DocumentKindAgent::fake([guessesFor(['test_report', 'product_image'])]);

    [$user, $organization, $product] = distributorWithAiProvider();

    $this->actingAs($user);

    askForKinds($organization, $product, ['EN71-1_test_report.pdf', 'packaging.jpg'])->assertOk();

    DocumentKindAgent::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->contains('EN71-1_test_report.pdf')
        && $prompt->contains('packaging.jpg'));
});

test('an answer that is short still lines up with the files it was about', function () {
    DocumentKindAgent::fake([guessesFor(['test_report'])]);

    [$user, $organization, $product] = distributorWithAiProvider();

    $this->actingAs($user);

    askForKinds($organization, $product, ['report.pdf', 'scan_0012.pdf', 'other.pdf'])
        ->assertOk()
        ->assertJsonPath('guesses', [
            ['type' => 'test_report', 'confidence' => 'high'],
            ['type' => null, 'confidence' => 'low'],
            ['type' => null, 'confidence' => 'low'],
        ]);
});

test('an answer given out of order is put back in order', function () {
    DocumentKindAgent::fake([['guesses' => [
        ['index' => 1, 'type' => 'certificate', 'confidence' => 'high'],
        ['index' => 0, 'type' => 'test_report', 'confidence' => 'low'],
    ]]]);

    [$user, $organization, $product] = distributorWithAiProvider();

    $this->actingAs($user);

    askForKinds($organization, $product, ['report.pdf', 'gs-certificate.pdf'])
        ->assertOk()
        ->assertJsonPath('guesses', [
            ['type' => 'test_report', 'confidence' => 'low'],
            ['type' => 'certificate', 'confidence' => 'high'],
        ]);
});

test('a kind the model invents is handed back as no guess at all', function () {
    DocumentKindAgent::fake([guessesFor(['invoice', 'unknown'])]);

    [$user, $organization, $product] = distributorWithAiProvider();

    $this->actingAs($user);

    askForKinds($organization, $product, ['invoice-88.pdf', 'scan_0012.pdf'])
        ->assertOk()
        ->assertJsonPath('guesses', [
            ['type' => null, 'confidence' => 'low'],
            ['type' => null, 'confidence' => 'low'],
        ]);
});

test('a guess for a file that was never asked about is dropped', function () {
    DocumentKindAgent::fake([['guesses' => [
        ['index' => 0, 'type' => 'test_report', 'confidence' => 'high'],
        ['index' => 7, 'type' => 'certificate', 'confidence' => 'high'],
    ]]]);

    [$user, $organization, $product] = distributorWithAiProvider();

    $this->actingAs($user);

    askForKinds($organization, $product, ['report.pdf'])
        ->assertOk()
        ->assertJsonPath('guesses', [['type' => 'test_report', 'confidence' => 'high']]);
});

/*
 * The whole contract of this endpoint: whatever went wrong, the page is
 * handed one row per file and can still be filled in by hand.
 */
test('an organization with no provider is told so and still gets a row per file', function () {
    DocumentKindAgent::fake()->preventStrayPrompts();

    [$user, $organization] = newOrganizationMember();
    $connection = newSupplierConnection($organization);
    $product = Product::factory()->for($organization)->create(['supplier_connection_id' => $connection->id]);

    $this->actingAs($user);

    askForKinds($organization, $product, ['report.pdf', 'doc.pdf'])
        ->assertOk()
        ->assertJsonPath('unavailable', 'not_configured')
        ->assertJsonPath('guesses', [
            ['type' => null, 'confidence' => 'low'],
            ['type' => null, 'confidence' => 'low'],
        ]);

    DocumentKindAgent::assertNeverPrompted();
});

test('a provider that will not answer still leaves the kinds to be picked by hand', function () {
    DocumentKindAgent::fake(function (): never {
        throw new RuntimeException('The provider rejected the API key.');
    });

    [$user, $organization, $product] = distributorWithAiProvider();

    $this->actingAs($user);

    askForKinds($organization, $product, ['report.pdf'])
        ->assertOk()
        ->assertJsonPath('unavailable', 'unavailable')
        ->assertJsonPath('guesses', [['type' => null, 'confidence' => 'low']]);
});

/*
 * The most important test here. The key is written into config so the SDK
 * can see it, and config outlives the request in a queue worker. If the name
 * it is written under were wrong, or the finally were dropped, one
 * organization's key would be sitting there for whoever prompts next.
 */
test('the organization key does not outlive the prompt it was set for', function () {
    DocumentKindAgent::fake([guessesFor(['test_report'])]);

    [$user, $organization, $product] = distributorWithAiProvider();

    $this->actingAs($user);

    askForKinds($organization, $product, ['report.pdf'])->assertOk();

    expect(config('ai.providers.organization-'.$organization->id))->toBeNull()
        ->and(json_encode(config('ai.providers')))->not->toContain(SUGGESTION_KEY);
});

test('the organization key does not outlive a prompt that threw', function () {
    DocumentKindAgent::fake(function (): never {
        throw new RuntimeException('The provider is down.');
    });

    [$user, $organization, $product] = distributorWithAiProvider();

    $this->actingAs($user);

    askForKinds($organization, $product, ['report.pdf'])->assertOk();

    expect(json_encode(config('ai.providers')))->not->toContain(SUGGESTION_KEY);
});

test('the shared provider configuration is never written over', function () {
    DocumentKindAgent::fake([guessesFor(['test_report'])]);

    [$user, $organization, $product] = distributorWithAiProvider();

    $before = config('ai.providers.gemini');

    $this->actingAs($user);

    askForKinds($organization, $product, ['report.pdf'])->assertOk();

    expect(config('ai.providers.gemini'))->toBe($before);
});

test('a member may not ask for guesses', function () {
    DocumentKindAgent::fake()->preventStrayPrompts();

    [$owner, $organization, $product] = distributorWithAiProvider();
    [$member] = newOrganizationMember();

    $organization->members()->attach($member, ['role' => OrganizationRole::Member->value]);

    $this->actingAs($member);

    askForKinds($organization, $product, ['report.pdf'])->assertForbidden();

    DocumentKindAgent::assertNeverPrompted();
});

test('a supplier responsible for the product may ask for guesses', function () {
    DocumentKindAgent::fake([guessesFor(['test_report'])]);

    [, $distributor] = newOrganizationMember();
    [$supplierUser, $supplier] = newSupplierMember();
    $connection = newSupplierConnection($distributor, $supplier);

    $product = Product::factory()->for($distributor)->create(['supplier_connection_id' => $connection->id]);

    $supplier->aiSetting()->create([
        'provider' => AiProvider::OpenAi,
        'model' => AiProvider::OpenAi->defaultModel(),
        'api_key' => SUGGESTION_KEY,
    ]);

    $this->actingAs($supplierUser);

    askForKinds($supplier, $product, ['report.pdf'])
        ->assertOk()
        ->assertJsonPath('guesses.0.type', 'test_report');
});

test('a supplier whose connection is revoked may not ask about the product', function () {
    DocumentKindAgent::fake()->preventStrayPrompts();

    [, $distributor] = newOrganizationMember();
    [$supplierUser, $supplier] = newSupplierMember();
    $connection = newSupplierConnection($distributor, $supplier, [
        'status' => SupplierConnectionStatus::Revoked,
    ]);

    $product = Product::factory()->for($distributor)->create(['supplier_connection_id' => $connection->id]);

    $this->actingAs($supplierUser);

    askForKinds($supplier, $product, ['report.pdf'])->assertNotFound();
});

test('another organization product cannot be asked about', function () {
    DocumentKindAgent::fake()->preventStrayPrompts();

    [$user, $organization] = distributorWithAiProvider();
    [, $other] = newOrganizationMember();
    $otherConnection = newSupplierConnection($other);
    $otherProduct = Product::factory()->for($other)->create(['supplier_connection_id' => $otherConnection->id]);

    $this->actingAs($user);

    askForKinds($organization, $otherProduct, ['report.pdf'])->assertNotFound();
});

test('more files than the batch allows are refused', function () {
    DocumentKindAgent::fake()->preventStrayPrompts();

    [$user, $organization, $product] = distributorWithAiProvider();

    $this->actingAs($user);

    askForKinds($organization, $product, array_map(
        fn (int $number): string => "report-{$number}.pdf",
        range(1, 21),
    ))->assertJsonValidationErrorFor('files');

    DocumentKindAgent::assertNeverPrompted();
});

test('a file list with nothing in it is refused', function () {
    [$user, $organization, $product] = distributorWithAiProvider();

    $this->actingAs($user);

    $this->postJson(route('products.documents.suggest', [
        'current_organization' => $organization->slug,
        'product' => $product->id,
    ]), ['files' => []])->assertJsonValidationErrorFor('files');
});

test('a guest may not ask for guesses', function () {
    [, $organization, $product] = distributorWithAiProvider();

    askForKinds($organization, $product, ['report.pdf'])->assertUnauthorized();
});
