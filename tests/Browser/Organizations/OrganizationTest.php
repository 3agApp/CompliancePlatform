<?php

use App\Enums\OrganizationRole;
use App\Models\Organization;

test('an organization is created through the switcher dialog', function () {
    [$user, $organization] = newOrganizationMember();

    $this->actingAs($user);

    $page = visit(route('dashboard', ['current_organization' => $organization->slug]));

    $page->click('@organization-switcher-trigger')
        ->click('@organization-switcher-new-organization')
        ->assertSee('Create a new organization')
        ->fill('@create-organization-name', 'Acme Compliance')
        ->click('@create-organization-submit')
        ->assertPathIs('/settings/organizations/acme-compliance')
        ->assertNoJavaScriptErrors();

    expect(Organization::where('name', 'Acme Compliance')->sole())
        ->slug->toBe('acme-compliance');

    expect($user->fresh()->currentOrganization->name)->toBe('Acme Compliance');
});

test('the switcher moves the user to another organization they belong to', function () {
    [$user, $organization] = newOrganizationMember(organizationAttributes: ['name' => 'First Organization', 'slug' => 'first-organization']);

    $other = Organization::factory()->create(['name' => 'Second Organization', 'slug' => 'second-organization']);
    $other->members()->attach($user, ['role' => OrganizationRole::Member->value]);

    $this->actingAs($user);

    $page = visit(route('products.index', ['current_organization' => $organization->slug]));

    $page->click('@organization-switcher-trigger')
        ->click('[role="menuitem"]:has-text("Second Organization")')
        ->assertPathIs('/second-organization/products')
        ->assertSeeIn('@organization-switcher-trigger', 'Second Organization')
        ->assertNoJavaScriptErrors();

    expect($user->fresh()->currentOrganization->name)->toBe('Second Organization');
});

test('an organization is renamed from its settings page', function () {
    [$user, $organization] = newOrganizationMember(organizationAttributes: ['name' => 'First Organization', 'slug' => 'first-organization']);

    $this->actingAs($user);

    $page = visit(route('organizations.edit', ['organization' => $organization->slug]));

    $page->fill('@organization-name-input', 'Renamed Organization')
        ->click('@organization-save-button')
        ->assertSee('Organization updated.')
        ->assertNoJavaScriptErrors();

    expect($organization->fresh()->name)->toBe('Renamed Organization');
});

test('a distributor fills in its company profile from the settings page', function () {
    [$user, $organization] = newOrganizationMember();

    $this->actingAs($user);

    $page = visit(route('organizations.edit', ['organization' => $organization->slug]));

    $page->assertSee('Complete your company profile')
        ->fill('@profile-street', 'Tramstrasse 66')
        ->fill('@profile-postal-code', '4142')
        ->fill('@profile-city', 'Münchenstein')
        ->click('@profile-country')
        ->fill('[cmdk-input]', 'switz')
        ->click('@country-option-CH')
        ->fill('@profile-map-url', 'https://maps.app.goo.gl/abc123XYZ')
        ->fill('@profile-phone', '+41 61 683 47 57')
        ->fill('@profile-website', 'example.ch')
        ->fill('@profile-vat-number', 'CHE-103.107.876')
        ->click('@profile-save-button')
        ->assertSee('Company profile updated.')
        ->assertSee('Open the saved link')
        ->assertDontSee('Complete your company profile')
        ->assertNoJavaScriptErrors();

    expect($organization->fresh())
        ->city->toBe('Münchenstein')
        ->country->value->toBe('CH')
        ->website->toBe('https://example.ch')
        ->map_url->toBe('https://maps.app.goo.gl/abc123XYZ')
        ->vat_number->toBe('CHE-103.107.876');
});

test('a supplier picks the countries it produces in', function () {
    [$user, $organization] = newSupplierMember();

    $this->actingAs($user);

    $page = visit(route('organizations.edit', ['organization' => $organization->slug]));

    $page->assertSee('Where you produce')
        ->click('@profile-production-countries')
        ->fill('[cmdk-input]', 'china')
        ->click('@country-option-CN')
        ->fill('[cmdk-input]', 'viet')
        ->click('@country-option-VN')
        ->keys('[cmdk-input]', 'Escape')
        ->assertSeeIn('@profile-production-countries-chips', 'China')
        ->assertSeeIn('@profile-production-countries-chips', 'Vietnam')
        ->click('@profile-save-button')
        ->assertSee('Company profile updated.')
        ->assertNoJavaScriptErrors();

    expect($organization->fresh()->production_countries->map->value->all())->toBe(['CN', 'VN']);
});
