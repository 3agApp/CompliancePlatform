<?php

use App\Enums\OrganizationRole;
use App\Enums\ProductReviewStatus;
use App\Models\Organization;
use App\Models\Product;
use App\Models\User;

/**
 * A distributor, its supplier, and one product assigned to that trade.
 *
 * @return array{0: User, 1: Organization, 2: User, 3: Organization, 4: Product}
 */
function tradeWithLabelledProduct(): array
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

test('the code is handed over as a png', function () {
    [$user, $organization, , , $product] = tradeWithLabelledProduct();

    $response = $this
        ->actingAs($user)
        ->get(route('products.qr', ['current_organization' => $organization->slug, 'product' => $product->id, 'format' => 'png']))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png')
        ->assertHeader('X-Content-Type-Options', 'nosniff');

    $size = getimagesizefromstring($response->getContent());

    expect($size)->not->toBeFalse()
        ->and($size[0])->toBeGreaterThan(100)
        ->and($size[0])->toBe($size[1]);
});

test('the code is handed over as an svg that carries the public address', function () {
    [$user, $organization, , , $product] = tradeWithLabelledProduct();

    $response = $this
        ->actingAs($user)
        ->get(route('products.qr', ['current_organization' => $organization->slug, 'product' => $product->id, 'format' => 'svg']))
        ->assertOk();

    expect($response->headers->get('Content-Type'))->toStartWith('image/svg+xml')
        ->and($response->getContent())->toContain('<svg');
});

test('the code is offered under a name somebody can find again', function () {
    [$user, $organization, , , $product] = tradeWithLabelledProduct();

    $this
        ->actingAs($user)
        ->get(route('products.qr', ['current_organization' => $organization->slug, 'product' => $product->id, 'format' => 'png']))
        ->assertOk()
        ->assertHeader(
            'Content-Disposition',
            'inline; filename="magnetic-building-set-'.$product->uuid.'.png"',
        );
});

test('a format nobody can print is not an address', function () {
    [$user, $organization, , , $product] = tradeWithLabelledProduct();

    $this
        ->actingAs($user)
        ->get(url("/{$organization->slug}/products/{$product->id}/qr/gif"))
        ->assertNotFound();
});

test('the printable sheet is an a6 pdf naming the product', function () {
    [$user, $organization, , , $product] = tradeWithLabelledProduct();

    $product->update(['ean' => '7612345678900', 'internal_article_number' => 'ART-4711']);

    $response = $this
        ->actingAs($user)
        ->get(route('products.label', ['current_organization' => $organization->slug, 'product' => $product->id]))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');

    expect($response->headers->get('Content-Disposition'))
        ->toContain('attachment')
        ->toContain('magnetic-building-set-'.$product->uuid.'.pdf');

    $pdf = $response->getContent();

    expect($pdf)->toStartWith('%PDF-')
        /**
         * A6 is 105x148mm, which a PDF states in points: 297.64 x 419.53.
         * Asserted on the page box rather than on the text, because the
         * text is compressed inside the stream.
         */
        ->toMatch('/MediaBox\s*\[\s*0(\.\d+)?\s+0(\.\d+)?\s+297(\.\d+)?\s+419(\.\d+)?\s*\]/');
});

test('the supplier who fills the product in does not get the label artwork', function () {
    [, , $supplierUser, $supplier, $product] = tradeWithLabelledProduct();

    $this
        ->actingAs($supplierUser)
        ->get(route('products.qr', ['current_organization' => $supplier->slug, 'product' => $product->id, 'format' => 'png']))
        ->assertForbidden();

    $this
        ->actingAs($supplierUser)
        ->get(route('products.label', ['current_organization' => $supplier->slug, 'product' => $product->id]))
        ->assertForbidden();
});

test('a member of the distributor may take the artwork, and the product page offers it', function () {
    [, $organization, , , $product] = tradeWithLabelledProduct();

    [$member] = newOrganizationMember();
    $organization->members()->attach($member, ['role' => OrganizationRole::Member->value]);
    $member->switchOrganization($organization);

    $this
        ->actingAs($member)
        ->get(route('products.qr', ['current_organization' => $organization->slug, 'product' => $product->id, 'format' => 'png']))
        ->assertOk();
});

test('a stranger gets nothing', function () {
    [, $organization, , , $product] = tradeWithLabelledProduct();

    $this
        ->get(route('products.qr', ['current_organization' => $organization->slug, 'product' => $product->id, 'format' => 'png']))
        ->assertRedirect(route('login'));
});

test('the printed sheet claims nothing about where the check stands', function () {
    [, , , , $product] = tradeWithLabelledProduct();

    $product->forceFill(['review_status' => ProductReviewStatus::Approved, 'reviewed_at' => now()])->save();

    /**
     * Asserted on the view rather than on the rendered file, because a PDF
     * keeps its text compressed inside the stream and "not present" is not
     * a thing that can be read off it honestly.
     *
     * The product is approved here on purpose: this is the case where a
     * sheet would be most tempted to print a claim, and the one where a
     * printed claim would age worst -- the approval can be withdrawn the
     * same afternoon, and the sticker cannot.
     */
    $html = view('products.label', [
        'product' => $product->load('brand'),
        'url' => $product->publicUrl(),
        'qr' => 'data:image/png;base64,AAAA',
    ])->render();

    expect($html)
        ->toContain($product->name)
        ->toContain($product->publicUrl())
        ->not->toContain('Verified')
        ->not->toContain('Swiss compliance')
        ->not->toContain('In progress');
});

test('the sheet carries the numbers printed on the box', function () {
    [, , , , $product] = tradeWithLabelledProduct();

    $product->update(['ean' => '7612345678900', 'internal_article_number' => 'ART-4711']);

    $html = view('products.label', [
        'product' => $product->fresh()->load('brand'),
        'url' => $product->publicUrl(),
        'qr' => 'data:image/png;base64,AAAA',
    ])->render();

    expect($html)
        ->toContain('7612345678900')
        ->toContain('ART-4711')
        ->toContain('Scan for current compliance information');
});
