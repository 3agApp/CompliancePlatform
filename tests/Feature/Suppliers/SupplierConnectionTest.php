<?php

use App\Enums\OrganizationRole;
use App\Enums\SupplierConnectionStatus;
use App\Models\Product;
use App\Models\SupplierConnection;
use App\Notifications\Suppliers\SupplierConnectionInvitation;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Notification::fake();
});

test('a distributor invites a supplier by company name and contact email', function () {
    [$user, $distributor] = newOrganizationMember();

    $this
        ->actingAs($user)
        ->post(route('suppliers.store', ['current_organization' => $distributor->slug]), [
            'company_name' => 'Acme Supplies',
            'contact_email' => 'compliance@acme.test',
        ])
        ->assertRedirect(route('suppliers.index', ['current_organization' => $distributor->slug]));

    $connection = SupplierConnection::sole();

    expect($connection)
        ->company_name->toBe('Acme Supplies')
        ->contact_email->toBe('compliance@acme.test')
        ->status->toBe(SupplierConnectionStatus::Pending)
        ->supplier_organization_id->toBeNull()
        ->distributor_organization_id->toBe($distributor->id);

    expect(strlen($connection->code))->toBe(64);

    Notification::assertSentOnDemand(SupplierConnectionInvitation::class);
});

test('the supplier invitation does not disclose whether the email already has an account', function () {
    [$knownUser] = newSupplierMember();
    [$user, $distributor] = newOrganizationMember();

    $known = $this
        ->actingAs($user)
        ->post(route('suppliers.store', ['current_organization' => $distributor->slug]), [
            'company_name' => 'Known Company',
            'contact_email' => $knownUser->email,
        ]);

    $unknown = $this
        ->actingAs($user)
        ->post(route('suppliers.store', ['current_organization' => $distributor->slug]), [
            'company_name' => 'Unknown Company',
            'contact_email' => 'nobody@nowhere.test',
        ]);

    $known->assertSessionHasNoErrors()->assertRedirect(route('suppliers.index', ['current_organization' => $distributor->slug]));
    $unknown->assertSessionHasNoErrors()->assertRedirect(route('suppliers.index', ['current_organization' => $distributor->slug]));

    expect($known->getStatusCode())->toBe($unknown->getStatusCode());

    expect(SupplierConnection::query()->whereNotNull('supplier_organization_id')->count())->toBe(0);
});

test('a distributor cannot invite the same contact email twice while a connection is pending', function () {
    [$user, $distributor] = newOrganizationMember();

    newSupplierConnection($distributor, attributes: ['contact_email' => 'compliance@acme.test']);

    $this
        ->actingAs($user)
        ->post(route('suppliers.store', ['current_organization' => $distributor->slug]), [
            'company_name' => 'Acme Supplies',
            'contact_email' => 'COMPLIANCE@acme.test',
        ])
        ->assertSessionHasErrors('contact_email');

    expect(SupplierConnection::query()->count())->toBe(1);
});

test('a distributor can invite a contact again after the invitation was declined', function () {
    [$user, $distributor] = newOrganizationMember();

    $connection = newSupplierConnection($distributor, attributes: [
        'contact_email' => 'compliance@acme.test',
        'status' => SupplierConnectionStatus::Declined,
    ]);

    $originalCode = $connection->code;

    $this
        ->actingAs($user)
        ->post(route('suppliers.store', ['current_organization' => $distributor->slug]), [
            'company_name' => 'Acme Supplies AG',
            'contact_email' => 'compliance@acme.test',
        ])
        ->assertSessionHasNoErrors();

    expect(SupplierConnection::query()->count())->toBe(1);

    expect($connection->fresh())
        ->status->toBe(SupplierConnectionStatus::Pending)
        ->company_name->toBe('Acme Supplies AG')
        ->code->not->toBe($originalCode);
});

test('only distributors can reach the suppliers page', function () {
    [$supplierUser, $supplier] = newSupplierMember();

    $this
        ->actingAs($supplierUser)
        ->get(route('suppliers.index', ['current_organization' => $supplier->slug]))
        ->assertNotFound();
});

test('only suppliers can reach the distributors page', function () {
    [$user, $distributor] = newOrganizationMember();

    $this
        ->actingAs($user)
        ->get(route('distributors.index', ['current_organization' => $distributor->slug]))
        ->assertNotFound();
});

test('a supplier sees the distributors it is connected to', function () {
    [$supplierUser, $supplier] = newSupplierMember();
    [, $distributor] = newOrganizationMember();

    newSupplierConnection($distributor, $supplier);
    newSupplierConnection($distributor, attributes: ['contact_email' => 'someone@else.test']);

    $this
        ->actingAs($supplierUser)
        ->get(route('distributors.index', ['current_organization' => $supplier->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('distributors/index')
            ->has('connections', 1)
            ->where('connections.0.distributorName', $distributor->name),
        );
});

test('members cannot invite suppliers', function () {
    [$user, $distributor] = newOrganizationMember(OrganizationRole::Member);

    $this
        ->actingAs($user)
        ->post(route('suppliers.store', ['current_organization' => $distributor->slug]), [
            'company_name' => 'Acme Supplies',
            'contact_email' => 'compliance@acme.test',
        ])
        ->assertForbidden();

    $this
        ->actingAs($user)
        ->get(route('suppliers.index', ['current_organization' => $distributor->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('permissions.canManageConnection', false));

    $this->assertDatabaseCount('supplier_connections', 0);
});

test('a distributor revokes an active connection and the products keep their assignment', function () {
    [$user, $distributor] = newOrganizationMember();
    [, $supplier] = newSupplierMember();

    $connection = newSupplierConnection($distributor, $supplier);
    $product = Product::factory()->for($distributor)->create(['supplier_connection_id' => $connection->id]);

    $this
        ->actingAs($user)
        ->patch(route('suppliers.revoke', [
            'current_organization' => $distributor->slug,
            'supplier_connection' => $connection->id,
        ]))
        ->assertRedirect(route('suppliers.index', ['current_organization' => $distributor->slug]));

    expect($connection->fresh())
        ->status->toBe(SupplierConnectionStatus::Revoked)
        ->supplier_organization_id->toBe($supplier->id);

    expect($product->fresh()->supplier_connection_id)->toBe($connection->id);
});

test('a distributor restores a revoked connection', function () {
    [$user, $distributor] = newOrganizationMember();
    [, $supplier] = newSupplierMember();

    $connection = newSupplierConnection($distributor, $supplier);
    $connection->update(['status' => SupplierConnectionStatus::Revoked]);

    $this
        ->actingAs($user)
        ->patch(route('suppliers.restore', [
            'current_organization' => $distributor->slug,
            'supplier_connection' => $connection->id,
        ]))
        ->assertSessionHasNoErrors();

    expect($connection->fresh()->status)->toBe(SupplierConnectionStatus::Active);
});

test('a distributor cannot reach a connection belonging to another distributor', function () {
    [$user, $distributor] = newOrganizationMember();
    [, $otherDistributor] = newOrganizationMember();

    $foreignConnection = newSupplierConnection($otherDistributor);

    $this
        ->actingAs($user)
        ->patch(route('suppliers.revoke', [
            'current_organization' => $distributor->slug,
            'supplier_connection' => $foreignConnection->id,
        ]))
        ->assertNotFound();

    expect($foreignConnection->fresh()->status)->toBe(SupplierConnectionStatus::Pending);
});

test('deleting a distributor organization takes its supplier connections and catalogue', function () {
    [$user, $distributor] = newOrganizationMember();
    [$supplierUser, $supplier] = newSupplierMember();

    $connection = newSupplierConnection($distributor, $supplier);
    $product = Product::factory()->for($distributor)->create(['supplier_connection_id' => $connection->id]);

    $this
        ->actingAs($user)
        ->delete(route('organizations.destroy', ['organization' => $distributor->slug]), [
            'name' => $distributor->name,
        ])
        ->assertSessionHasNoErrors();

    /*
     * The trade belonged to the distributor, so it goes with it -- and so
     * does the catalogue the supplier was answering for. The supplier keeps
     * its own organization and everything else it is party to.
     */
    $this->assertModelMissing($connection);
    $this->assertModelMissing($product);
    $this->assertModelExists($supplier);

    $this
        ->actingAs($supplierUser)
        ->get(route('products.edit', ['current_organization' => $supplier->slug, 'product' => $product->id]))
        ->assertNotFound();
});

test('guests cannot reach the suppliers page', function () {
    [, $distributor] = newOrganizationMember();

    $this
        ->get(route('suppliers.index', ['current_organization' => $distributor->slug]))
        ->assertRedirect(route('login'));
});

test('a distributor sends the invitation again for a connection revoked before it was claimed', function () {
    [$user, $distributor] = newOrganizationMember();

    $connection = newSupplierConnection($distributor, attributes: [
        'status' => SupplierConnectionStatus::Revoked,
        'expires_at' => now()->subDay(),
    ]);

    $originalCode = $connection->code;

    $this
        ->actingAs($user)
        ->post(route('suppliers.resend', [
            'current_organization' => $distributor->slug,
            'supplier_connection' => $connection->id,
        ]))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('suppliers.index', ['current_organization' => $distributor->slug]));

    expect($connection->fresh())
        ->status->toBe(SupplierConnectionStatus::Pending)
        ->code->not->toBe($originalCode)
        ->expires_at->toBeGreaterThan(now());

    Notification::assertSentOnDemand(SupplierConnectionInvitation::class);
});

test('a claimed connection cannot be invited again', function () {
    [$user, $distributor] = newOrganizationMember();
    [, $supplier] = newSupplierMember();

    $connection = newSupplierConnection($distributor, $supplier);
    $connection->update(['status' => SupplierConnectionStatus::Revoked]);

    $this
        ->actingAs($user)
        ->post(route('suppliers.resend', [
            'current_organization' => $distributor->slug,
            'supplier_connection' => $connection->id,
        ]))
        ->assertNotFound();

    expect($connection->fresh()->status)->toBe(SupplierConnectionStatus::Revoked);

    Notification::assertNothingSent();
});

test('a declined connection cannot be invited again', function () {
    [$user, $distributor] = newOrganizationMember();

    $connection = newSupplierConnection($distributor, attributes: [
        'status' => SupplierConnectionStatus::Declined,
    ]);

    $this
        ->actingAs($user)
        ->post(route('suppliers.resend', [
            'current_organization' => $distributor->slug,
            'supplier_connection' => $connection->id,
        ]))
        ->assertNotFound();

    expect($connection->fresh()->status)->toBe(SupplierConnectionStatus::Declined);

    Notification::assertNothingSent();
});

test('the suppliers list offers to invite a revoked unclaimed connection again', function () {
    [$user, $distributor] = newOrganizationMember();
    [, $supplier] = newSupplierMember();

    newSupplierConnection($distributor, attributes: [
        'company_name' => 'Unclaimed Supplies',
        'status' => SupplierConnectionStatus::Revoked,
    ]);

    $claimed = newSupplierConnection($distributor, $supplier, [
        'company_name' => 'Anchored Supplies',
        'contact_email' => 'claimed@acme.test',
    ]);
    $claimed->update(['status' => SupplierConnectionStatus::Revoked]);

    $this
        ->actingAs($user)
        ->get(route('suppliers.index', ['current_organization' => $distributor->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('connections.0.companyName', $supplier->name)
            ->where('connections.0.canResend', false)
            ->where('connections.0.canRestore', true)
            ->where('connections.1.companyName', 'Unclaimed Supplies')
            ->where('connections.1.canResend', true)
            ->where('connections.1.canRestore', false),
        );
});
