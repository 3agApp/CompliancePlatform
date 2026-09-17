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
        ->assertSee('The products Second Organization is responsible for.')
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
