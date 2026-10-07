<?php

use App\Enums\OrganizationRole;
use App\Enums\OrganizationType;
use App\Enums\SupplierConnectionStatus;
use App\Models\Organization;
use App\Models\SupplierConnection;
use App\Models\User;

test('a recipient claims a connection by creating a new supplier organization', function () {
    [, $distributor] = newOrganizationMember();
    $recipient = User::factory()->withoutOrganization()->create();

    $connection = newSupplierConnection($distributor, attributes: [
        'company_name' => 'Acme Supplies',
        'contact_email' => $recipient->email,
    ]);

    $this
        ->actingAs($recipient)
        ->post(route('connections.store', ['connection' => $connection->code]), [
            'mode' => 'create',
            'name' => 'Acme Supplies',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $supplier = Organization::where('name', 'Acme Supplies')->sole();

    expect($supplier->type)->toBe(OrganizationType::Supplier);

    expect($connection->fresh())
        ->status->toBe(SupplierConnectionStatus::Active)
        ->supplier_organization_id->toBe($supplier->id)
        ->accepted_at->not->toBeNull();

    expect($recipient->fresh())
        ->current_organization_id->toBe($supplier->id);

    expect($recipient->organizationRole($supplier))->toBe(OrganizationRole::Owner);
});

test('a recipient claims a connection with a supplier organization they already run', function () {
    [$supplierUser, $supplier] = newSupplierMember();
    [, $distributor] = newOrganizationMember();

    $connection = newSupplierConnection($distributor, attributes: [
        'contact_email' => $supplierUser->email,
    ]);

    $this
        ->actingAs($supplierUser)
        ->post(route('connections.store', ['connection' => $connection->code]), [
            'mode' => 'existing',
            'organization' => $supplier->slug,
        ])
        ->assertSessionHasNoErrors();

    expect(Organization::query()->count())->toBe(2);

    expect($connection->fresh())
        ->status->toBe(SupplierConnectionStatus::Active)
        ->supplier_organization_id->toBe($supplier->id);
});

test('a supplier organization cannot be connected twice to the same distributor', function () {
    [$supplierUser, $supplier] = newSupplierMember();
    [, $distributor] = newOrganizationMember();

    $first = newSupplierConnection($distributor, attributes: ['contact_email' => $supplierUser->email]);
    $second = newSupplierConnection($distributor, attributes: ['contact_email' => $supplierUser->email]);

    $this
        ->actingAs($supplierUser)
        ->post(route('connections.store', ['connection' => $first->code]), [
            'mode' => 'existing',
            'organization' => $supplier->slug,
        ])
        ->assertSessionHasNoErrors();

    $this
        ->actingAs($supplierUser)
        ->post(route('connections.store', ['connection' => $second->code]), [
            'mode' => 'existing',
            'organization' => $supplier->slug,
        ])
        ->assertSessionHasErrors('organization');

    expect($second->fresh())
        ->status->toBe(SupplierConnectionStatus::Pending)
        ->supplier_organization_id->toBeNull();

    expect(SupplierConnection::query()->where('supplier_organization_id', $supplier->id)->count())->toBe(1);
});

test('a recipient cannot claim with an organization they do not belong to', function () {
    [, $strangersSupplier] = newSupplierMember();
    [, $distributor] = newOrganizationMember();

    $recipient = User::factory()->withoutOrganization()->create();
    $connection = newSupplierConnection($distributor, attributes: ['contact_email' => $recipient->email]);

    $this
        ->actingAs($recipient)
        ->post(route('connections.store', ['connection' => $connection->code]), [
            'mode' => 'existing',
            'organization' => $strangersSupplier->slug,
        ])
        ->assertSessionHasErrors('organization');

    expect($connection->fresh()->supplier_organization_id)->toBeNull();
});

test('a recipient cannot claim with a distributor organization', function () {
    [$user, $ownDistributor] = newOrganizationMember();
    [, $distributor] = newOrganizationMember();

    $connection = newSupplierConnection($distributor, attributes: ['contact_email' => $user->email]);

    $this
        ->actingAs($user)
        ->post(route('connections.store', ['connection' => $connection->code]), [
            'mode' => 'existing',
            'organization' => $ownDistributor->slug,
        ])
        ->assertSessionHasErrors('organization');

    expect($connection->fresh()->supplier_organization_id)->toBeNull();
});

test('a supplier member cannot bind their organization to a distributor', function () {
    [$supplierUser, $supplier] = newSupplierMember(OrganizationRole::Member);
    [, $distributor] = newOrganizationMember();

    $connection = newSupplierConnection($distributor, attributes: ['contact_email' => $supplierUser->email]);

    $this
        ->actingAs($supplierUser)
        ->post(route('connections.store', ['connection' => $connection->code]), [
            'mode' => 'existing',
            'organization' => $supplier->slug,
        ])
        ->assertSessionHasErrors('organization');
});

test('a connection sent to another email address cannot be claimed', function () {
    [, $distributor] = newOrganizationMember();
    $stranger = User::factory()->withoutOrganization()->create();

    $connection = newSupplierConnection($distributor, attributes: ['contact_email' => 'someone@else.test']);

    $this
        ->actingAs($stranger)
        ->post(route('connections.store', ['connection' => $connection->code]), [
            'mode' => 'create',
            'name' => 'Acme Supplies',
        ])
        ->assertSessionHasErrors('connection');

    expect($connection->fresh()->status)->toBe(SupplierConnectionStatus::Pending);
    $this->assertDatabaseMissing('organizations', ['name' => 'Acme Supplies']);
});

test('an expired connection invitation cannot be claimed', function () {
    [, $distributor] = newOrganizationMember();
    $recipient = User::factory()->withoutOrganization()->create();

    $connection = newSupplierConnection($distributor, attributes: [
        'contact_email' => $recipient->email,
        'expires_at' => now()->subDay(),
    ]);

    $this
        ->actingAs($recipient)
        ->post(route('connections.store', ['connection' => $connection->code]), [
            'mode' => 'create',
            'name' => 'Acme Supplies',
        ])
        ->assertSessionHasErrors('connection');

    expect($connection->fresh()->supplier_organization_id)->toBeNull();
});

test('a revoked connection cannot be claimed', function () {
    [, $distributor] = newOrganizationMember();
    $recipient = User::factory()->withoutOrganization()->create();

    $connection = newSupplierConnection($distributor, attributes: [
        'contact_email' => $recipient->email,
        'status' => SupplierConnectionStatus::Revoked,
    ]);

    $this
        ->actingAs($recipient)
        ->post(route('connections.store', ['connection' => $connection->code]), [
            'mode' => 'create',
            'name' => 'Acme Supplies',
        ])
        ->assertSessionHasErrors('connection');
});

test('declining a connection leaves the supplier organization unset', function () {
    [, $distributor] = newOrganizationMember();
    $recipient = User::factory()->withoutOrganization()->create();

    $connection = newSupplierConnection($distributor, attributes: ['contact_email' => $recipient->email]);

    $this
        ->actingAs($recipient)
        ->delete(route('connections.destroy', ['connection' => $connection->code]))
        ->assertRedirect(route('onboarding'));

    expect($connection->fresh())
        ->status->toBe(SupplierConnectionStatus::Declined)
        ->supplier_organization_id->toBeNull();
});

test('a pending supplier connection is counted with the other invitations', function () {
    [, $distributor] = newOrganizationMember();
    $recipient = User::factory()->withoutOrganization()->create();

    newSupplierConnection($distributor, attributes: ['contact_email' => $recipient->email]);

    $this
        ->actingAs($recipient)
        ->get(route('onboarding'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('pendingInvitationsCount', 1));
});

/**
 * Nothing was mailed, so whoever signs in with the address must not find
 * an invitation waiting that the distributor never sent.
 */
test('a supplier that has not been invited yet has no invitation to see or claim', function () {
    [, $distributor] = newOrganizationMember();
    $recipient = User::factory()->withoutOrganization()->create();

    $connection = newSupplierConnection($distributor, attributes: [
        'contact_email' => $recipient->email,
        'invited_at' => null,
        'expires_at' => null,
    ]);

    $this
        ->actingAs($recipient)
        ->get(route('onboarding'))
        ->assertInertia(fn ($page) => $page->where('pendingInvitationsCount', 0));

    $this
        ->actingAs($recipient)
        ->get(route('connections.show', ['connection' => $connection->code]))
        ->assertRedirect(route('invitations.index'));

    $this
        ->actingAs($recipient)
        ->post(route('connections.store', ['connection' => $connection->code]), [
            'mode' => 'create',
            'name' => 'Acme Supplies',
        ])
        ->assertSessionHasErrors('connection');

    expect($connection->fresh()->supplier_organization_id)->toBeNull();
});
