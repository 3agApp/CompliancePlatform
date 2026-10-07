<?php

use App\Enums\OrganizationRole;
use App\Enums\ProductReviewStatus;
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

test('the dashboard splits a distributor catalog across the review stages, empty ones included', function () {
    [$user, $distributor] = newOrganizationMember();
    $connection = newSupplierConnection($distributor);

    Product::factory()->count(3)->for($distributor)->create(['supplier_connection_id' => $connection->id]);
    Product::factory()->for($distributor)->reviewed(ProductReviewStatus::Approved)->create(['supplier_connection_id' => $connection->id]);

    $this
        ->actingAs($user)
        ->get(route('dashboard', ['current_organization' => $distributor->slug]))
        ->assertInertia(fn ($page) => $page
            ->where('pipeline', [
                ['status' => 'draft', 'label' => 'Draft', 'count' => 3],
                ['status' => 'in_review', 'label' => 'In review', 'count' => 0],
                ['status' => 'changes_requested', 'label' => 'Changes requested', 'count' => 0],
                ['status' => 'approved', 'label' => 'Approved', 'count' => 1],
            ]),
        );
});

test('a distributor queue names the products waiting on review, longest waiting first', function () {
    [$user, $distributor] = newOrganizationMember();
    [, $supplier] = newSupplierMember();
    $connection = newSupplierConnection($distributor, $supplier);

    $this->travelTo(now()->subDays(3));
    $older = Product::factory()->for($distributor)->reviewed(ProductReviewStatus::InReview)
        ->create(['name' => 'Older', 'supplier_connection_id' => $connection->id]);
    $this->travelBack();

    $newer = Product::factory()->for($distributor)->reviewed(ProductReviewStatus::InReview)
        ->create(['name' => 'Newer', 'supplier_connection_id' => $connection->id]);

    /** Neither of these is the distributor's move. */
    Product::factory()->for($distributor)->create(['supplier_connection_id' => $connection->id]);
    Product::factory()->for($distributor)->reviewed(ProductReviewStatus::Approved)->create(['supplier_connection_id' => $connection->id]);

    $this
        ->actingAs($user)
        ->get(route('dashboard', ['current_organization' => $distributor->slug]))
        ->assertInertia(fn ($page) => $page
            ->where('queue.total', 2)
            ->has('queue.items', 2)
            ->where('queue.items.0.id', $older->id)
            ->where('queue.items.0.counterparty', $supplier->name)
            ->where('queue.items.0.completeness_score', 100)
            ->where('queue.items.1.id', $newer->id),
        );
});

test('a supplier queue puts products sent back ahead of drafts and stops at five', function () {
    [$supplierUser, $supplier] = newSupplierMember();
    [, $distributor] = newOrganizationMember();
    $connection = newSupplierConnection($distributor, $supplier);

    Product::factory()->count(5)->for($distributor)->create(['supplier_connection_id' => $connection->id]);
    $sentBack = Product::factory()->for($distributor)->reviewed(ProductReviewStatus::ChangesRequested)
        ->create(['supplier_connection_id' => $connection->id]);
    Product::factory()->for($distributor)->reviewed(ProductReviewStatus::InReview)->create(['supplier_connection_id' => $connection->id]);

    $this
        ->actingAs($supplierUser)
        ->get(route('dashboard', ['current_organization' => $supplier->slug]))
        ->assertInertia(fn ($page) => $page
            ->where('queue.total', 6)
            ->has('queue.items', 5)
            ->where('queue.items.0.id', $sentBack->id)
            ->where('queue.items.0.review_status', 'changes_requested')
            ->where('queue.items.0.counterparty', $distributor->name)
            ->where('queue.items.1.review_status', 'draft')
            ->where('pipeline.1.count', 1),
        );
});

test('the dashboard pipeline and queue ignore another organization products', function () {
    [$user, $distributor] = newOrganizationMember();
    [, $otherDistributor] = newOrganizationMember();

    Product::factory()->for($otherDistributor)->reviewed(ProductReviewStatus::InReview)
        ->create(['supplier_connection_id' => newSupplierConnection($otherDistributor)->id]);

    $this
        ->actingAs($user)
        ->get(route('dashboard', ['current_organization' => $distributor->slug]))
        ->assertInertia(fn ($page) => $page
            ->where('pipeline.1.count', 0)
            ->where('queue.total', 0)
            ->has('queue.items', 0),
        );
});
