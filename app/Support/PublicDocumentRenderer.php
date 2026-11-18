<?php

namespace App\Support;

use App\Enums\DocumentPreviewKind;
use App\Models\ProductDocument;
use GdImage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Turns a document released to the public page into watermarked pictures.
 *
 * The public never receives the file a distributor uploaded. A PDF is drawn
 * page by page into images and a picture is redrawn. Either way the
 * importer's name is stamped across the result, so a page lifted off the
 * site and passed off elsewhere still says where it came from, and there
 * is no clean original to strip the mark from.
 *
 * Each page is drawn once, on first request, and kept beside the document
 * on the private disk. The watermark is part of the cache key, so renaming
 * the organization draws the pages again rather than serving the old name.
 */
class PublicDocumentRenderer
{
    /**
     * The most pages a reader is shown. A manual longer than this is cut
     * short rather than drawn in full for anybody who asks.
     */
    public const int MAX_PAGES = 40;

    /**
     * Dots per inch a PDF page is drawn at: enough to read the small print
     * of a test report on a phone, and no sharper than that.
     */
    public const int RESOLUTION = 110;

    /**
     * The longest edge, in pixels, any page is kept at.
     */
    public const int MAX_EDGE = 1800;

    /**
     * How long poppler may take over one page before it is given up on.
     */
    public const int TIMEOUT_SECONDS = 30;

    /**
     * Count the pages the reader can be shown.
     *
     * A picture is a single page. A PDF is asked once and remembered, since
     * the file behind a document never changes: a new file is a new row.
     */
    public function pageCount(ProductDocument $document): int
    {
        if ($document->previewKind() === DocumentPreviewKind::Image) {
            return 1;
        }

        return Cache::rememberForever(self::pageCountKey($document), function () use ($document): int {
            $result = Process::timeout(self::TIMEOUT_SECONDS)
                ->run(['pdfinfo', Storage::disk(ProductDocument::DISK)->path($document->path)]);

            if ($result->failed() || preg_match('/^Pages:\s+(\d+)/m', $result->output(), $matches) !== 1) {
                throw new RuntimeException("Could not read the page count of document {$document->id}: {$result->errorOutput()}");
            }

            return max(1, min((int) $matches[1], self::MAX_PAGES));
        });
    }

    /**
     * Get the path on the private disk of one watermarked page, drawing it
     * the first time it is asked for.
     */
    public function page(ProductDocument $document, int $page, string $watermark): string
    {
        $disk = Storage::disk(ProductDocument::DISK);
        $path = self::directoryFor($document).'/'.substr(hash('sha256', $watermark), 0, 16)."/{$page}.webp";

        if ($disk->exists($path)) {
            return $path;
        }

        $image = $document->previewKind() === DocumentPreviewKind::Image
            ? $this->loadImage($document)
            : $this->rasterise($document, $page);

        /**
         * A PNG or GIF drawn with a palette cannot be written as WebP, and
         * a palette has no room for a translucent watermark either.
         */
        imagepalettetotruecolor($image);

        $image = $this->fit($image);
        $this->stamp($image, $watermark);

        /**
         * Written through a temporary file rather than an output buffer,
         * so a server that buffers its own output never gets the bytes.
         */
        $temporary = tempnam(sys_get_temp_dir(), 'public-webp-');

        if ($temporary === false || ! imagewebp($image, $temporary, 82)) {
            throw new RuntimeException("Could not write page {$page} of document {$document->id}.");
        }

        try {
            $disk->put($path, (string) file_get_contents($temporary));
        } finally {
            @unlink($temporary);
        }

        return $path;
    }

    /**
     * Forget every page drawn from a document.
     */
    public static function clear(ProductDocument $document): void
    {
        Storage::disk(ProductDocument::DISK)->deleteDirectory(self::directoryFor($document));
        Cache::forget(self::pageCountKey($document));
    }

    /**
     * The directory a document's drawn pages are kept in.
     *
     * Under the product's own directory, so removing a product's files
     * takes these with them.
     */
    public static function directoryFor(ProductDocument $document): string
    {
        return "product-documents/{$document->product_id}/public-pages/{$document->id}";
    }

    /**
     * The cache key a document's page count is remembered under.
     */
    protected static function pageCountKey(ProductDocument $document): string
    {
        return "public-document-pages:{$document->id}";
    }

    /**
     * Draw one page of a PDF with poppler.
     */
    protected function rasterise(ProductDocument $document, int $page): GdImage
    {
        $output = tempnam(sys_get_temp_dir(), 'public-page-');

        if ($output === false) {
            throw new RuntimeException('Could not create a temporary file to draw a page into.');
        }

        try {
            $result = Process::timeout(self::TIMEOUT_SECONDS)->run([
                'pdftoppm',
                '-f', (string) $page,
                '-l', (string) $page,
                '-r', (string) self::RESOLUTION,
                '-png',
                '-singlefile',
                Storage::disk(ProductDocument::DISK)->path($document->path),
                $output,
            ]);

            $image = $result->successful() && is_file("{$output}.png")
                ? @imagecreatefrompng("{$output}.png")
                : false;

            if ($image === false) {
                throw new RuntimeException("Could not draw page {$page} of document {$document->id}: {$result->errorOutput()}");
            }

            return $image;
        } finally {
            @unlink($output);
            @unlink("{$output}.png");
        }
    }

    /**
     * Read a picture off the private disk.
     */
    protected function loadImage(ProductDocument $document): GdImage
    {
        $image = @imagecreatefromstring((string) Storage::disk(ProductDocument::DISK)->get($document->path));

        if ($image === false) {
            throw new RuntimeException("Could not read the picture of document {$document->id}.");
        }

        return $image;
    }

    /**
     * Shrink a page whose longest edge is past the limit.
     */
    protected function fit(GdImage $image): GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $longest = max($width, $height);

        if ($longest <= self::MAX_EDGE) {
            return $image;
        }

        $scale = self::MAX_EDGE / $longest;
        $resized = imagescale($image, max(1, (int) round($width * $scale)), max(1, (int) round($height * $scale)));

        return $resized === false ? $image : $resized;
    }

    /**
     * Tile the watermark diagonally across the whole page.
     *
     * Faint enough that the page underneath stays readable, and repeated
     * across all of it so cropping one corner off does not remove it.
     */
    protected function stamp(GdImage $image, string $watermark): void
    {
        $font = base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSans-Bold.ttf');

        if (! is_file($font)) {
            throw new RuntimeException('The watermark font is missing.');
        }

        imagealphablending($image, true);

        $width = imagesx($image);
        $height = imagesy($image);
        $size = max(10, (int) round(min($width, $height) / 38));
        $colour = imagecolorallocatealpha($image, 120, 120, 120, 96);

        if ($colour === false) {
            throw new RuntimeException('Could not allocate the watermark colour.');
        }
        $angle = 30;

        $box = imagettfbbox($size, 0, $font, $watermark);
        $textWidth = $box === false ? $size * strlen($watermark) : (int) abs($box[2] - $box[0]);
        $stepX = $textWidth + $size * 4;
        $stepY = $size * 7;

        for ($row = 0, $y = -$height; $y < $height * 2; $row++, $y += $stepY) {
            $offset = ($row % 2) * intdiv($stepX, 2);

            for ($x = -$width; $x < $width * 2; $x += $stepX) {
                imagettftext($image, $size, $angle, $x + $offset, $y, $colour, $font, $watermark);
            }
        }
    }
}
