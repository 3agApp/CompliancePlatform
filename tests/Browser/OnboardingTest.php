<?php

use App\Enums\OrganizationType;
use App\Models\Organization;
use App\Models\User;

test('a user without an organization creates their first one through the onboarding form', function () {
    $user = User::factory()->withoutOrganization()->create();

    $this->actingAs($user);

    $page = visit(route('onboarding'));

    $page->assertSee('Create your first organization')
        ->fill('@onboarding-organization-name', 'Acme Compliance')
        ->click('@onboarding-organization-submit')
        ->assertPathIs('/settings/organizations/acme-compliance')
        ->assertSee('Acme Compliance')
        ->assertNoJavaScriptErrors();

    expect(Organization::sole())
        ->name->toBe('Acme Compliance')
        ->slug->toBe('acme-compliance');

    expect($user->fresh()->currentOrganization->name)->toBe('Acme Compliance');
});

test('the onboarding form shows the validation message for a reserved organization name', function () {
    $user = User::factory()->withoutOrganization()->create();

    $this->actingAs($user);

    $page = visit(route('onboarding'));

    $page->fill('@onboarding-organization-name', 'Settings')
        ->click('@onboarding-organization-submit')
        ->assertPathIs('/onboarding')
        ->assertSee('This organization name is reserved and cannot be used.')
        ->assertNoJavaScriptErrors();

    $this->assertDatabaseCount('organizations', 0);
});

test('a new user chooses the supplier organization type during onboarding', function () {
    $user = User::factory()->withoutOrganization()->create();

    $this->actingAs($user);

    visit(route('onboarding'))
        ->fill('@onboarding-organization-name', 'Acme Supplies AG')
        ->click('@organization-type-supplier')
        ->click('@onboarding-organization-submit')
        ->assertNoJavaScriptErrors();

    expect(Organization::sole())
        ->name->toBe('Acme Supplies AG')
        ->type->toBe(OrganizationType::Supplier);
});
