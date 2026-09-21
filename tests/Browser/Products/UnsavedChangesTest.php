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
