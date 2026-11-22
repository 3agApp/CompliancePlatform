<?php

use App\Models\Brand;
use App\Models\Product;
use App\Support\StickerCopy;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * The brand and name a product's stickers would carry, fitted to two lines
 * of the sticker's 21 mm text column at 5.3 pt.
 *
 * @return array{brand: string|null, name: string}
 */
function stickerCopy(string $name, ?string $brand = null): array
{
    $product = new Product(['name' => $name]);
    $product->setRelation('brand', $brand === null ? null : new Brand(['name' => $brand]));

    $copy = new StickerCopy(Pdf::getDomPDF()->getFontMetrics(), 21 * 72 / 25.4, 5.3);

    return $copy->for($product);
}

test('a name that fits is printed whole, after the brand in capitals', function () {
    expect(stickerCopy('Dinos Set (5-teilig)', 'Magna-Tiles'))
        ->toBe(['brand' => 'MAGNA-TILES', 'name' => 'Dinos Set (5-teilig)']);
});

test('a name that opens with its brand does not print the brand twice', function (string $name) {
    expect(stickerCopy($name, 'Magna-Tiles')['name'])->toBe('Dog House - 13 Teile');
})->with([
    'trademark sign' => 'MAGNA-TILES® Dog House - 13 Teile',
    'plain' => 'Magna-Tiles Dog House - 13 Teile',
    'colon' => 'Magna-Tiles: Dog House - 13 Teile',
]);

test('a long name is cut on a whole word, without trailing punctuation', function () {
    $copy = stickerCopy('MAGNA-TILES® Camp Out 16-teilig: Magnetischer Bauspass für Abenteurer', 'Magna-Tiles');

    expect($copy['name'])->toBe('Camp Out 16-teilig...');
});

test('a product without a brand keeps both lines for its name', function () {
    expect(stickerCopy('Magnetic Building Set Deluxe 120 Pieces with Storage Box'))
        ->toBe(['brand' => null, 'name' => 'Magnetic Building Set Deluxe 120 Pieces...']);
});
