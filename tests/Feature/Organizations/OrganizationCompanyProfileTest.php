<?php

use App\Enums\OrganizationRole;
use App\Models\Organization;
use Inertia\Testing\AssertableInertia as Assert;

test('owners can save their company details', function () {
    [$user, $organization] = newOrganizationMember();

    $this
        ->actingAs($user)
        ->patch(route('organizations.update', $organization), [
            'name' => $organization->name,
            'street' => 'Bahnhofstrasse 1',
            'postal_code' => '8001',
            'city' => 'Zürich',
            'country' => 'CH',
            'phone' => '+41 44 000 00 00',
            'email' => 'info@acme.test',
            'website' => 'https://acme.test',
            'vat_number' => 'CHE-123.456.789 MWST',
            'represented_by' => 'Jane Doe',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect($organization->fresh()->companyProfile())->toBe([
        'street' => 'Bahnhofstrasse 1',
        'postal_code' => '8001',
        'city' => 'Zürich',
        'country' => 'CH',
        'phone' => '+41 44 000 00 00',
        'email' => 'info@acme.test',
        'website' => 'https://acme.test',
        'vat_number' => 'CHE-123.456.789 MWST',
        'represented_by' => 'Jane Doe',
    ]);
});

test('company details can be cleared again', function () {
    [$user, $organization] = newOrganizationMember(organizationAttributes: ['city' => 'Bern', 'country' => 'CH']);

    $this
        ->actingAs($user)
        ->patch(route('organizations.update', $organization), [
            'name' => $organization->name,
            'city' => '',
            'country' => '',
        ])
        ->assertSessionHasNoErrors();

    expect($organization->fresh())
        ->city->toBeNull()
        ->country->toBeNull();
});

test('company details are validated', function () {
    [$user, $organization] = newOrganizationMember();

    $this
        ->actingAs($user)
        ->patch(route('organizations.update', $organization), [
            'name' => $organization->name,
            'country' => 'XX',
            'email' => 'not-an-email',
            'website' => 'javascript:alert(1)',
        ])
        ->assertSessionHasErrors(['country', 'email', 'website']);
});

test('members cannot change the company details', function () {
    [, $organization] = newOrganizationMember();
    [$member] = newOrganizationMember(OrganizationRole::Member);
    $organization->members()->attach($member, ['role' => OrganizationRole::Member->value]);

    $this
        ->actingAs($member)
        ->patch(route('organizations.update', $organization), [
            'name' => $organization->name,
            'city' => 'Bern',
        ])
        ->assertForbidden();

    expect($organization->fresh()->city)->toBeNull();
});

test('the edit page carries the company details and the country codes', function () {
    [$user, $organization] = newOrganizationMember(organizationAttributes: ['city' => 'Basel']);

    $this
        ->actingAs($user)
        ->get(route('organizations.edit', $organization))
        ->assertInertia(fn (Assert $page) => $page
            ->where('organization.profile.city', 'Basel')
            ->where('organization.profile.street', null)
            ->has('countries', fn (Assert $countries) => $countries->etc())
        );
});

test('a distributor reads the company details of a supplier that has joined', function () {
    [$user, $distributor] = newOrganizationMember();
    $supplier = Organization::factory()->supplier()->withCompanyProfile()->create(['city' => 'Lugano']);
    newSupplierConnection($distributor, $supplier);

    $this
        ->actingAs($user)
        ->get(route('suppliers.index', ['current_organization' => $distributor->slug]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('connections.0.supplierProfile.city', 'Lugano')
        );
});

test('a supplier that has not joined yet has no company details to show', function () {
    [$user, $distributor] = newOrganizationMember();
    newSupplierConnection($distributor);

    $this
        ->actingAs($user)
        ->get(route('suppliers.index', ['current_organization' => $distributor->slug]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('connections.0.supplierProfile', null)
        );
});
