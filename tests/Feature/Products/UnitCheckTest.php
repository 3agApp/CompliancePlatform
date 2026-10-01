<?php

use App\Models\LabelBatch;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Support\Captcha;
use App\Support\UnitDevice;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * One packet of a product, off a run of one.
 */
function aPacket(array $attributes = []): ProductUnit
{
    [, $organization] = newOrganizationMember();

    $product = Product::factory()->for($organization)->create([
        'name' => 'Magnetic Building Set',
        'supplier_connection_id' => newSupplierConnection($organization)->id,
    ]);

    return ProductUnit::factory()
        ->for(LabelBatch::factory()->for($product)->for($organization), 'batch')
        ->for($product)
        ->create($attributes);
}

/**
 * A session holding a captcha whose answer is ABCDE.
 *
 * @return array<string, array{code: string, expires_at: int}>
 */
function aSolvedCaptcha(): array
{
    return [Captcha::SESSION_KEY => ['code' => 'ABCDE', 'expires_at' => now()->addMinutes(5)->getTimestamp()]];
}

/**
 * A browser, as the cookie it carries and the hash it is stored as.
 *
 * @return array{0: string, 1: string}
 */
function aBrowser(): array
{
    $id = (string) Str::uuid();

    return [$id, hash_hmac('sha256', $id, (string) config('app.key'))];
}

/**
 * Check a packet's serial from a browser, with the captcha answered.
 */
function checkPacket(ProductUnit $unit, ?string $browser = null, ?string $serial = null, ?Product $on = null): TestResponse
{
    $test = test()->withSession(aSolvedCaptcha());

    if ($browser !== null) {
        $test = $test->withCookie(UnitDevice::COOKIE, $browser);
    }

    return $test->post(
        route('products.public.check', ['product' => ($on ?? $unit->product)->uuid]),
        ['serial' => $serial ?? $unit->formattedSerial(), 'captcha' => 'abcde'],
    );
}

test('scanning a label fills the serial in and says nothing about the packet', function () {
    $unit = aPacket();

    $this->get($unit->url())
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('products/public')
            ->where('product.name', 'Magnetic Building Set')
            ->where('serial', $unit->formattedSerial())
            ->where('checkResult', null)
            ->where('checkable', true)
            ->where('checkUrl', route('products.public.check', ['product' => $unit->product->uuid])));

    expect($unit->checks()->count())->toBe(0)
        ->and($unit->fresh()->first_checked_at)->toBeNull();
});

test('a serial off the label is filled in however it was written', function () {
    $unit = aPacket(['serial' => '0123ABCD4567']);

    $this->get(route('products.public.unit', ['product' => $unit->product->uuid, 'serial' => 'o123abcd4567']))
        ->assertInertia(fn (Assert $page) => $page->where('serial', '0123-ABCD-4567'));
});

test('the first check of a new packet says it is genuine and first', function () {
    $unit = aPacket();
    [$browser] = aBrowser();

    checkPacket($unit, $browser)->assertRedirect($unit->url());

    $this->withCookie(UnitDevice::COOKIE, $browser)
        ->get($unit->url())
        ->assertInertia(fn (Assert $page) => $page
            ->where('checkResult.status', 'first_check')
            ->where('checkResult.serial', $unit->formattedSerial())
            ->where('checkResult.earlierChecks', 0)
            ->where('checkResult.history', []));

    expect($unit->checks()->count())->toBe(1)
        ->and($unit->fresh()->first_checked_at)->not->toBeNull();
});

test('the result is shown once, to whoever checked, and not to a later visit', function () {
    $unit = aPacket();

    checkPacket($unit);

    $this->get($unit->url())->assertInertia(fn (Assert $page) => $page->where('checkResult.status', 'first_check'));
    $this->get($unit->url())->assertInertia(fn (Assert $page) => $page->where('checkResult', null));
});

test('checking again from the same device is still genuine, with its own history', function () {
    [$browser, $hash] = aBrowser();
    $unit = ProductUnit::factory()->checkedBy($hash, now()->subDays(2))->create(['label_batch_id' => aPacket()->label_batch_id]);

    checkPacket($unit, $browser);

    $this->get($unit->url())
        ->assertInertia(fn (Assert $page) => $page
            ->where('checkResult.status', 'checked_before')
            ->where('checkResult.earlierChecks', 1)
            ->where('checkResult.history.0.thisDevice', true));
});

test('a code checked from another device warns, and shows when it was checked', function () {
    [, $owner] = aBrowser();
    [$someoneElse] = aBrowser();
    $checkedAt = now()->subDays(3)->startOfSecond();

    $unit = ProductUnit::factory()->checkedBy($owner, $checkedAt)->create(['label_batch_id' => aPacket()->label_batch_id]);

    checkPacket($unit, $someoneElse);

    $this->get($unit->url())
        ->assertInertia(fn (Assert $page) => $page
            ->where('checkResult.status', 'checked_elsewhere')
            ->where('checkResult.earlierChecks', 1)
            ->where('checkResult.history.0.at', $checkedAt->toIso8601String())
            ->where('checkResult.history.0.thisDevice', false));

    expect($unit->checks()->count())->toBe(2);
});

test('the history is newest first and capped, with the total kept', function () {
    $unit = aPacket();

    foreach (range(1, ProductUnit::HISTORY_LENGTH + 5) as $earlier) {
        $unit->checks()->create(['device_hash' => str_pad((string) $earlier, 64, '0')])
            ->forceFill(['created_at' => now()->subHours(100 - $earlier)])->save();
    }

    checkPacket($unit);

    $this->get($unit->url())
        ->assertInertia(fn (Assert $page) => $page
            ->where('checkResult.earlierChecks', ProductUnit::HISTORY_LENGTH + 5)
            ->has('checkResult.history', ProductUnit::HISTORY_LENGTH)
            ->where('checkResult.history.0.at', now()->subHours(100 - ProductUnit::HISTORY_LENGTH - 5)->toIso8601String()));
});

test('a serial nobody issued is reported as not recognised, and nothing is recorded', function () {
    $unit = aPacket();

    $this->from($unit->product->publicUrl());

    checkPacket($unit, serial: 'ZZZZ-ZZZZ-ZZZZ')->assertRedirect($unit->product->publicUrl());

    $this->get($unit->product->publicUrl())
        ->assertInertia(fn (Assert $page) => $page
            ->where('checkResult.status', 'unknown')
            ->where('checkResult.serial', 'ZZZZ-ZZZZ-ZZZZ'));

    expect($unit->checks()->count())->toBe(0);
});

test('a serial issued for another product is not recognised on this one', function () {
    $unit = aPacket();
    $other = aPacket();

    checkPacket($unit, on: $other->product);

    $this->get($other->product->publicUrl())
        ->assertInertia(fn (Assert $page) => $page->where('checkResult.status', 'unknown'));

    expect($unit->checks()->count())->toBe(0);
});

test('a result is only ever shown on the page of the product it was checked on', function () {
    $unit = aPacket();
    $other = aPacket();

    checkPacket($unit);

    $this->get($other->product->publicUrl())
        ->assertInertia(fn (Assert $page) => $page->where('checkResult', null));
});

test('a withdrawn code says so, and the check is not kept', function () {
    $unit = aPacket(['revoked_at' => now()]);

    checkPacket($unit);

    $this->get($unit->url())
        ->assertInertia(fn (Assert $page) => $page
            ->where('checkResult.status', 'revoked')
            ->where('checkResult.history', []));

    expect($unit->checks()->count())->toBe(0);
});

test('checking takes the captcha, and a wrong answer checks nothing', function () {
    $unit = aPacket();

    $this->withSession(aSolvedCaptcha())
        ->post(route('products.public.check', ['product' => $unit->product->uuid]), ['serial' => $unit->formattedSerial(), 'captcha' => 'WRONG'])
        ->assertSessionHasErrors(['captcha' => 'The code did not match. Type the new code shown in the picture.']);

    $this->post(route('products.public.check', ['product' => $unit->product->uuid]), ['serial' => $unit->formattedSerial()])
        ->assertSessionHasErrors('captcha');

    expect($unit->checks()->count())->toBe(0);
});

test('a captcha answers once, so it cannot be replayed against another serial', function () {
    $unit = aPacket();

    checkPacket($unit, serial: 'ZZZZ-ZZZZ-ZZZZ');

    $this->post(route('products.public.check', ['product' => $unit->product->uuid]), ['serial' => $unit->formattedSerial(), 'captcha' => 'ABCDE'])
        ->assertSessionHasErrors('captcha');

    expect($unit->checks()->count())->toBe(0);
});

test('an expired captcha is not an answer', function () {
    $unit = aPacket();

    $this->withSession([Captcha::SESSION_KEY => ['code' => 'ABCDE', 'expires_at' => now()->subSecond()->getTimestamp()]])
        ->post(route('products.public.check', ['product' => $unit->product->uuid]), ['serial' => $unit->formattedSerial(), 'captcha' => 'ABCDE'])
        ->assertSessionHasErrors('captcha');
});

test('a serial has to be typed', function () {
    $unit = aPacket();

    $this->withSession(aSolvedCaptcha())
        ->post(route('products.public.check', ['product' => $unit->product->uuid]), ['serial' => '', 'captcha' => 'ABCDE'])
        ->assertSessionHasErrors(['serial' => 'Enter the serial printed beside the QR code.']);
});

test('the captcha is a fresh picture every time, and never cached', function () {
    $response = $this->get(route('captcha'))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png')
        ->assertSessionHas(Captcha::SESSION_KEY);

    expect($response->headers->get('Cache-Control'))->toContain('no-store')
        ->and(getimagesizefromstring($response->getContent()))->not->toBeFalse();
});

test('the packet page says nothing more about the trade than the plain page', function () {
    $unit = aPacket();

    $this->get($unit->url())
        ->assertInertia(fn (Assert $page) => $page
            ->missing('product.supplier_connection_id')
            ->missing('product.documents')
            ->missing('unit'));
});

test('the check page sends a typed serial to its packet\'s page', function () {
    $unit = aPacket();

    $this->get(route('check'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('check'));

    $this->post(route('check.store'), ['serial' => strtolower($unit->serial)])
        ->assertRedirect($unit->url());

    expect($unit->checks()->count())->toBe(0);
});

test('the check page warns about a serial nobody issued', function () {
    aPacket();

    $this->post(route('check.store'), ['serial' => 'ZZZZ-ZZZZ-ZZZZ'])
        ->assertSessionHasErrors(['serial' => 'This code is not registered. The product may not be genuine.']);
});

test('serials cannot be tried faster than a person types', function () {
    $unit = aPacket();

    foreach (range(1, 30) as $attempt) {
        $this->post(route('check.store'), ['serial' => 'ZZZZ-ZZZZ-ZZZZ']);
    }

    $this->post(route('check.store'), ['serial' => $unit->serial])->assertTooManyRequests();
});

test('the plain page offers a check only for a product whose packets carry serials', function () {
    $unit = aPacket();
    $plain = Product::factory()->for($unit->product->organization)->create(['supplier_connection_id' => $unit->product->supplier_connection_id]);

    $this->get($unit->product->publicUrl())
        ->assertInertia(fn (Assert $page) => $page->where('checkable', true)->where('serial', null));

    $this->get($plain->publicUrl())
        ->assertInertia(fn (Assert $page) => $page->where('checkable', false));
});

test('the reader\'s language is read off the switch\'s cookie, and the server answers in it', function () {
    $unit = aPacket();

    $this->withUnencryptedCookie('public_lang', 'de')
        ->get($unit->url())
        ->assertInertia(fn (Assert $page) => $page->where('locale', 'de'));

    $this->withUnencryptedCookie('public_lang', 'de')
        ->withSession(aSolvedCaptcha())
        ->post(route('products.public.check', ['product' => $unit->product->uuid]), ['serial' => $unit->formattedSerial(), 'captcha' => 'WRONG'])
        ->assertSessionHasErrors(['captcha' => 'Der Code stimmte nicht. Bitte geben Sie den neuen Code aus dem Bild ein.']);

    $this->withUnencryptedCookie('public_lang', 'fr')
        ->get($unit->url())
        ->assertInertia(fn (Assert $page) => $page->where('locale', 'en'));
});
