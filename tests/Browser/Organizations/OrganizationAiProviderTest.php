<?php

use App\Enums\AiProvider;

/**
 * Sending documents to the provider is its own choice, separate from having
 * a key: an owner ticks it on purpose, and it is still ticked on the next
 * visit.
 */
test('an owner allows the AI check to read product documents', function () {
    [$user, $organization] = newOrganizationMember();

    $organization->aiSetting()->create([
        'provider' => AiProvider::Gemini,
        'model' => AiProvider::Gemini->defaultModel(),
        'api_key' => 'AIzaSyD-BrowserKeyForTests-0123456789',
    ]);

    $this->actingAs($user);

    visit(route('organizations.edit', $organization))
        ->assertSee('Allow the AI check to read product documents')
        ->assertAttribute('@ai-allow-document-analysis', 'data-state', 'unchecked')
        ->click('@ai-allow-document-analysis')
        ->click('@save-ai-provider-submit')
        ->assertSee('AI provider saved.')
        ->assertAttribute('@ai-allow-document-analysis', 'data-state', 'checked')
        ->assertNoJavaScriptErrors();

    expect($organization->fresh()->aiSetting->allow_document_analysis)->toBeTrue();
});
