<?php

use App\Actions\Organizations\DeleteOrganization;
use App\Enums\AiProvider;
use App\Enums\OrganizationRole;
use App\Models\Organization;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * A key long enough and plain enough to pass the rules the form applies.
 */
const A_KEY = 'AIzaSyD-ExampleKeyForTests-0123456789';

/**
 * Connect a provider through the settings page.
 *
 * @param  array<string, mixed>  $overrides
 */
function connectAiProvider(Organization $organization, array $overrides = []): TestResponse
{
    return test()->patch(route('organizations.ai-provider.update', $organization), [
        'provider' => AiProvider::Gemini->value,
        'model' => AiProvider::Gemini->defaultModel(),
        'api_key' => A_KEY,
        ...$overrides,
    ]);
}

test('an owner connects a provider, a key and a model', function () {
    [$user, $organization] = newOrganizationMember();

    $this->actingAs($user);

    connectAiProvider($organization)
        ->assertRedirect(route('organizations.edit', $organization));

    $setting = $organization->fresh()->aiSetting;

    expect($setting)->not->toBeNull()
        ->and($setting->provider)->toBe(AiProvider::Gemini)
        ->and($setting->model)->toBe(AiProvider::Gemini->defaultModel())
        ->and($setting->api_key)->toBe(A_KEY);
});

test('sending documents to the provider stays off until an owner turns it on', function () {
    [$user, $organization] = newOrganizationMember();

    $this->actingAs($user);

    connectAiProvider($organization)->assertRedirect();

    expect($organization->fresh()->aiSetting->allow_document_analysis)->toBeFalse();

    connectAiProvider($organization, ['api_key' => '', 'allow_document_analysis' => '1'])->assertRedirect();

    expect($organization->fresh()->aiSetting->allow_document_analysis)->toBeTrue();

    $this->get(route('organizations.edit', $organization))
        ->assertInertia(fn (Assert $page) => $page->where('aiProvider.allow_document_analysis', true));
});

test('an admin may connect a provider too', function () {
    [$user, $organization] = newOrganizationMember(OrganizationRole::Admin);

    $this->actingAs($user);

    connectAiProvider($organization)->assertRedirect();

    expect($organization->fresh()->aiSetting)->not->toBeNull();
});

test('a member may not connect a provider', function () {
    [$user, $organization] = newOrganizationMember(OrganizationRole::Member);

    $this->actingAs($user);

    connectAiProvider($organization)->assertForbidden();

    expect($organization->fresh()->aiSetting)->toBeNull();
});

test('someone outside the organization may not connect a provider', function () {
    [$user] = newOrganizationMember();
    [, $other] = newOrganizationMember();

    $this->actingAs($user);

    connectAiProvider($other)->assertForbidden();
});

test('the key is written to the database encrypted', function () {
    [$user, $organization] = newOrganizationMember();

    $this->actingAs($user);

    connectAiProvider($organization);

    $stored = DB::table('organization_ai_settings')
        ->where('organization_id', $organization->id)
        ->value('api_key');

    expect($stored)->not->toBe(A_KEY)
        ->and($stored)->not->toContain(A_KEY)
        ->and($organization->fresh()->aiSetting->api_key)->toBe(A_KEY);
});

test('the key never reaches the page that manages it', function () {
    [$user, $organization] = newOrganizationMember();

    $this->actingAs($user);

    connectAiProvider($organization);

    $response = $this->get(route('organizations.edit', $organization));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('organizations/edit')
            ->where('aiProvider.provider', AiProvider::Gemini->value)
            ->where('aiProvider.key_hint', substr(A_KEY, -4))
            ->missing('aiProvider.api_key')
            ->etc());

    expect(json_encode($response->viewData('page')))->not->toContain(A_KEY);
});

test('a member is not told which provider the organization pays for', function () {
    [$owner, $organization] = newOrganizationMember();
    [$member] = newOrganizationMember();

    $this->actingAs($owner);
    connectAiProvider($organization);

    $organization->members()->attach($member, ['role' => OrganizationRole::Member->value]);

    $this->actingAs($member)
        ->get(route('organizations.edit', $organization))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('aiProvider', null)->etc());
});

test('a model the chosen provider does not offer is refused', function () {
    [$user, $organization] = newOrganizationMember();

    $this->actingAs($user);

    connectAiProvider($organization, ['model' => AiProvider::OpenAi->defaultModel()])
        ->assertInvalid(['model' => 'Choose one of the models this provider offers.']);

    expect($organization->fresh()->aiSetting)->toBeNull();
});

test('an unknown provider is refused', function () {
    [$user, $organization] = newOrganizationMember();

    $this->actingAs($user);

    connectAiProvider($organization, ['provider' => 'acme-intelligence'])
        ->assertInvalid(['provider' => 'Choose one of the available AI providers.']);
});

test('a key is required the first time and kept every time after', function () {
    [$user, $organization] = newOrganizationMember();

    $this->actingAs($user);

    connectAiProvider($organization, ['api_key' => ''])
        ->assertInvalid(['api_key' => 'Paste the API key for the provider you chose.']);

    connectAiProvider($organization);

    connectAiProvider($organization, [
        'api_key' => '',
        'model' => 'gemini-3.6-flash',
    ])->assertRedirect();

    $setting = $organization->fresh()->aiSetting;

    expect($setting->model)->toBe('gemini-3.6-flash')
        ->and($setting->api_key)->toBe(A_KEY);
});

test('a key pasted with whitespace in it is refused', function () {
    [$user, $organization] = newOrganizationMember();

    $this->actingAs($user);

    connectAiProvider($organization, ['api_key' => 'AIzaSyD-Example Key-0123456789012'])
        ->assertInvalid(['api_key']);
});

test('disconnecting the provider forgets the key', function () {
    [$user, $organization] = newOrganizationMember();

    $this->actingAs($user);

    connectAiProvider($organization);

    $this->delete(route('organizations.ai-provider.destroy', $organization))
        ->assertRedirect(route('organizations.edit', $organization));

    expect($organization->fresh()->aiSetting)->toBeNull();
    $this->assertDatabaseCount('organization_ai_settings', 0);
});

test('a member may not disconnect the provider', function () {
    [$owner, $organization] = newOrganizationMember();
    [$member] = newOrganizationMember();

    $this->actingAs($owner);
    connectAiProvider($organization);

    $organization->members()->attach($member, ['role' => OrganizationRole::Member->value]);

    $this->actingAs($member)
        ->delete(route('organizations.ai-provider.destroy', $organization))
        ->assertForbidden();

    expect($organization->fresh()->aiSetting)->not->toBeNull();
});

/*
 * Organizations are soft deleted, so the foreign key cascade never fires.
 * Without this the key of a shut-down organization would sit in the database
 * for good, billable to an account nobody is watching any more.
 */
test('deleting an organization takes its API key with it', function () {
    [$user, $organization] = newOrganizationMember();

    $this->actingAs($user);
    connectAiProvider($organization);

    app(DeleteOrganization::class)->handle($organization);

    $this->assertDatabaseCount('organization_ai_settings', 0);
});

test('a guest is sent to log in rather than shown the provider form', function () {
    [, $organization] = newOrganizationMember();

    connectAiProvider($organization)->assertRedirect(route('login'));
});
