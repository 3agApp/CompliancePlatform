<?php

use App\Models\Product;

/**
 * The search opens from anywhere with the keyboard, finds a product by its
 * barcode, and goes to it on Enter.
 */
test('a product is found by its barcode from the search box and opened with the keyboard', function () {
    [$user, $distributor] = newOrganizationMember();

    Product::factory()->for($distributor)->create([
        'name' => 'Wooden Train',
        'ean' => '4006381333931',
        'supplier_connection_id' => newSupplierConnection($distributor)->id,
    ]);

    $this->actingAs($user);

    visit(route('dashboard', ['current_organization' => $distributor->slug]))
        ->click('@command-search-trigger')
        ->fill('@command-search-input', '4006381')
        ->assertSeeIn('@command-search', 'Wooden Train')
        ->keys('@command-search-input', 'Enter')
        ->assertSee('Wooden Train')
        ->assertPathContains('/products/')
        ->assertNoJavaScriptErrors();
});
