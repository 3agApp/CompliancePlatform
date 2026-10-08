<?php

use App\Enums\Locale;

/**
 * Switching language reloads the page, so every assertion after the click
 * is about the page as it comes back: in the other language, all of it,
 * including the sidebar that stays mounted between visits.
 */
test('a member switches the app to German from the user menu, and back to the organization', function () {
    [$user, $organization] = newOrganizationMember();

    $this->actingAs($user);

    visit(route('dashboard', ['current_organization' => $organization->slug]))
        ->assertSee('Dashboard')
        ->click('@sidebar-menu-button')
        ->click('@language-menu')
        ->click('@language-de')
        ->assertSee('Übersicht')
        ->assertSeeIn('@nav-products', 'Produkte')
        ->assertNoJavaScriptErrors();

    expect($user->fresh()->locale)->toBe(Locale::German);
});

test('a member who follows the organization reads the app in its default language', function () {
    [$user, $organization] = newOrganizationMember(organizationAttributes: ['locale' => Locale::German]);

    $this->actingAs($user);

    visit(route('dashboard', ['current_organization' => $organization->slug]))
        ->assertSee('Übersicht')
        ->assertSee('Sie arbeiten in '.$organization->name.'.')
        ->assertNoJavaScriptErrors();
});

test('a guest reads the login page in German after one click', function () {
    visit(route('login'))
        ->assertSee('Log in to your account')
        ->click('@language-toggle')
        ->assertSee('Bei Ihrem Konto anmelden')
        ->assertSeeIn('@language-toggle', 'English')
        ->assertNoJavaScriptErrors();
});

test('an owner sets the organization language from its settings', function () {
    [$user, $organization] = newOrganizationMember();

    $this->actingAs($user);

    /**
     * The test server keeps one signed-in user object across requests, so
     * the page cannot come back German here the way it does for real: the
     * cached organization still speaks English. What this covers is that
     * choosing a language makes the form saveable and saves it.
     */
    visit(route('organizations.edit', $organization))
        ->click('@organization-locale')
        ->click('[role="option"]:has-text("Deutsch")')
        ->click('@organization-save-button')
        ->assertSee('Organization updated.')
        ->assertNoJavaScriptErrors();

    expect($organization->fresh()->locale)->toBe(Locale::German);
});

/**
 * A reader whose browser translates the page has React's text taken out
 * from under it. Changing language there -- the very thing such a reader is
 * looking for -- used to take the whole page down with "Failed to execute
 * 'removeChild' on 'Node'", in production, while every test stayed green.
 */
test('an owner changes the organization language on a page the browser has translated', function () {
    [$user, $organization] = newOrganizationMember();

    $this->actingAs($user);

    $page = visit(route('organizations.edit', $organization))->assertSee('Default language');

    translateLikeTheBrowser($page)
        ->click('@organization-locale')
        ->click('[role="option"]:has-text("Deutsch")')
        ->click('@organization-save-button')
        ->assertSee('Organization updated.')
        ->assertMissing('@error-boundary')
        ->assertNoJavaScriptErrors();

    expect($organization->fresh()->locale)->toBe(Locale::German);
});
