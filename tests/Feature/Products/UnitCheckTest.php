<?php

use App\Models\LabelBatch;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Support\Turnstile;
use App\Support\UnitDevice;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
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

beforeEach(function () {
    /** Inertia's server-side rendering, when a dev server runs, is the only other request let through. */
    Http::preventStrayRequests()->allowStrayRequests(['*/__inertia_ssr']);
    config(['services.turnstile.hostnames' => ['swiss-product-seal.ch']]);
});

/**
 * Have Cloudflare's Siteverify answer every token with the given body,
 * by default a pass for the check form on our own site.
 *
 * @param  array<string, mixed>  $answer
 */
function cloudflareAnswers(array $answer = []): void
{
    Http::fake([Turnstile::VERIFY_URL => Http::response([
        'success' => true,
        'action' => Turnstile::UNIT_CHECK_ACTION,
        'hostname' => 'swiss-product-seal.ch',
        'error-codes' => [],
        ...$answer,
    ])]);
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
 * Check a packet's serial from a browser, with Turnstile passed.
 */
function checkPacket(ProductUnit $unit, ?string $browser = null, ?string $serial = null, ?Product $on = null): TestResponse
{
    cloudflareAnswers();
    $test = test();

    if ($browser !== null) {
        $test = $test->withCookie(UnitDevice::COOKIE, $browser);
    }

    return $test->post(
        route('products.public.check', ['product' => ($on ?? $unit->product)->uuid]),
        ['serial' => $serial ?? $unit->formattedSerial(), Turnstile::RESPONSE_FIELD => 'a-token'],
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

    /**
     * Read once: a second on the clock passing between the setup and the
     * assertion would otherwise fail the test on a slow runner.
     */
    $start = now()->startOfSecond();

    foreach (range(1, ProductUnit::HISTORY_LENGTH + 5) as $earlier) {
        $unit->checks()->create(['device_hash' => str_pad((string) $earlier, 64, '0')])
            ->forceFill(['created_at' => $start->copy()->subHours(100 - $earlier)])->save();
    }

    checkPacket($unit);

    $this->get($unit->url())
        ->assertInertia(fn (Assert $page) => $page
            ->where('checkResult.earlierChecks', ProductUnit::HISTORY_LENGTH + 5)
            ->has('checkResult.history', ProductUnit::HISTORY_LENGTH)
            ->where('checkResult.history.0.at', $start->copy()->subHours(100 - ProductUnit::HISTORY_LENGTH - 5)->toIso8601String()));
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

test('checking asks Cloudflare first, and a token it turns down checks nothing', function () {
    $unit = aPacket();
    cloudflareAnswers(['success' => false, 'error-codes' => ['invalid-input-response']]);

    $this->post(route('products.public.check', ['product' => $unit->product->uuid]), ['serial' => $unit->formattedSerial(), Turnstile::RESPONSE_FIELD => 'a-token'])
        ->assertSessionHasErrors(['turnstile' => 'We could not confirm you are a person. Wait for the security check to finish, then press Check again.']);

    Http::assertSent(fn (Request $request) => $request->url() === Turnstile::VERIFY_URL
        && $request['secret'] === config('services.turnstile.secret_key')
        && $request['response'] === 'a-token'
        && $request['remoteip'] === '127.0.0.1');

    expect($unit->checks()->count())->toBe(0);
});

test('a check without a token is turned away without asking Cloudflare', function () {
    $unit = aPacket();
    cloudflareAnswers();

    $this->post(route('products.public.check', ['product' => $unit->product->uuid]), ['serial' => $unit->formattedSerial()])
        ->assertSessionHasErrors('turnstile');

    Http::assertNothingSent();
    expect($unit->checks()->count())->toBe(0);
});

test('a token Cloudflare passed for another action or another site checks nothing', function (array $answer) {
    $unit = aPacket();
    cloudflareAnswers($answer);

    $this->post(route('products.public.check', ['product' => $unit->product->uuid]), ['serial' => $unit->formattedSerial(), Turnstile::RESPONSE_FIELD => 'a-token'])
        ->assertSessionHasErrors('turnstile');

    expect($unit->checks()->count())->toBe(0);
})->with([
    'another action' => [['action' => 'login']],
    'another site' => [['hostname' => 'counterfeit.example']],
]);

test('Cloudflare\'s testing keys pass without an action or a hostname', function () {
    $unit = aPacket();

    Http::fake([Turnstile::VERIFY_URL => Http::response([
        'success' => true,
        'hostname' => 'example.com',
        'error-codes' => [],
        'metadata' => ['result_with_testing_key' => true],
    ])]);

    $this->post(route('products.public.check', ['product' => $unit->product->uuid]), ['serial' => $unit->formattedSerial(), Turnstile::RESPONSE_FIELD => 'a-token'])
        ->assertSessionHasNoErrors();

    expect($unit->checks()->count())->toBe(1);
});

test('nothing is checked while Cloudflare cannot be reached', function () {
    $unit = aPacket();
    Http::fake([Turnstile::VERIFY_URL => Http::failedConnection()]);

    $this->post(route('products.public.check', ['product' => $unit->product->uuid]), ['serial' => $unit->formattedSerial(), Turnstile::RESPONSE_FIELD => 'a-token'])
        ->assertSessionHasErrors('turnstile');

    expect($unit->checks()->count())->toBe(0);
});

test('nothing is checked while no secret is set, and Cloudflare is not asked', function () {
    $unit = aPacket();
    config(['services.turnstile.secret_key' => null]);
    cloudflareAnswers();

    $this->post(route('products.public.check', ['product' => $unit->product->uuid]), ['serial' => $unit->formattedSerial(), Turnstile::RESPONSE_FIELD => 'a-token'])
        ->assertSessionHasErrors('turnstile');

    Http::assertNothingSent();
    expect($unit->checks()->count())->toBe(0);
});

test('the page hands the widget its site key', function () {
    $unit = aPacket();
    config(['services.turnstile.site_key' => '1x00000000000000000000AA']);

    $this->get($unit->url())
        ->assertInertia(fn (Assert $page) => $page->where('turnstileSiteKey', '1x00000000000000000000AA'));
});

test('a serial has to be typed', function () {
    $unit = aPacket();

    $this->post(route('products.public.check', ['product' => $unit->product->uuid]), ['serial' => ''])
        ->assertSessionHasErrors(['serial' => 'Enter the serial printed beside the QR code.']);
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
        ->post(route('products.public.check', ['product' => $unit->product->uuid]), ['serial' => $unit->formattedSerial()])
        ->assertSessionHasErrors(['turnstile' => 'Wir konnten nicht bestätigen, dass Sie ein Mensch sind. Warten Sie, bis die Sicherheitsprüfung abgeschlossen ist, und drücken Sie dann erneut auf Prüfen.']);

    $this->withUnencryptedCookie('public_lang', 'fr')
        ->get($unit->url())
        ->assertInertia(fn (Assert $page) => $page->where('locale', 'en'));
});
