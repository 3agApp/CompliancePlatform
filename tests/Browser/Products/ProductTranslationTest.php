<?php

use App\Ai\Agents\ProductTextTranslationAgent;
use App\Enums\AiProvider;
use App\Enums\Locale;
use App\Enums\TranslationSource;
use App\Models\Product;
use App\Models\ProductTranslation;

/**
 * The AI fills the French tab, the person corrects a word, and what is
 * saved is what they read -- marked as theirs, since they changed it.
 */
test('the safety texts are translated with AI, corrected by hand and saved with the product', function () {
    ProductTextTranslationAgent::fake([['translations' => [
        ['locale' => 'fr', 'field' => 'warning_text', 'text' => 'Ne convient pas aux enfants de moins de 3 ans.'],
    ]]]);

    [$user, $organization] = newOrganizationMember();
    $product = Product::factory()->for($organization)->create([
        'supplier_connection_id' => newSupplierConnection($organization)->id,
        'warning_text' => 'Not suitable for children under 3 years.',
    ]);
    $organization->aiSetting()->create([
        'provider' => AiProvider::Gemini,
        'model' => AiProvider::Gemini->defaultModel(),
        'api_key' => 'AIzaSyD-ExampleKeyForTests-0123456789',
    ]);

    $this->actingAs($user);

    visit(route('products.edit', ['current_organization' => $organization->slug, 'product' => $product->id]))
        ->assertSeeIn('@safety-language-original', 'English')
        ->click('@safety-language-fr')
        ->assertSee('Original: Not suitable for children under 3 years.')
        ->click('@safety-translate-fr')
        ->assertValue('@product-warning-text-fr', 'Ne convient pas aux enfants de moins de 3 ans.')
        ->assertSee('Translated by AI — check it')
        ->fill('@product-warning-text-fr', 'Ne convient pas aux enfants de moins de 36 mois.')
        ->click('@update-product-submit')
        ->assertSee('Product updated.')
        ->assertNoJavaScriptErrors();

    $translation = $product->translations()->sole();

    expect($translation->locale)->toBe(Locale::French)
        ->and($translation->warning_text)->toBe('Ne convient pas aux enfants de moins de 36 mois.')
        ->and($translation->source)->toBe(TranslationSource::Manual);
});

test('a buyer who switches the public page to Italian reads the Italian warning', function () {
    [, $organization] = newOrganizationMember();
    $product = Product::factory()->for($organization)->create([
        'supplier_connection_id' => newSupplierConnection($organization)->id,
        'warning_text' => 'Not suitable for children under 3 years.',
    ]);
    ProductTranslation::factory()->for($product)->create([
        'locale' => Locale::Italian,
        'warning_text' => 'Non adatto a bambini di età inferiore a 3 anni.',
    ]);

    visit(route('products.public', $product->uuid))
        ->assertSeeIn('@product-safety', 'Not suitable for children under 3 years.')
        ->select('@public-locale-toggle', 'it')
        ->assertSeeIn('@product-safety', 'Non adatto a bambini di età inferiore a 3 anni.')
        ->assertNoJavaScriptErrors();
});
