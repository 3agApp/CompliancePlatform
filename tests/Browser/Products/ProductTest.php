<?php

use App\Enums\CountryOfOrigin;
use App\Enums\OrganizationRole;
use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\SupplierConnection;
use Illuminate\Support\Facades\Notification;

test('a product is created through the new product dialog', function () {
    [$user, $organization] = newOrganizationMember();
    newSupplierConnection($organization, attributes: ['company_name' => 'Acme Supplies']);
    $organization->brands()->create(['name' => 'Magna-Tiles']);

    $this->actingAs($user);

    $page = visit(route('products.index', ['current_organization' => $organization->slug]));

    $page->assertSee('No products yet')
        ->click('@products-new-product-button')
        ->assertSee('Add a product')
        ->fill('@product-name', 'Organic Oat Milk')
        ->click('@product-brand')
        ->click('[role="option"]:has-text("Magna-Tiles")')
        ->click('@product-category')
        ->click('[role="option"]:has-text("Magnetic toy")')
        ->fill('@product-internal-article-number', 'ART-10294')
        ->fill('@product-supplier-article-number', 'MT-BLUE-32')
        ->fill('@product-ean', '4006381333931')
        ->fill('@product-order-number', 'PO-2026-0148')
        ->fill('@product-customs-tariff-number', '9503.00.75')
        ->click('@product-country-of-origin')
        ->click('[role="option"]:has-text("Germany")')
        ->click('@product-supplier')
        ->click('[role="option"]:has-text("Acme Supplies")')
        ->click('@create-product-submit')
        ->assertDontSee('Add a product')
        ->assertSee('Organic Oat Milk')
        ->assertSee('Magna-Tiles')
        ->assertSee('Magnetic toy')
        ->assertSee('ART-10294')
        ->assertSee('4006381333931')
        ->assertNoJavaScriptErrors();

    expect(Product::sole())
        ->name->toBe('Organic Oat Milk')
        ->brand_id->toBe(Brand::query()->where('name', 'Magna-Tiles')->value('id'))
        ->product_category_id->toBe(ProductCategory::query()->where('name', 'Magnetic toy')->value('id'))
        ->ean->toBe('4006381333931')
        ->internal_article_number->toBe('ART-10294')
        ->supplier_article_number->toBe('MT-BLUE-32')
        ->order_number->toBe('PO-2026-0148')
        ->customs_tariff_number->toBe('95030075')
        ->country_of_origin->toBe(CountryOfOrigin::Germany)
        ->organization_id->toBe($organization->id)
        ->supplier_connection_id->toBe(SupplierConnection::sole()->id);
});

test('the identification details of a product are edited on its own page', function () {
    [$user, $organization] = newOrganizationMember();
    $connection = newSupplierConnection($organization, attributes: ['company_name' => 'Acme Supplies']);
    $organization->brands()->create(['name' => 'Magna-Tiles']);

    $product = Product::factory()->for($organization)->withoutOptionalDetails()->create([
        'name' => 'Organic Oat Milk',
        'supplier_connection_id' => $connection->id,
    ]);

    $this->actingAs($user);

    $page = visit(route('products.edit', [
        'current_organization' => $organization->slug,
        'product' => $product->id,
    ]));

    $page->click('@product-brand')
        ->click('[role="option"]:has-text("Magna-Tiles")')
        ->click('@product-category')
        ->click('[role="option"]:has-text("Magnetic toy")')
        ->fill('@product-internal-article-number', 'ART-10294')
        ->fill('@product-supplier-article-number', 'MT-BLUE-32')
        ->fill('@product-order-number', 'PO-2026-0148')
        ->fill('@product-customs-tariff-number', '9503.00.75')
        ->click('@update-product-submit')
        ->assertSee('Product updated.')
        ->assertNoJavaScriptErrors();

    expect($product->fresh())
        ->brand_id->toBe(Brand::query()->where('name', 'Magna-Tiles')->value('id'))
        ->product_category_id->toBe(ProductCategory::query()->where('name', 'Magnetic toy')->value('id'))
        ->internal_article_number->toBe('ART-10294')
        ->supplier_article_number->toBe('MT-BLUE-32')
        ->order_number->toBe('PO-2026-0148')
        ->customs_tariff_number->toBe('95030075');
});

test('the new product dialog stays open and shows the validation message for an invalid barcode', function () {
    [$user, $organization] = newOrganizationMember();
    newSupplierConnection($organization, attributes: ['company_name' => 'Acme Supplies']);

    $this->actingAs($user);

    $page = visit(route('products.index', ['current_organization' => $organization->slug]));

    $page->click('@products-new-product-button')
        ->fill('@product-name', 'Organic Oat Milk')
        ->fill('@product-ean', '123')
        ->click('@product-supplier')
        ->click('[role="option"]:has-text("Acme Supplies")')
        ->click('@create-product-submit')
        ->assertSee('Add a product')
        ->assertSee('The EAN/barcode must be 8, 12, 13, or 14 digits.')
        ->assertNoJavaScriptErrors();

    $this->assertDatabaseCount('products', 0);
});

test('a product is deleted through the confirmation dialog', function () {
    [$user, $organization] = newOrganizationMember();
    $connection = newSupplierConnection($organization);
    $product = Product::factory()->for($organization)->create([
        'name' => 'Organic Oat Milk',
        'supplier_connection_id' => $connection->id,
    ]);

    $this->actingAs($user);

    $page = visit(route('products.index', ['current_organization' => $organization->slug]));

    $page->click('@product-delete-button')
        ->assertSee('This action cannot be undone.')
        ->click('@delete-product-confirm')
        ->assertSee('No products yet')
        ->assertNoJavaScriptErrors();

    $this->assertModelMissing($product);
});

test('a member sees the product list without the create and delete controls', function () {
    [$user, $organization] = newOrganizationMember(OrganizationRole::Member);
    $connection = newSupplierConnection($organization);
    Product::factory()->for($organization)->create([
        'name' => 'Organic Oat Milk',
        'supplier_connection_id' => $connection->id,
    ]);

    $this->actingAs($user);

    visit(route('products.index', ['current_organization' => $organization->slug]))
        ->assertSee('Organic Oat Milk')
        ->assertMissing('@products-new-product-button')
        ->assertMissing('@product-delete-button')
        ->assertPresent('@product-edit-button')
        ->assertNoJavaScriptErrors();
});

test('a supplier invited from the products page can be assigned without a refresh', function () {
    Notification::fake();

    [$user, $distributor] = newOrganizationMember();

    $this->actingAs($user);

    $page = visit(route('products.index', ['current_organization' => $distributor->slug]));

    $page->assertPresent('@products-invite-supplier-button')
        ->assertMissing('@products-new-product-button')
        ->click('@products-invite-supplier-button')
        ->assertSee('No suppliers yet')
        /**
         * Poison the cache the way a real mouse does on the way to the invite
         * button: the sidebar links prefetch on hover and hold the response
         * for 30 seconds. Inertia refuses to prefetch the page it is already
         * on, so this only works from another page — which is exactly where
         * the user is when they invite. Without it the click below would send
         * a fresh request and the test would pass either way.
         */
        ->hover('@nav-products')
        ->wait(1.5)
        ->click('@invite-supplier-button')
        ->fill('@supplier-company-name', 'Acme Supplies AG')
        ->fill('@supplier-contact-email', 'compliance@acme.test')
        ->click('@invite-supplier-submit')
        ->assertSee('Acme Supplies AG')
        /**
         * Back to products the way the user does it: a sidebar click, with no
         * reload. The invitation is still pending, so this covers both that
         * the stale copy was dropped and that a pending supplier is offered.
         */
        ->click('@nav-products')
        ->assertPresent('@products-new-product-button')
        ->assertMissing('@products-invite-supplier-button')
        ->click('@products-new-product-button')
        ->click('@product-supplier')
        ->assertSee('Acme Supplies AG (invitation pending)')
        ->assertNoJavaScriptErrors();
});
