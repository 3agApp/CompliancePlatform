<?php

use App\Enums\OrganizationRole;
use App\Enums\SupplierConnectionStatus;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\Product;
use App\Models\User;

test('guests are redirected to the login page', function () {
    $user = User::factory()->create();

    $this->get(route('dashboard', ['current_organization' => $user->currentOrganization->slug]))
        ->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();

    $this
        ->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk();
});

test('dashboard does not include a pending invitations list', function () {
    $owner = User::factory()->create();
    $invitedUser = User::factory()->create(['email' => 'invited@example.com']);
    $organization = Organization::factory()->create();

    $organization->members()->attach($owner, ['role' => OrganizationRole::Owner->value]);

    OrganizationInvitation::factory()->create([
        'organization_id' => $organization->id,
        'email' => 'invited@example.com',
        'invited_by' => $owner->id,
    ]);

    $this
        ->actingAs($invitedUser)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->component('dashboard')
            ->missing('pendingInvitations')
            ->where('pendingInvitationsCount', 1),
        );
});

test('the dashboard counts a distributor products suppliers and pending invitations', function () {
    [$user, $distributor] = newOrganizationMember();
    [, $supplier] = newSupplierMember();

    $active = newSupplierConnection($distributor, $supplier);
    newSupplierConnection($distributor, attributes: ['contact_email' => 'pending@supplier.test']);

    Product::factory()->count(2)->for($distributor)->create(['supplier_connection_id' => $active->id]);

    $this
        ->actingAs($user)
        ->get(route('dashboard', ['current_organization' => $distributor->slug]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('dashboard')
            ->where('viewerType', 'distributor')
            ->where('stats.products', 2)
            ->where('stats.activeSuppliers', 1)
            ->where('stats.pendingInvitations', 1),
        );
});

test('the dashboard counts a supplier assigned products and distributors', function () {
    [$supplierUser, $supplier] = newSupplierMember();
    [, $distributorA] = newOrganizationMember();
    [, $distributorB] = newOrganizationMember();

    $active = newSupplierConnection($distributorA, $supplier);
    $revoked = newSupplierConnection($distributorB, $supplier);

    Product::factory()->for($distributorA)->create(['supplier_connection_id' => $active->id]);
    Product::factory()->count(3)->for($distributorB)->create(['supplier_connection_id' => $revoked->id]);

    $revoked->update(['status' => SupplierConnectionStatus::Revoked]);

    $this
        ->actingAs($supplierUser)
        ->get(route('dashboard', ['current_organization' => $supplier->slug]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('viewerType', 'supplier')
            ->where('stats.products', 1)
            ->where('stats.distributors', 1),
        );
});

test('the dashboard counts ignore another organization data', function () {
    [$user, $distributor] = newOrganizationMember();
    [, $otherDistributor] = newOrganizationMember();

    $otherConnection = newSupplierConnection($otherDistributor);
    Product::factory()->count(4)->for($otherDistributor)->create(['supplier_connection_id' => $otherConnection->id]);

    $this
        ->actingAs($user)
        ->get(route('dashboard', ['current_organization' => $distributor->slug]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('stats.products', 0)
            ->where('stats.activeSuppliers', 0)
            ->where('stats.pendingInvitations', 0),
        );
});
