<?php

use App\Models\LabelBatch;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Support\Captcha;

afterEach(fn () => Captcha::fake(null));

/**
 * A packet off a run of one.
 */
function aPacketToScan(): ProductUnit
{
    [, $organization] = newOrganizationMember();

    $product = Product::factory()->for($organization)->create([
        'name' => 'Magnetic Building Set',
        'supplier_connection_id' => newSupplierConnection($organization)->id,
    ]);

    return ProductUnit::factory()
        ->for(LabelBatch::factory()->for($product)->for($organization), 'batch')
        ->for($product)
        ->create();
}

test('a buyer scans a label, types the captcha and checks the packet', function () {
    $unit = aPacketToScan();
    Captcha::fake('ACDEF');

    visit($unit->url())
        ->assertValue('@check-serial', $unit->formattedSerial())
        ->assertMissing('@check-result')
        ->fill('@captcha-input', 'WRONG')
        ->click('@check-submit')
        ->assertSee('The code did not match.')
        ->assertMissing('@check-result')
        ->fill('@captcha-input', 'acdef')
        ->click('@check-submit')
        ->assertSee('Genuine product')
        ->assertSee('this is the first time anyone has checked it')
        ->assertNoJavaScriptErrors();

    expect($unit->checks()->count())->toBe(1);
});

test('a buyer checking a code someone else checked sees the warning and the history', function () {
    $unit = aPacketToScan();
    $unit->check(str_repeat('a', 64));
    Captcha::fake('ACDEF');

    visit($unit->url())
        ->fill('@captcha-input', 'ACDEF')
        ->click('@check-submit')
        ->assertSee('Checked before')
        ->assertSee('1 earlier check')
        ->assertSee('Another device')
        ->assertPresent('@check-history-entry')
        ->assertSeeIn('@check-history-entry', ', GMT')
        ->assertNoJavaScriptErrors();
});

test('the public page switches to German, and the server answers in German too', function () {
    $unit = aPacketToScan();
    Captcha::fake('ACDEF');

    visit($unit->url())
        ->click('@public-locale-toggle')
        ->assertSee('Ist dieses Produkt echt?')
        ->fill('@captcha-input', 'WRONG')
        ->click('@check-submit')
        ->assertSee('Der Code stimmte nicht.')
        ->assertNoJavaScriptErrors();
});

test('a serial typed on the check page leads to its packet, and an unknown one is warned about', function () {
    $unit = aPacketToScan();

    visit(route('check'))
        ->fill('@check-serial', 'ZZZZ-ZZZZ-ZZZZ')
        ->click('@check-submit')
        ->assertSee('This code is not registered. The product may not be genuine.')
        ->fill('@check-serial', strtolower($unit->formattedSerial()))
        ->click('@check-submit')
        ->assertSee('Magnetic Building Set')
        ->assertValue('@check-serial', $unit->formattedSerial())
        ->assertNoJavaScriptErrors();
});

test('a distributor issues a run of labels and withdraws it', function () {
    [$user, $organization] = newOrganizationMember();

    $product = Product::factory()->for($organization)->create([
        'name' => 'Magnetic Building Set',
        'supplier_connection_id' => newSupplierConnection($organization)->id,
    ]);

    $this->actingAs($user);

    visit(route('products.edit', ['current_organization' => $organization->slug, 'product' => $product->id]))
        ->assertSee('Serialised labels')
        ->assertMissing('@product-qr-panel')
        ->fill('@label-quantity', '12')
        ->click('@label-issue')
        ->assertSee('Say who or what these labels are for')
        ->fill('@label-issued-for', 'Spielwaren Muster AG')
        ->fill('@label-quantity', '12')
        ->click('@label-issue')
        ->assertSee('Spielwaren Muster AG')
        ->assertSee('12 boxes')
        ->assertSee('0 / 12 checked')
        ->assertPresent('@label-batch-pdf')
        ->click('@label-batch-withdraw')
        ->click('@label-batch-withdraw-confirm')
        ->assertMissing('@label-batch-pdf')
        ->assertNoJavaScriptErrors();

    expect($product->units()->count())->toBe(12)
        ->and($product->units()->whereNull('revoked_at')->count())->toBe(0);
});

test('the run overview shows unusual serials first and filters between them', function () {
    [$user, $organization] = newOrganizationMember();

    $product = Product::factory()->for($organization)->create([
        'name' => 'Magnetic Building Set',
        'supplier_connection_id' => newSupplierConnection($organization)->id,
    ]);

    $batch = LabelBatch::issue($product, 5, 'Spielwaren Muster AG', null, $user, $organization);
    $copied = $batch->units()->first();

    foreach (range(1, config('labels.unusual_checks')) as $time) {
        $copied->check(str_pad((string) $time, 64, 'd'));
    }

    $this->actingAs($user);

    visit(route('products.edit', ['current_organization' => $organization->slug, 'product' => $product->id]))
        ->assertSee('1 unusual')
        ->click('@label-batch-overview')
        ->assertSee('Labels for Spielwaren Muster AG')
        ->assertPresent('@label-batch-unusual-warning')
        ->assertCount('@label-unit-row', 1)
        ->assertSeeIn('@label-unit-row', $copied->formattedSerial())
        ->click('@label-filter-all')
        ->assertCount('@label-unit-row', 5)
        ->assertNoJavaScriptErrors();
});
