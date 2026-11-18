<?php

use App\Models\Organization;

test('an owner fills in the company details and they are kept', function () {
    [$user, $organization] = newOrganizationMember();

    $this->actingAs($user);

    visit(route('organizations.edit', $organization))
        ->assertSee('Company details')
        ->type('@company-street', 'Bahnhofstrasse 1')
        ->type('@company-postal_code', '8001')
        ->type('@company-city', 'Zürich')
        ->click('@company-country')
        ->click('[role="option"]:has-text("Switzerland")')
        ->type('@company-vat_number', 'CHE-123.456.789 MWST')
        ->type('@company-website', 'https://acme.test')
        ->click('@organization-save-button')
        ->assertSee('Organization updated.')
        ->assertNoJavaScriptErrors();

    expect($organization->fresh())
        ->street->toBe('Bahnhofstrasse 1')
        ->city->toBe('Zürich')
        ->country->toBe('CH')
        ->vat_number->toBe('CHE-123.456.789 MWST')
        ->website->toBe('https://acme.test');
});

test('a distributor reads the company details a supplier keeps', function () {
    [$user, $distributor] = newOrganizationMember();
    $supplier = Organization::factory()->supplier()->create([
        'name' => 'Acme Toys',
        'city' => 'Lugano',
        'country' => 'CH',
        'represented_by' => 'Mario Rossi',
    ]);
    newSupplierConnection($distributor, $supplier);

    $this->actingAs($user);

    visit(route('suppliers.index', ['current_organization' => $distributor->slug]))
        ->click('@supplier-actions')
        ->click('@supplier-details-button')
        ->assertVisible('@supplier-details-modal')
        ->assertSee('Lugano')
        ->assertSee('Switzerland')
        ->assertSee('Mario Rossi')
        ->assertNoJavaScriptErrors();
});
