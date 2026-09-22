<?php

namespace App\Support;

use App\Models\Product;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Writer\Result\ResultInterface;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Support\Str;

/**
 * The square that takes a reader from the packet in their hand to the
 * product's public page.
 *
 * It encodes the public address and nothing else -- no tracking parameter,
 * no campaign tag -- because the code is printed on something that outlives
 * every decision we might make about it, and a link that has to keep
 * working for the life of a product should carry as little as possible.
 */
class ProductQrCode
{
    /**
     * How wide the square is drawn, before its margin.
     *
     * Generous on purpose: this is artwork, meant to be dropped into a label
     * and scaled down by whoever lays it out. Scaling a large code down
     * keeps its edges; scaling a small one up does not.
     */
    public const int SIZE = 600;

    /**
     * The quiet zone around the code, without which a scanner reading it off
     * a busy label may not find its edges at all.
     */
    public const int MARGIN = 16;

    /**
     * Render the product's code as a PNG.
     */
    public static function png(Product $product): ResultInterface
    {
        return self::build($product, new PngWriter);
    }

    /**
     * Render the product's code as an SVG.
     */
    public static function svg(Product $product): ResultInterface
    {
        return self::build($product, new SvgWriter);
    }

    /**
     * Render the code as a data address, for embedding in a page that is
     * about to be printed rather than served.
     */
    public static function dataUri(Product $product): string
    {
        return self::png($product)->getDataUri();
    }

    /**
     * Get the name the downloaded file is offered under.
     *
     * Named after the product rather than its id, because it is about to
     * land in somebody's downloads folder beside a hundred others. The uuid
     * rides along so two products with the same name stay apart, and
     * nothing but what a filename may safely hold survives the slug.
     */
    public static function filename(Product $product, string $extension): string
    {
        $name = Str::slug($product->name);

        if ($name === '') {
            $name = 'product';
        }

        return "{$name}-{$product->uuid}.{$extension}";
    }

    /**
     * Draw the code.
     *
     * Medium correction, which recovers a code from roughly a sixth of it
     * being scuffed, torn or covered -- the ordinary fate of anything
     * printed on a box -- without making the square dense enough to need a
     * bigger label.
     */
    protected static function build(Product $product, PngWriter|SvgWriter $writer): ResultInterface
    {
        return (new Builder(
            writer: $writer,
            data: $product->publicUrl(),
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: self::SIZE,
            margin: self::MARGIN,
        ))->build();
    }
}
