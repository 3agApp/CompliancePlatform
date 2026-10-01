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

test('issuing a run gives every packet its own serial, and the history says so', function () {
    [$user, $organization, , , $product] = tradeWithSerialisedProduct();

    $this
        ->actingAs($user)
        ->post(route('products.label-batches.store', ['current_organization' => $organization->slug, 'product' => $product->id]), ['quantity' => 25])
        ->assertRedirect(route('products.edit', ['current_organization' => $organization->slug, 'product' => $product->id]));

    $batch = LabelBatch::sole();
    $units = $batch->units()->get();

    expect($batch->quantity)->toBe(25)
        ->and($batch->product_id)->toBe($product->id)
        ->and($batch->organization_id)->toBe($organization->id)
        ->and($batch->created_by)->toBe($user->id)
        ->and($units)->toHaveCount(25)
        ->and($units->pluck('serial')->unique())->toHaveCount(25)
        ->and($units->every(fn (ProductUnit $unit) => UnitSerial::normalize($unit->serial) === $unit->serial))->toBeTrue()
        ->and($product->events()->first()->type)->toBe(ProductEventType::LabelsIssued);
});

test('a run is between one label and the largest batch', function (int $quantity) {
    [$user, $organization, , , $product] = tradeWithSerialisedProduct();

    $this
        ->actingAs($user)
        ->post(route('products.label-batches.store', ['current_organization' => $organization->slug, 'product' => $product->id]), ['quantity' => $quantity])
        ->assertSessionHasErrors('quantity');

    expect(LabelBatch::count())->toBe(0);
})->with([0, 401]);

test('a run prints as one label per packet at the roll size', function () {
    [$user, $organization, , , $product] = tradeWithSerialisedProduct();

    $batch = LabelBatch::issue($product, 3, $user, $organization);

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

    $batch = LabelBatch::issue($product, 1, $user, $organization);
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

    $batch = LabelBatch::issue($product, 3, $user, $organization);

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
    $batch = LabelBatch::issue($other, 1, $user, $organization);

    $this
        ->actingAs($user)
        ->get(route('products.label-batches.pdf', ['current_organization' => $organization->slug, 'product' => $product->id, 'label_batch' => $batch->id]))
        ->assertNotFound();
});

test('the supplier does not get the serials', function () {
    [$user, $organization, $supplierUser, $supplier, $product] = tradeWithSerialisedProduct();

    $batch = LabelBatch::issue($product, 1, $user, $organization);

    $this
        ->actingAs($supplierUser)
        ->post(route('products.label-batches.store', ['current_organization' => $supplier->slug, 'product' => $product->id]), ['quantity' => 1])
        ->assertForbidden();

    $this
        ->actingAs($supplierUser)
        ->get(route('products.label-batches.pdf', ['current_organization' => $supplier->slug, 'product' => $product->id, 'label_batch' => $batch->id]))
        ->assertForbidden();
});

test('a member who may only look at the catalogue does not get the serials', function () {
    [$user, $organization, , , $product] = tradeWithSerialisedProduct();

    $batch = LabelBatch::issue($product, 1, $user, $organization);

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

    $batch = LabelBatch::issue($product, 4, $user, $organization);
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
