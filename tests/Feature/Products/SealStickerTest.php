<?php

use App\Enums\OrganizationRole;
use App\Models\Product;
use Inertia\Testing\AssertableInertia as Assert;

test('the seal prints on A4 sheets of HERMA round 40 mm labels, at actual size', function () {
    [$user, $organization] = newOrganizationMember();

    $response = $this
        ->actingAs($user)
        ->get(route('seal-stickers.pdf', ['current_organization' => $organization->slug, 'sheets' => 3]))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition', 'inline; filename=swiss-product-seal-stickers.pdf');

    $pdf = $response->getContent();

    /**
     * A4 is 595.28 x 841.89 points. Pages are counted on the page objects,
     * because the content is compressed inside the stream.
     */
    expect($pdf)->toStartWith('%PDF-')
        ->toMatch('/MediaBox\s*\[\s*0(\.\d+)?\s+0(\.\d+)?\s+595(\.\d+)?\s+841(\.\d+)?\s*\]/')
        ->and(preg_match_all('/\/Type\s*\/Page[^s]/', $pdf))->toBe(3)
        /** The artwork is embedded once however many seals it is drawn as. */
        ->and(preg_match_all('/\/Subtype\s*\/Image/', $pdf))->toBeLessThanOrEqual(2)
        /** Printed at actual size, or the seals shrink off the die-cut circles. */
        ->and($pdf)->toMatch('/\/PrintScaling\s*\/None/');
});

test('one sheet is printed when no number is given', function () {
    [$user, $organization] = newOrganizationMember();

    $pdf = $this
        ->actingAs($user)
        ->get(route('seal-stickers.pdf', ['current_organization' => $organization->slug]))
        ->assertOk()
        ->getContent();

    expect(preg_match_all('/\/Type\s*\/Page[^s]/', $pdf))->toBe(1);
});

test('a print is between one sheet and the most there may be', function (int $sheets) {
    [$user, $organization] = newOrganizationMember();

    $this
        ->actingAs($user)
        ->get(route('seal-stickers.pdf', ['current_organization' => $organization->slug, 'sheets' => $sheets]))
        ->assertSessionHasErrors('sheets');
})->with([0, 21]);

test('the sheet centres each seal on its die-cut circle, a little over the cut', function () {
    $html = view('seal-stickers', [
        'grid' => config('labels.seal_sheet'),
        'sheets' => 1,
        'seal' => public_path('brand/siegel_farbig_hell.png'),
    ])->render();

    /**
     * 16 mm in and 13.5 mm down, a 40 mm circle every 46 mm, less the
     * 0.4 mm the artwork runs past the cut all round.
     */
    expect(substr_count($html, 'class="seal"'))->toBe(24)
        ->and($html)->toContain('width: 40.8mm;')
        ->toContain('left: 15.6mm; top: 13.1mm;')
        ->toContain('left: 61.6mm; top: 13.1mm;')
        ->toContain('left: 153.6mm; top: 13.1mm;')
        ->toContain('left: 15.6mm; top: 59.1mm;')
        ->toContain('left: 153.6mm; top: 243.1mm;');
});

test('the sheets fill the page as HERMA lays it out, margin to margin', function () {
    $grid = config('labels.seal_sheet');

    $width = 2 * $grid['left'] + $grid['columns'] * $grid['diameter'] + ($grid['columns'] - 1) * $grid['gap'];
    $height = 2 * $grid['top'] + $grid['rows'] * $grid['diameter'] + ($grid['rows'] - 1) * $grid['gap'];

    expect($width)->toBe(210.0)->and($height)->toBe(297.0);
});

test('the page seals are printed from opens for a distributor\'s owners and admins', function (OrganizationRole $role) {
    [$user, $organization] = newOrganizationMember($role);

    $this
        ->actingAs($user)
        ->get(route('seal-stickers.index', ['current_organization' => $organization->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('seal-stickers/index')
            ->where('perSheet', 24)
            ->where('maxSheets', 20));
})->with([OrganizationRole::Owner, OrganizationRole::Admin]);

test('a member who cannot work on products cannot print seals', function () {
    [$user, $organization] = newOrganizationMember(OrganizationRole::Member);

    $this->actingAs($user)
        ->get(route('seal-stickers.index', ['current_organization' => $organization->slug]))
        ->assertForbidden();

    $this->actingAs($user)
        ->get(route('seal-stickers.pdf', ['current_organization' => $organization->slug]))
        ->assertForbidden();
});

test('a supplier cannot print the distributor\'s seals', function () {
    [$user, $supplier] = newSupplierMember();

    $this->actingAs($user)
        ->get(route('seal-stickers.index', ['current_organization' => $supplier->slug]))
        ->assertForbidden();

    $this->actingAs($user)
        ->get(route('seal-stickers.pdf', ['current_organization' => $supplier->slug]))
        ->assertForbidden();
});

test('the product page knows how seals are printed', function () {
    [$user, $organization] = newOrganizationMember();
    $connection = newSupplierConnection($organization);

    $product = Product::factory()->for($organization)->create([
        'supplier_connection_id' => $connection->id,
    ]);

    $this
        ->actingAs($user)
        ->get(route('products.edit', ['current_organization' => $organization->slug, 'product' => $product->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('sealStickers.perSheet', 24)
            ->where('sealStickers.maxSheets', 20));
});
