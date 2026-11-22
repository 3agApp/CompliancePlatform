<?php

use App\Support\Watermark;
use App\Support\WatermarkStamp;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * A rough width for a text: every character is as wide as half the size.
 */
function roughWidth(string $text, float $size): float
{
    return mb_strlen($text) * $size * 0.5;
}

test('a PDF page gets one stamp by default', function () {
    $stamp = new WatermarkStamp(595.0, 842.0, config('watermark.pdf'));

    expect($stamp->placements('swiss-product-seal.ch', roughWidth(...), new Randomizer(new Mt19937(7))))->toHaveCount(1);
});

test('more stamps take one band each, each wholly inside its band', function () {
    $stamp = new WatermarkStamp(595.0, 842.0, [...config('watermark.pdf'), 'stamps' => 3]);

    $placements = $stamp->placements('swiss-product-seal.ch', roughWidth(...), new Randomizer(new Mt19937(7)));

    expect($placements)->toHaveCount(3);

    foreach ($placements as $band => $placement) {
        $width = roughWidth($placement['text'], $placement['fontSize']);
        [$boxWidth, $boxHeight] = $stamp->boundingBox($width, $placement['fontSize'], $placement['angleDegrees'], $placement['curve']['amplitude']);
        $bandHeight = 842.0 / 3;

        expect(abs($placement['angleDegrees']))->toBeGreaterThanOrEqual(15.0)->toBeLessThanOrEqual(65.0)
            ->and($placement['x'] - $boxWidth / 2)->toBeGreaterThanOrEqual(-0.01)
            ->and($placement['x'] + $boxWidth / 2)->toBeLessThanOrEqual(595.01)
            ->and($placement['y'] - $boxHeight / 2)->toBeGreaterThanOrEqual($band * $bandHeight - 0.01)
            ->and($placement['y'] + $boxHeight / 2)->toBeLessThanOrEqual(($band + 1) * $bandHeight + 0.01);
    }
});

test('a stamp bends its letters along a wave rather than a straight line', function () {
    $stamp = new WatermarkStamp(2000.0, 1200.0, config('watermark.image'));

    $glyphs = $stamp->glyphsFor('swiss-product-seal.ch', roughWidth(...), new Randomizer(new Mt19937(11)));
    $angles = array_map(fn (array $glyph) => round($glyph['angleDegrees'], 1), $glyphs);

    /** One stamp on a picture, every letter drawn, at more than one angle. */
    expect($glyphs)->toHaveCount(mb_strlen('swiss-product-seal.ch'))
        ->and(array_unique($angles))->not->toHaveCount(1);

    foreach ($glyphs as $glyph) {
        expect($glyph['x'])->toBeGreaterThan(0.0)->toBeLessThan(2000.0)
            ->and($glyph['y'])->toBeGreaterThan(0.0)->toBeLessThan(1200.0);
    }
});

test('two copies of the same file are stamped in different places', function () {
    $stamp = new WatermarkStamp(595.0, 842.0, config('watermark.pdf'));

    $first = $stamp->placements('swiss-product-seal.ch', roughWidth(...), new Randomizer(new Mt19937(1)));
    $second = $stamp->placements('swiss-product-seal.ch', roughWidth(...), new Randomizer(new Mt19937(2)));

    expect($first)->not->toBe($second);
});

test('the logo sits wholly on the page, a little tilted', function (int $seed) {
    $stamp = new WatermarkStamp(595.0, 842.0, config('watermark.pdf'));

    $logo = $stamp->logoPlacement(1098 / 253, new Randomizer(new Mt19937($seed)));

    $radians = deg2rad($logo['angleDegrees']);
    $reachX = (($logo['width'] * abs(cos($radians))) + ($logo['height'] * abs(sin($radians)))) / 2;
    $reachY = (($logo['width'] * abs(sin($radians))) + ($logo['height'] * abs(cos($radians)))) / 2;

    expect($logo['width'])->toEqualWithDelta(595.0 * config('watermark.pdf.logo_width_ratio'), 0.001)
        ->and($logo['width'] / $logo['height'])->toEqualWithDelta(1098 / 253, 0.001)
        ->and(abs($logo['angleDegrees']))->toBeLessThanOrEqual(config('watermark.logo.max_angle_degrees'))
        ->and($logo['x'] - $reachX)->toBeGreaterThanOrEqual(-0.001)
        ->and($logo['x'] + $reachX)->toBeLessThanOrEqual(595.001)
        ->and($logo['y'] - $reachY)->toBeGreaterThanOrEqual(-0.001)
        ->and($logo['y'] + $reachY)->toBeLessThanOrEqual(842.001);
})->with([1, 2, 3, 4, 5]);

test('on an area too narrow for it, the tilted logo is made smaller to fit', function () {
    $stamp = new WatermarkStamp(200.0, 1000.0, [...config('watermark.image'), 'logo_width_ratio' => 1.0]);

    $logo = $stamp->logoPlacement(1098 / 253, new Randomizer(new Mt19937(3)));

    $radians = deg2rad($logo['angleDegrees']);

    expect(($logo['width'] * abs(cos($radians))) + ($logo['height'] * abs(sin($radians))))->toBeLessThanOrEqual(200.001)
        ->and($logo['width'])->toBeLessThan(200.0);
});

test('two copies carry the logo in different places', function () {
    $stamp = new WatermarkStamp(595.0, 842.0, config('watermark.pdf'));

    expect($stamp->logoPlacement(1098 / 253, new Randomizer(new Mt19937(1))))
        ->not->toBe($stamp->logoPlacement(1098 / 253, new Randomizer(new Mt19937(2))));
});

test('there is no watermark text beside the logo unless one is configured', function () {
    config(['watermark.text' => null]);

    expect(Watermark::text())->toBe('');

    config(['watermark.text' => 'Example AG']);

    expect(Watermark::text())->toBe('Example AG');
});
