<?php

use App\Enums\OrganizationType;
use App\Enums\SupplierConnectionStatus;
use App\Models\Organization;
use App\Models\Product;
use App\Models\SupplierConnection;
use App\Models\User;
use Illuminate\Support\Facades\Notification;

test('a supplier is invited through the invite supplier dialog', function () {
    Notification::fake();

    [$user, $distributor] = newOrganizationMember();

    $this->actingAs($user);

    $page = visit(route('suppliers.index', ['current_organization' => $distributor->slug]));

    $page->assertSee('No suppliers yet')
        ->click('@invite-supplier-button')
        ->assertSee('Invite a supplier')
        ->fill('@supplier-company-name', 'Acme Supplies AG')
        ->fill('@supplier-contact-email', 'compliance@acme.test')
        ->click('@invite-supplier-submit')
        ->assertSee('Acme Supplies AG')
        ->assertSee('compliance@acme.test')
        ->assertSee('Pending')
        ->assertNoJavaScriptErrors();

    expect(SupplierConnection::sole())
        ->company_name->toBe('Acme Supplies AG')
        ->status->toBe(SupplierConnectionStatus::Pending);
});

test('the invite supplier dialog stays open and shows the validation message for a duplicate contact', function () {
    [$user, $distributor] = newOrganizationMember();

    newSupplierConnection($distributor, attributes: ['contact_email' => 'compliance@acme.test']);

    $this->actingAs($user);

    $page = visit(route('suppliers.index', ['current_organization' => $distributor->slug]));

    $page->click('@invite-supplier-button')
        ->fill('@supplier-company-name', 'Acme Supplies AG')
        ->fill('@supplier-contact-email', 'compliance@acme.test')
        ->click('@invite-supplier-submit')
        ->assertSee('You already have a supplier connection with this email address.')
        ->assertNoJavaScriptErrors();

    expect(SupplierConnection::query()->count())->toBe(1);
});

test('a recipient claims a connection by creating a new company', function () {
    [, $distributor] = newOrganizationMember();
    $recipient = User::factory()->withoutOrganization()->create();

    $connection = newSupplierConnection($distributor, attributes: [
        'company_name' => 'Acme Supplies AG',
        'contact_email' => $recipient->email,
    ]);

    $this->actingAs($recipient);

    $page = visit(route('connections.show', ['connection' => $connection->code]));

    $page->assertSee($distributor->name)
        ->assertSee('Acme Supplies AG')
        ->click('@claim-connection-submit')
        ->assertNoJavaScriptErrors();

    $supplier = Organization::where('name', 'Acme Supplies AG')->sole();

    expect($supplier->type)->toBe(OrganizationType::Supplier);

    expect($connection->fresh())
        ->status->toBe(SupplierConnectionStatus::Active)
        ->supplier_organization_id->toBe($supplier->id);
});

test('a recipient claims a connection with a company they already run', function () {
    [$supplierUser, $supplier] = newSupplierMember();
    [, $distributor] = newOrganizationMember();

    $connection = newSupplierConnection($distributor, attributes: [
        'contact_email' => $supplierUser->email,
    ]);

    $this->actingAs($supplierUser);

    $page = visit(route('connections.show', ['connection' => $connection->code]));

    $page->assertSee('A company I already run')
        ->click('@claim-connection-existing-company')
        ->click('@claim-connection-submit')
        ->assertNoJavaScriptErrors();

    expect(Organization::query()->count())->toBe(2);

    expect($connection->fresh())
        ->status->toBe(SupplierConnectionStatus::Active)
        ->supplier_organization_id->toBe($supplier->id);
});

test('a recipient declines a connection', function () {
    [, $distributor] = newOrganizationMember();
    $recipient = User::factory()->withoutOrganization()->create();

    $connection = newSupplierConnection($distributor, attributes: [
        'contact_email' => $recipient->email,
    ]);

    $this->actingAs($recipient);

    visit(route('connections.show', ['connection' => $connection->code]))
        ->click('@claim-connection-decline')
        ->assertNoJavaScriptErrors();

    expect($connection->fresh())
        ->status->toBe(SupplierConnectionStatus::Declined)
        ->supplier_organization_id->toBeNull();
});

test('a supplier sees the assigned products list without the create and delete controls', function () {
    [$supplierUser, $supplier] = newSupplierMember();
    [, $distributor] = newOrganizationMember();

    $connection = newSupplierConnection($distributor, $supplier);
    Product::factory()->for($distributor)->create([
        'name' => 'Oat Milk',
        'supplier_connection_id' => $connection->id,
    ]);

    $this->actingAs($supplierUser);

    visit(route('products.index', ['current_organization' => $supplier->slug]))
        ->assertSee('Oat Milk')
        ->assertSee($distributor->name)
        ->assertMissing('@products-new-product-button')
        ->assertMissing('@product-delete-button')
        ->assertPresent('@product-edit-button')
        ->assertNoJavaScriptErrors();
});

test('a supplier edits an assigned product without being able to reassign it', function () {
    [$supplierUser, $supplier] = newSupplierMember();
    [, $distributor] = newOrganizationMember();

    $connection = newSupplierConnection($distributor, $supplier);
    $product = Product::factory()->for($distributor)->create([
        'name' => 'Oat Milk',
        'supplier_connection_id' => $connection->id,
    ]);

    $this->actingAs($supplierUser);

    visit(route('products.edit', ['current_organization' => $supplier->slug, 'product' => $product->id]))
        ->assertSee($distributor->name)
        ->assertMissing('@product-supplier')
        ->fill('@product-name', 'Organic Oat Milk')
        ->click('@update-product-submit')
        ->assertNoJavaScriptErrors();

    expect($product->fresh())
        ->name->toBe('Organic Oat Milk')
        ->supplier_connection_id->toBe($connection->id);
});

test('a distributor assigns a supplier from the product edit page', function () {
    [$user, $distributor] = newOrganizationMember();

    $connection = newSupplierConnection($distributor, attributes: ['company_name' => 'Acme Supplies AG']);
    $product = Product::factory()->for($distributor)->create(['name' => 'Oat Milk']);

    $this->actingAs($user);

    visit(route('products.edit', ['current_organization' => $distributor->slug, 'product' => $product->id]))
        ->click('@product-supplier')
        ->click('[role="option"]:has-text("Acme Supplies AG")')
        ->click('@update-product-submit')
        ->assertNoJavaScriptErrors();

    expect($product->fresh()->supplier_connection_id)->toBe($connection->id);
});

test('a distributor with no suppliers is pointed at the suppliers page', function () {
    [$user, $distributor] = newOrganizationMember();

    $this->actingAs($user);

    visit(route('products.index', ['current_organization' => $distributor->slug]))
        ->assertSee('Invite a supplier first')
        ->assertMissing('@products-new-product-button')
        ->assertPresent('@products-invite-supplier-button')
        ->assertNoJavaScriptErrors();
});

test('the sidebar shows suppliers for a distributor', function () {
    [$user, $distributor] = newOrganizationMember();

    $this->actingAs($user);

    visit(route('dashboard', ['current_organization' => $distributor->slug]))
        ->assertSee('Suppliers')
        ->assertDontSee('Distributors')
        ->assertNoJavaScriptErrors();
});

test('the sidebar shows distributors for a supplier', function () {
    [$supplierUser, $supplier] = newSupplierMember();

    $this->actingAs($supplierUser);

    visit(route('dashboard', ['current_organization' => $supplier->slug]))
        ->assertSee('Distributors')
        ->assertSee('Assigned products')
        ->assertNoJavaScriptErrors();
});
