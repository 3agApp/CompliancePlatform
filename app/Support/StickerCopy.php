<?php

namespace App\Support;

use App\Models\Product;
use Dompdf\FontMetrics;

/**
 * The brand and product name printed on a sticker, fitted to the lines the
 * sticker keeps for them.
 *
 * Names run from "Dinos Set (5-teilig)" to eighty-odd characters, and a
 * guess by character count either cuts short names that would have fit or
 * lets long ones spill. So the words are measured in the font the PDF
 * prints them in and laid out the way the renderer will wrap them, and the
 * name is shortened on a whole word until brand and name fit.
 */
class StickerCopy
{
    /**
     * The font both are set in, as the label view names it.
     */
    private const string FAMILY = 'DejaVu Sans';

    /**
     * @param  float  $width  The width of a line, in points.
     * @param  float  $size  The type size, in points.
     */
    public function __construct(
        private FontMetrics $metrics,
        private float $width,
        private float $size,
        private int $lines = 2,
    ) {}

    /**
     * Get the brand, in the capitals it is printed in, and the name that
     * fits after it.
     *
     * Product names often open with their brand ("MAGNA-TILES® Dolphin Bay
     * ..."). The brand is printed ahead of the name already, so it is taken
     * off the front of the name rather than printed twice.
     *
     * @return array{brand: string|null, name: string}
     */
    public function for(Product $product): array
    {
        $brand = $product->brand?->name;
        $name = $product->name;

        if ($brand !== null) {
            $name = preg_replace('/^'.preg_quote($brand, '/').'[\s®™:–-]*/iu', '', $name) ?: $name;
            $brand = mb_strtoupper($brand);
        }

        return ['brand' => $brand, 'name' => $this->fit($brand, $name)];
    }

    /**
     * Shorten the name, a word at a time, until it fits after the brand.
     */
    private function fit(?string $brand, string $name): string
    {
        $words = preg_split('/\s+/u', trim($name), flags: PREG_SPLIT_NO_EMPTY) ?: [];

        if ($this->fits($brand, $words)) {
            return implode(' ', $words);
        }

        while (count($words) > 1) {
            array_pop($words);

            $shortened = $words;
            $shortened[] = rtrim(array_pop($shortened), ':;,.-–').'...';

            if ($this->fits($brand, $shortened)) {
                return implode(' ', $shortened);
            }
        }

        return ($words[0] ?? '').'...';
    }

    /**
     * Whether the brand and these words wrap onto the lines there are.
     *
     * Wraps the way the renderer does: a word goes onto the line if it fits
     * there, and otherwise starts the next one.
     *
     * @param  list<string>  $words
     */
    private function fits(?string $brand, array $words): bool
    {
        $bold = $this->metrics->getFont(self::FAMILY, 'bold');
        $normal = $this->metrics->getFont(self::FAMILY);

        $brandWords = $brand === null ? [] : (preg_split('/\s+/u', $brand, flags: PREG_SPLIT_NO_EMPTY) ?: []);

        $tokens = [
            ...array_map(fn (string $word): array => [$word, $bold], $brandWords),
            ...array_map(fn (string $word): array => [$word, $normal], $words),
        ];

        $space = $this->metrics->getTextWidth(' ', $normal, $this->size);
        $line = 1;
        $used = 0.0;

        foreach ($tokens as [$word, $font]) {
            $width = $this->metrics->getTextWidth($word, $font, $this->size);
            $needed = $used === 0.0 ? $width : $used + $space + $width;

            if ($needed <= $this->width) {
                $used = $needed;

                continue;
            }

            $line++;
            $used = $width;

            if ($line > $this->lines || $width > $this->width) {
                return false;
            }
        }

        return true;
    }
}
