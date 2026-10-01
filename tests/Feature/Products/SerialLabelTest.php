<?php

use App\Enums\OrganizationRole;
use App\Enums\ProductEventType;
use App\Models\LabelBatch;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\User;
use App\Support\UnitSerial;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * A distributor, its supplier, and one product assigned to that trade.
 *
 * @return array{0: User, 1: Organization, 2: User, 3: Organization, 4: Product}
 */
function tradeWithSerialisedProduct(): array
{
    [$distributorUser, $distributor] = newOrganizationMember();
    [$supplierUser, $supplier] = newSupplierMember();

    $connection = newSupplierConnection($distributor, $supplier);

    $product = Product::factory()->for($distributor)->create([
        'name' => 'Magnetic Building Set',
        'supplier_connection_id' => $connection->id,
    ]);

    return [$distributorUser, $distributor, $supplierUser, $supplier, $product];
}

test('issuing a run gives every box its own serial, says who it was for, and the history says so', function () {
    [$user, $organization, , , $product] = tradeWithSerialisedProduct();

    $this
        ->actingAs($user)
        ->post(route('products.label-batches.store', ['current_organization' => $organization->slug, 'product' => $product->id]), ['quantity' => 25, 'issued_for' => 'Spielwaren Muster AG', 'note' => 'Christmas shipment'])
        ->assertRedirect(route('products.edit', ['current_organization' => $organization->slug, 'product' => $product->id]));

    $batch = LabelBatch::sole();
    $units = $batch->units()->get();

    expect($batch->quantity)->toBe(25)
        ->and($batch->issued_for)->toBe('Spielwaren Muster AG')
        ->and($batch->note)->toBe('Christmas shipment')
        ->and($batch->product_id)->toBe($product->id)
        ->and($batch->organization_id)->toBe($organization->id)
        ->and($batch->created_by)->toBe($user->id)
        ->and($units)->toHaveCount(25)
        ->and($units->pluck('serial')->unique())->toHaveCount(25)
        ->and($units->every(fn (ProductUnit $unit) => UnitSerial::normalize($unit->serial) === $unit->serial))->toBeTrue()
        ->and($product->events()->first()->type)->toBe(ProductEventType::LabelsIssued)
        ->and($product->events()->first()->note)->toBe('25 serialised labels for Spielwaren Muster AG');
});

test('a run is between one label and the largest batch', function (int $quantity) {
    [$user, $organization, , , $product] = tradeWithSerialisedProduct();

    $this
        ->actingAs($user)
        ->post(route('products.label-batches.store', ['current_organization' => $organization->slug, 'product' => $product->id]), ['quantity' => $quantity, 'issued_for' => 'Spielwaren Muster AG'])
        ->assertSessionHasErrors('quantity');

    expect(LabelBatch::count())->toBe(0);
})->with([0, 401]);

test('a run prints as one label per packet at the roll size', function () {
    [$user, $organization, , , $product] = tradeWithSerialisedProduct();

    $batch = LabelBatch::issue($product, 3, 'Spielwaren Muster AG', null, $user, $organization);

    $response = $this
        ->actingAs($user)
        ->get(route('products.label-batches.pdf', ['current_organization' => $organization->slug, 'product' => $product->id, 'label_batch' => $batch->id]))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');

    $pdf = $response->getContent();

    /**
     * 50 x 30 mm is 141.73 x 85.04 points. Counted on the page objects,
     * because the text is compressed inside the stream.
     */
    expect($pdf)->toStartWith('%PDF-')
        ->toMatch('/MediaBox\s*\[\s*0(\.\d+)?\s+0(\.\d+)?\s+141(\.\d+)?\s+85(\.\d+)?\s*\]/')
        ->and(preg_match_all('/\/Type\s*\/Page[^s]/', $pdf))->toBe(3);
});

test('the labels carry each packet\'s serial and the code that leads to it', function () {
    [$user, $organization, , , $product] = tradeWithSerialisedProduct();

    $batch = LabelBatch::issue($product, 1, 'Spielwaren Muster AG', null, $user, $organization);
    $unit = ProductUnit::sole();

    $html = view('products.unit-labels', [
        'product' => $product->load('brand'),
        'units' => [['serial' => $unit->formattedSerial(), 'qr' => 'data:image/png;base64,AAAA']],
    ])->render();

    expect($html)
        ->toContain($unit->formattedSerial())
        ->and($unit->url())->toBe(route('products.public.unit', ['product' => $product->uuid, 'serial' => $unit->formattedSerial()]));
});

test('a withdrawn run withdraws every packet in it and is not printed again', function () {
    [$user, $organization, , , $product] = tradeWithSerialisedProduct();

    $batch = LabelBatch::issue($product, 3, 'Spielwaren Muster AG', null, $user, $organization);

    $this
        ->actingAs($user)
        ->delete(route('products.label-batches.destroy', ['current_organization' => $organization->slug, 'product' => $product->id, 'label_batch' => $batch->id]))
        ->assertRedirect();

    expect($batch->fresh()->revoked_at)->not->toBeNull()
        ->and(ProductUnit::whereNull('revoked_at')->count())->toBe(0)
        ->and($product->events()->first()->type)->toBe(ProductEventType::LabelsRevoked);

    $this
        ->actingAs($user)
        ->get(route('products.label-batches.pdf', ['current_organization' => $organization->slug, 'product' => $product->id, 'label_batch' => $batch->id]))
        ->assertNotFound();
});

test('a run of another product is not reachable through this one', function () {
    [$user, $organization, , , $product] = tradeWithSerialisedProduct();

    $other = Product::factory()->for($organization)->create(['supplier_connection_id' => $product->supplier_connection_id]);
    $batch = LabelBatch::issue($other, 1, 'Spielwaren Muster AG', null, $user, $organization);

    $this
        ->actingAs($user)
        ->get(route('products.label-batches.pdf', ['current_organization' => $organization->slug, 'product' => $product->id, 'label_batch' => $batch->id]))
        ->assertNotFound();
});

test('the supplier does not get the serials', function () {
    [$user, $organization, $supplierUser, $supplier, $product] = tradeWithSerialisedProduct();

    $batch = LabelBatch::issue($product, 1, 'Spielwaren Muster AG', null, $user, $organization);

    $this
        ->actingAs($supplierUser)
        ->post(route('products.label-batches.store', ['current_organization' => $supplier->slug, 'product' => $product->id]), ['quantity' => 1, 'issued_for' => 'Spielwaren Muster AG'])
        ->assertForbidden();

    $this
        ->actingAs($supplierUser)
        ->get(route('products.label-batches.pdf', ['current_organization' => $supplier->slug, 'product' => $product->id, 'label_batch' => $batch->id]))
        ->assertForbidden();
});

test('a member who may only look at the catalogue does not get the serials', function () {
    [$user, $organization, , , $product] = tradeWithSerialisedProduct();

    $batch = LabelBatch::issue($product, 1, 'Spielwaren Muster AG', null, $user, $organization);

    [$member] = newOrganizationMember();
    $organization->members()->attach($member, ['role' => OrganizationRole::Member->value]);
    $member->switchOrganization($organization);

    $this
        ->actingAs($member)
        ->get(route('products.label-batches.pdf', ['current_organization' => $organization->slug, 'product' => $product->id, 'label_batch' => $batch->id]))
        ->assertForbidden();

    $this
        ->actingAs($member)
        ->get(route('products.edit', ['current_organization' => $organization->slug, 'product' => $product->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('permissions.canManageSerialLabels', false)
            ->where('labelBatches', []));
});

test('the product page lists its runs and how many packets have been checked', function () {
    [$user, $organization, , , $product] = tradeWithSerialisedProduct();

    $batch = LabelBatch::issue($product, 4, 'Spielwaren Muster AG', null, $user, $organization);
    $batch->units()->first()->check(str_repeat('a', 64));

    $this
        ->actingAs($user)
        ->get(route('products.edit', ['current_organization' => $organization->slug, 'product' => $product->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('permissions.canManageSerialLabels', true)
            ->has('labelBatches', 1)
            ->where('labelBatches.0.quantity', 4)
            ->where('labelBatches.0.checked', 1)
            ->where('labelBatches.0.createdBy', $user->name));
});

test('a run has to say who or what it was issued for', function () {
    [$user, $organization, , , $product] = tradeWithSerialisedProduct();

    $this
        ->actingAs($user)
        ->post(route('products.label-batches.store', ['current_organization' => $organization->slug, 'product' => $product->id]), ['quantity' => 5])
        ->assertSessionHasErrors(['issued_for' => 'Say who or what these labels are for, such as a customer, shipment or order.']);

    expect(LabelBatch::count())->toBe(0);
});

/**
 * Check one of a run's serials a number of times, each from its own device.
 */
function checkedTimes(ProductUnit $unit, int $times): void
{
    foreach (range(1, $times) as $time) {
        $unit->check(str_pad((string) $time, 64, 'd'));
    }
}

test('the overview lists every serial most-checked first and flags the unusual ones', function () {
    [$user, $organization, , , $product] = tradeWithSerialisedProduct();

    $batch = LabelBatch::issue($product, 4, 'Spielwaren Muster AG', 'Christmas shipment', $user, $organization);
    [$copied, $normal] = $batch->units()->get()->all();

    checkedTimes($copied, config('labels.unusual_checks'));
    checkedTimes($normal, 2);

    $this
        ->actingAs($user)
        ->get(route('products.label-batches.show', ['current_organization' => $organization->slug, 'product' => $product->id, 'label_batch' => $batch->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('products/label-batch')
            ->where('batch.issuedFor', 'Spielwaren Muster AG')
            ->where('batch.note', 'Christmas shipment')
            ->where('summary.checked', 2)
            ->where('summary.checks', config('labels.unusual_checks') + 2)
            ->where('summary.unusual', 1)
            ->where('unusualThreshold', config('labels.unusual_checks'))
            ->has('units', 4)
            ->where('units.0.serial', $copied->formattedSerial())
            ->where('units.0.checks', config('labels.unusual_checks'))
            ->where('units.0.devices', config('labels.unusual_checks'))
            ->where('units.1.serial', $normal->formattedSerial())
            ->where('units.1.checks', 2)
            ->where('units.2.checks', 0)
            ->where('units.2.lastCheckedAt', null));
});

test('the product page sums up each run\'s checks and unusual serials', function () {
    [$user, $organization, , , $product] = tradeWithSerialisedProduct();

    $batch = LabelBatch::issue($product, 3, 'Spielwaren Muster AG', null, $user, $organization);
    [$copied, $normal] = $batch->units()->get()->all();

    checkedTimes($copied, config('labels.unusual_checks'));
    checkedTimes($normal, 1);

    $this
        ->actingAs($user)
        ->get(route('products.edit', ['current_organization' => $organization->slug, 'product' => $product->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('labelBatches.0.issuedFor', 'Spielwaren Muster AG')
            ->where('labelBatches.0.checked', 2)
            ->where('labelBatches.0.checks', config('labels.unusual_checks') + 1)
            ->where('labelBatches.0.unusual', 1));
});

test('a run\'s overview is for its distributor only, and only through its own product', function () {
    [$user, $organization, $supplierUser, $supplier, $product] = tradeWithSerialisedProduct();

    $batch = LabelBatch::issue($product, 1, 'Spielwaren Muster AG', null, $user, $organization);
    $other = Product::factory()->for($organization)->create(['supplier_connection_id' => $product->supplier_connection_id]);

    $this
        ->actingAs($supplierUser)
        ->get(route('products.label-batches.show', ['current_organization' => $supplier->slug, 'product' => $product->id, 'label_batch' => $batch->id]))
        ->assertForbidden();

    $this
        ->actingAs($user)
        ->get(route('products.label-batches.show', ['current_organization' => $organization->slug, 'product' => $other->id, 'label_batch' => $batch->id]))
        ->assertNotFound();
});
