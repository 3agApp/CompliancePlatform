<?php

namespace App\Support;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\PngWriter;

/**
 * The square printed on each box, leading to that box's serial.
 */
class QrCode
{
    /**
     * Draw a code for an address as a data address, for embedding in a page
     * that is about to be printed rather than served.
     *
     * Medium correction, which recovers a code from roughly a sixth of it
     * being scuffed, torn or covered -- the ordinary fate of anything
     * printed on a box -- without making the square dense enough to need a
     * bigger label. No margin: the label lays out its own quiet zone.
     */
    public static function dataUri(string $url, int $size): string
    {
        return (new Builder(
            writer: new PngWriter,
            data: $url,
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: $size,
            margin: 0,
        ))->build()->getDataUri();
    }
}
