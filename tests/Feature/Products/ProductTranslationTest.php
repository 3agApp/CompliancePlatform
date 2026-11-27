<?php

use App\Actions\Products\AssessProductDocuments;
use App\Ai\Agents\DocumentAssessmentAgent;
use App\Ai\Agents\ProductTextTranslationAgent;
use App\Enums\AiProvider;
use App\Enums\Locale;
use App\Enums\OrganizationRole;
use App\Enums\ProductEventType;
use App\Enums\TranslationSource;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductTranslation;
use App\Models\SupplierConnection;
use App\Models\User;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Ai\Prompts\AgentPrompt;

/**
 * A distributor with a product whose warning is written in German.
 *
 * @return array{0: User, 1: Organization, 2: Product}
 */
function productWithGermanWarning(OrganizationRole $role = OrganizationRole::Owner): array
{
    [$user, $organization] = newOrganizationMember($role);
    $connection = newSupplierConnection($organization);
    $category = legalFamily($organization);
    $template = familyTemplate($organization);

    $organization->update(['locale' => Locale::German]);

    $product = Product::factory()->for($organization)->create([
        'supplier_connection_id' => $connection->id,
        'product_category_id' => $category->id,
        'product_template_id' => $template->id,
        'warning_text' => 'Nicht für Kinder unter 3 Jahren geeignet. Kleinteile.',
        'safety_notice' => 'Verpackung aufbewahren.',
    ]);

    return [$user, $organization, $product];
}

/**
 * Save the product as the edit page does, with the given extra fields.
 *
 * @param  array<string, mixed>  $overrides
 */
function saveTranslatedProduct(Organization $organization, Product $product, array $overrides = []): TestResponse
{
    /** @var SupplierConnection $connection */
    $connection = $product->supplierConnection;

    return test()->patch(route('products.update', ['current_organization' => $organization->slug, 'product' => $product->id]), [
        'name' => $product->name,
        'product_category_id' => $product->product_category_id,
        'product_template_id' => $product->product_template_id,
        'supplier_connection_id' => $connection->id,
        'warning_text' => $product->warning_text,
        'safety_notice' => $product->safety_notice,
        ...$overrides,
    ]);
}

/**
 * Ask for translations of the given texts.
 *
 * @param  array<string, string>  $texts
 * @param  list<string>  $to
 */
function askForTranslations(Organization $organization, Product $product, array $texts, array $to = ['fr', 'it'], string $from = 'de'): TestResponse
{
    return test()->postJson(route('products.translations.suggest', [
        'current_organization' => $organization->slug,
        'product' => $product->id,
    ]), ['from' => $from, 'to' => $to, 'texts' => $texts]);
}

function connectGemini(Organization $organization): void
{
    $organization->aiSetting()->create([
        'provider' => AiProvider::Gemini,
        'model' => AiProvider::Gemini->defaultModel(),
        'api_key' => 'AIzaSyD-ExampleKeyForTests-0123456789',
    ]);
}

test('a new product is written in the language of the organization that keeps it', function () {
    [, , $product] = productWithGermanWarning();

    expect($product->fresh()->text_locale)->toBe(Locale::German);
});

test('translations are saved with the product, and the history names the language', function () {
    [$user, $organization, $product] = productWithGermanWarning();

    $this->actingAs($user);

    saveTranslatedProduct($organization, $product, [
        'text_locale' => 'de',
        'translations' => [
            'fr' => ['warning_text' => 'Ne convient pas aux enfants de moins de 3 ans.', 'source' => 'ai'],
            'it' => ['warning_text' => ' ', 'safety_notice' => ''],
        ],
    ])->assertSessionHasNoErrors()->assertRedirect();

    $translation = $product->translations()->sole();

    expect($translation->locale)->toBe(Locale::French)
        ->and($translation->warning_text)->toBe('Ne convient pas aux enfants de moins de 3 ans.')
        ->and($translation->safety_notice)->toBeNull()
        ->and($translation->source)->toBe(TranslationSource::Ai)
        ->and($translation->source_hash)->toBe($product->fresh()->translationSourceHash());

    $event = $product->events()->where('type', ProductEventType::Updated)->sole();
    app()->setLocale('en');

    expect($event->changedFields())->toBe([[
        'field' => 'warning_text.fr',
        'label' => 'Warning text (Français)',
        'from' => null,
        'to' => 'Ne convient pas aux enfants de moins de 3 ans.',
    ]]);
});

test('a language submitted empty loses its translation, and one left out keeps it', function () {
    [$user, $organization, $product] = productWithGermanWarning();
    ProductTranslation::factory()->for($product)->create(['locale' => Locale::French]);
    ProductTranslation::factory()->for($product)->create(['locale' => Locale::Italian, 'warning_text' => 'Non adatto.']);

    $this->actingAs($user);

    saveTranslatedProduct($organization, $product, [
        'translations' => ['fr' => ['warning_text' => '', 'source' => 'manual']],
    ])->assertSessionHasNoErrors();

    expect($product->translations()->pluck('locale')->map->value->all())->toBe(['it']);
});

test('a translation saved unchanged keeps saying it is out of date once the original moves on', function () {
    [$user, $organization, $product] = productWithGermanWarning();
    $translation = ProductTranslation::factory()->for($product)->create(['locale' => Locale::French]);

    $this->actingAs($user);

    saveTranslatedProduct($organization, $product, [
        'warning_text' => 'Nicht für Kinder unter 36 Monaten geeignet.',
        'translations' => ['fr' => ['warning_text' => $translation->warning_text, 'source' => 'manual']],
    ])->assertSessionHasNoErrors();

    $this->get(route('products.edit', ['current_organization' => $organization->slug, 'product' => $product->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('product.text_locale', 'de')
            ->where('product.translations.fr.warning_text', $translation->warning_text)
            ->where('product.translations.fr.outdated', true),
        );

    saveTranslatedProduct($organization, $product->fresh(), [
        'translations' => ['fr' => ['warning_text' => 'Ne convient pas aux enfants de moins de 36 mois.', 'source' => 'manual']],
    ]);

    expect($translation->fresh()->isOutdated())->toBeFalse();
});

test('the language the product is written in keeps no translation of itself', function () {
    [$user, $organization, $product] = productWithGermanWarning();
    ProductTranslation::factory()->for($product)->create(['locale' => Locale::French]);

    $this->actingAs($user);

    saveTranslatedProduct($organization, $product, [
        'text_locale' => 'fr',
        'translations' => ['fr' => ['warning_text' => 'Ignoré.']],
    ])->assertSessionHasNoErrors();

    expect($product->fresh()->text_locale)->toBe(Locale::French)
        ->and($product->translations()->count())->toBe(0);
});

test('only the languages the app speaks can hold a translation', function () {
    [$user, $organization, $product] = productWithGermanWarning();

    $this->actingAs($user);

    saveTranslatedProduct($organization, $product, [
        'translations' => ['es' => ['warning_text' => 'No apto.']],
    ])->assertSessionHasErrors('translations');

    saveTranslatedProduct($organization, $product, [
        'translations' => ['fr' => ['warning_text' => str_repeat('a', 5001)]],
    ])->assertSessionHasErrors('translations.fr.warning_text');

    expect($product->translations()->count())->toBe(0);
});

test('the safety texts on the form are translated by the organization AI provider', function () {
    ProductTextTranslationAgent::fake([['translations' => [
        ['locale' => 'fr', 'field' => 'warning_text', 'text' => 'Ne convient pas aux enfants de moins de 3 ans.'],
        ['locale' => 'it', 'field' => 'warning_text', 'text' => 'Non adatto a bambini di età inferiore a 3 anni.'],
        ['locale' => 'it', 'field' => 'usage_restrictions', 'text' => 'Not asked for.'],
        ['locale' => 'en', 'field' => 'warning_text', 'text' => 'Not asked for either.'],
    ]]]);

    [$user, $organization, $product] = productWithGermanWarning();
    connectGemini($organization);

    $this->actingAs($user);

    askForTranslations($organization, $product, ['warning_text' => 'Nicht für Kinder unter 3 Jahren geeignet.', 'safety_notice' => ''])
        ->assertOk()
        ->assertExactJson([
            'translations' => [
                'fr' => ['warning_text' => 'Ne convient pas aux enfants de moins de 3 ans.'],
                'it' => ['warning_text' => 'Non adatto a bambini di età inferiore a 3 anni.'],
            ],
            'unavailable' => null,
        ]);

    ProductTextTranslationAgent::assertPrompted(fn (AgentPrompt $prompt): bool => str_contains($prompt->prompt, "[warning_text]\nNicht für Kinder unter 3 Jahren geeignet.")
        && str_contains($prompt->prompt, 'Translate into: fr (Français), it (Italiano)')
        && ! str_contains($prompt->prompt, 'safety_notice'));

    expect($product->translations()->count())->toBe(0);
});

test('without an AI provider nothing is asked and the page is told why', function () {
    ProductTextTranslationAgent::fake()->preventStrayPrompts();

    [$user, $organization, $product] = productWithGermanWarning();

    $this->actingAs($user);

    askForTranslations($organization, $product, ['warning_text' => 'Kleinteile.'])
        ->assertOk()
        ->assertExactJson(['translations' => [], 'unavailable' => 'not_configured']);
});

test('somebody who may not edit the product cannot spend the AI credit on it', function () {
    ProductTextTranslationAgent::fake()->preventStrayPrompts();

    [, $organization, $product] = productWithGermanWarning();
    connectGemini($organization);
    [$member] = newOrganizationMember(OrganizationRole::Member);
    $member->organizations()->attach($organization, ['role' => OrganizationRole::Member]);
    $member->update(['current_organization_id' => $organization->id]);

    $this->actingAs($member);

    askForTranslations($organization, $product, ['warning_text' => 'Kleinteile.'])->assertForbidden();
});

test('a buyer reads the safety texts in their language, and the original where none was translated', function () {
    [, , $product] = productWithGermanWarning();
    ProductTranslation::factory()->for($product)->create([
        'locale' => Locale::French,
        'warning_text' => 'Ne convient pas aux enfants de moins de 3 ans. Petites pièces.',
    ]);

    $this->withUnencryptedCookie('public_lang', 'fr')
        ->get(route('products.public', $product->uuid))
        ->assertInertia(fn (Assert $page) => $page
            ->where('safety.warning_text', 'Ne convient pas aux enfants de moins de 3 ans. Petites pièces.')
            ->where('safety.safety_notice', 'Verpackung aufbewahren.'),
        );

    $this->withUnencryptedCookie('public_lang', 'it')
        ->get(route('products.public', $product->uuid))
        ->assertInertia(fn (Assert $page) => $page
            ->where('safety.warning_text', 'Nicht für Kinder unter 3 Jahren geeignet. Kleinteile.'),
        );
});

test('the AI check is given the translated safety texts too', function () {
    DocumentAssessmentAgent::fake();

    [, , $product] = productWithGermanWarning();
    ProductTranslation::factory()->for($product)->create(['locale' => Locale::Italian, 'warning_text' => 'Piccole parti.']);

    $details = AssessProductDocuments::productDetailsFor($product->fresh());

    expect($details)->toContain("The same texts in Italiano (it):\n- Warning text: Piccole parti.");
});
