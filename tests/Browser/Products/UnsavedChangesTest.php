<?php

use App\Models\Product;

/**
 * The guard asks through window.confirm, which the browser driver dismisses
 * on its own. A dismissed prompt is the person saying "stay", so a blocked
 * navigation is what proves the guard was armed.
 */
test('picking a supplier arms the unsaved changes guard', function () {
    [$user, $distributor] = newOrganizationMember();
    newSupplierConnection($distributor, attributes: ['company_name' => 'Acme Supplies']);

    $this->actingAs($user);

    $createUrl = route('products.create', ['current_organization' => $distributor->slug]);

    $page = visit($createUrl);

    // A Radix select raises change but never input, which is the case the
    // guard used to miss entirely.
    $page->click('@product-supplier')
        ->click('[role="option"]:has-text("Acme Supplies")');

    $armed = $page->script(<<<'JS'
        const event = new Event('beforeunload', { cancelable: true });
        window.dispatchEvent(event);
        event.defaultPrevented;
    JS);

    expect($armed)->toBeTrue();

    $page->assertNoJavaScriptErrors();
});

test('an untouched create form lets the person leave', function () {
    [$user, $distributor] = newOrganizationMember();
    newSupplierConnection($distributor, attributes: ['company_name' => 'Acme Supplies']);

    $this->actingAs($user);

    $page = visit(route('products.create', ['current_organization' => $distributor->slug]));

    $armed = $page->script(<<<'JS'
        const event = new Event('beforeunload', { cancelable: true });
        window.dispatchEvent(event);
        event.defaultPrevented;
    JS);

    expect($armed)->toBeFalse();

    $page->assertNoJavaScriptErrors();
});

test('an untouched edit form lets the person leave', function () {
    [$user, $distributor] = newOrganizationMember();
    $connection = newSupplierConnection($distributor, attributes: ['company_name' => 'Acme Supplies']);

    $product = Product::factory()->for($distributor)->create([
        'name' => 'Oat Milk',
        'supplier_connection_id' => $connection->id,
    ]);

    $this->actingAs($user);

    $page = visit(route('products.edit', [
        'current_organization' => $distributor->slug,
        'product' => $product->id,
    ]));

    $armed = $page->script(<<<'JS'
        const event = new Event('beforeunload', { cancelable: true });
        window.dispatchEvent(event);
        event.defaultPrevented;
    JS);

    expect($armed)->toBeFalse();

    $page->assertNoJavaScriptErrors();
});

test('editing a product arms the unsaved changes guard', function () {
    [$user, $distributor] = newOrganizationMember();
    $connection = newSupplierConnection($distributor, attributes: ['company_name' => 'Acme Supplies']);

    $product = Product::factory()->for($distributor)->create([
        'name' => 'Oat Milk',
        'supplier_connection_id' => $connection->id,
    ]);

    $this->actingAs($user);

    $page = visit(route('products.edit', [
        'current_organization' => $distributor->slug,
        'product' => $product->id,
    ]));

    $page->fill('@product-name', 'Rice Milk');

    $armed = $page->script(<<<'JS'
        const event = new Event('beforeunload', { cancelable: true });
        window.dispatchEvent(event);
        event.defaultPrevented;
    JS);

    expect($armed)->toBeTrue();

    $page->assertNoJavaScriptErrors();
});

/**
 * The guard asks through window.confirm, so the tests below answer it
 * themselves -- "leave" -- and keep a note of every time it was asked.
 * Client-side visits keep the window, so the stand-in lasts the session.
 */
const RECORD_CONFIRMS = <<<'JS'
    window.confirmed = [];
    window.confirm = () => { window.confirmed.push(location.pathname); return true; };
    true;
JS;

const GUARD_ARMED = <<<'JS'
    const event = new Event('beforeunload', { cancelable: true });
    window.dispatchEvent(event);
    event.defaultPrevented;
JS;

/**
 * Click a link the way a person would, so Inertia makes the visit.
 */
function followLink(mixed $page, string $text): void
{
    $page->script('[...document.querySelectorAll("main a")].find((link) => link.textContent.trim().includes('.json_encode($text).')).click(); true;');
    $page->wait(1);
}

test('a product opened after leaving an edited one starts with nothing unsaved', function () {
    [$user, $distributor] = newOrganizationMember();
    $connection = newSupplierConnection($distributor, attributes: ['company_name' => 'Acme Supplies']);

    $oatMilk = Product::factory()->for($distributor)->create([
        'name' => 'Oat Milk',
        'supplier_connection_id' => $connection->id,
    ]);
    Product::factory()->for($distributor)->create([
        'name' => 'Soy Milk',
        'supplier_connection_id' => $connection->id,
    ]);

    $this->actingAs($user);

    $page = visit(route('products.edit', [
        'current_organization' => $distributor->slug,
        'product' => $oatMilk->id,
    ]));

    $page->script(RECORD_CONFIRMS);
    $page->fill('@product-name', 'Rice Milk');

    followLink($page, 'Products');

    expect($page->script('window.confirmed'))->toHaveCount(1);

    followLink($page, 'Soy Milk');

    $page->assertValue('@product-name', 'Soy Milk');

    // Opened, its deferred panels loaded, and still never touched: no
    // question on the way in, and none on the way out.
    expect($page->script(GUARD_ARMED))->toBeFalse();

    followLink($page, 'Products');

    expect($page->script('window.confirmed'))->toHaveCount(1);

    $page->assertNoJavaScriptErrors();
});

test('picking a country of origin arms the unsaved changes guard', function () {
    [$user, $distributor] = newOrganizationMember();
    $connection = newSupplierConnection($distributor, attributes: ['company_name' => 'Acme Supplies']);

    $product = Product::factory()->for($distributor)->create([
        'name' => 'Oat Milk',
        'supplier_connection_id' => $connection->id,
        'country_of_origin' => null,
    ]);

    $this->actingAs($user);

    $page = visit(route('products.edit', [
        'current_organization' => $distributor->slug,
        'product' => $product->id,
    ]));

    expect($page->script(GUARD_ARMED))->toBeFalse();

    $page->script('document.querySelector("[data-test=product-country-of-origin]").scrollIntoView({block: "center"})');

    $page->click('@product-country-of-origin')
        ->click('@country-option-DE');

    expect($page->script(GUARD_ARMED))->toBeTrue();

    $page->assertNoJavaScriptErrors();
});

/**
 * Every answer the page gets -- an upload's included -- is followed by the
 * deferred panels loading again: a visit to the same product. The guard
 * took that for leaving, and asked in the middle of an upload.
 *
 * The browser test server does not pass files through, so the upload is
 * refused here. The answer and the reload after it are the same.
 */
test('uploading a document beside an unsaved edit does not ask to leave', function () {
    [$user, $distributor] = newOrganizationMember();
    $connection = newSupplierConnection($distributor, attributes: ['company_name' => 'Acme Supplies']);

    $product = Product::factory()->for($distributor)->create([
        'name' => 'Oat Milk',
        'supplier_connection_id' => $connection->id,
    ]);

    $file = sys_get_temp_dir().'/pest-documents/en71-certificate.pdf';
    @mkdir(dirname($file), 0777, true);
    file_put_contents($file, "%PDF-1.4\n% a file with a name and a size\n");

    $this->actingAs($user);

    $page = visit(route('products.edit', [
        'current_organization' => $distributor->slug,
        'product' => $product->id,
    ]));

    $page->script(RECORD_CONFIRMS);
    $page->fill('@product-name', 'Rice Milk');

    $page->click('@product-tab-documents')
        ->click('@upload-documents-button')
        ->attach('@document-file', $file)
        ->click('@pending-document-type-0')
        ->click('[role="option"]:has-text("Certificate")')
        ->click('@upload-document-submit');

    $page->wait(2);

    expect($page->script('window.confirmed'))->toBe([]);

    // Still the same edit, still unsaved, and still guarded.
    $page->click('@upload-documents-cancel')
        ->click('@product-tab-details')
        ->assertValue('@product-name', 'Rice Milk');

    expect($page->script(GUARD_ARMED))->toBeTrue();
});
