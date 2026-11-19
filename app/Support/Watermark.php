<?php

namespace App\Support;

use App\Enums\DocumentPreviewKind;
use App\Models\Product;
use App\Models\ProductDocument;
use GdImage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Random\Randomizer;
use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Throwable;

/**
 * The copy of a picture or a PDF that the public page hands out.
 *
 * Whatever leaves the public page carries the site's domain across it, so
 * a copy passed along still says where it came from. A picture gets one
 * stamp; a PDF page gets several, scattered at random. Every stamp is
 * tilted at random and bent along a random wave (see WatermarkStamp). The
 * original on the private disk is never touched, and those signed in keep
 * getting it clean.
 *
 * A copy is made the first time somebody asks for it and kept beside the
 * product's other files, so every later reader is served bytes already on
 * disk. Its name carries a hash of what was stamped on it: change the text
 * or the look, and the next reader gets a fresh copy.
 */
class Watermark
{
    /**
     * Raised whenever the look of the stamp changes, so every copy made
     * under the old look is made again.
     */
    public const int VERSION = 2;

    /**
     * How long a stamp on a PDF may take before it is given up on.
     */
    protected const int PDF_TIMEOUT_SECONDS = 60;

    /**
     * The widths of Helvetica Bold's glyphs, in thousandths of the font
     * size, by character code.
     *
     * @var array<int, int>|null
     */
    protected static ?array $helveticaWidths = null;

    /**
     * Get the path of the stamped copy of a document, making it first if
     * nobody has asked for it yet.
     *
     * Null when there is nothing to stamp -- the original is gone, or is
     * not a picture or a PDF -- or when stamping it failed. Never the
     * original's path: a file that cannot be stamped is not handed out.
     */
    public static function forPublic(Product $product, ProductDocument $document): ?string
    {
        $kind = $document->previewKind();
        $disk = Storage::disk(ProductDocument::DISK);

        if ($kind === null || ! $disk->exists($document->path)) {
            return null;
        }

        $path = static::pathFor($product, $document);

        if ($disk->exists($path)) {
            return $path;
        }

        try {
            Cache::lock("watermark:{$path}", 120)->block(
                60,
                fn () => static::make($document, $kind, $path),
            );
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }

        return $path;
    }

    /**
     * Delete the stamped copies of a document, all but the one named.
     */
    public static function forget(ProductDocument $document, ?string $except = null): void
    {
        $disk = Storage::disk(ProductDocument::DISK);
        $directory = static::directoryFor($document->product_id);

        collect($disk->files($directory))
            ->filter(fn (string $file) => Str::startsWith(basename($file), "{$document->id}-") && $file !== $except)
            ->each(fn (string $file) => $disk->delete($file));
    }

    /**
     * Get what is stamped across the public files: the configured text, or
     * the site's own domain.
     */
    public static function text(): string
    {
        $configured = config('watermark.text');

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        $host = parse_url((string) config('app.url'), PHP_URL_HOST);

        return is_string($host) && $host !== '' ? $host : (string) config('app.name');
    }

    /**
     * Stamp a document's copy at the given path, unless whoever held the
     * lock before made it already.
     */
    protected static function make(ProductDocument $document, DocumentPreviewKind $kind, string $path): void
    {
        $disk = Storage::disk(ProductDocument::DISK);

        if ($disk->exists($path)) {
            return;
        }

        $original = (string) $disk->get($document->path);

        $disk->put($path, $kind === DocumentPreviewKind::Pdf
            ? static::stampPdf($original, static::text())
            : static::stampImage($original, static::text(), (string) $document->previewContentType()));

        static::forget($document, except: $path);
    }

    /**
     * Get where the stamped copy of a document is kept.
     */
    protected static function pathFor(Product $product, ProductDocument $document): string
    {
        $hash = substr(hash('sha256', self::VERSION."\0".static::text()."\0".$document->path), 0, 16);
        $extension = $document->previewKind() === DocumentPreviewKind::Pdf
            ? 'pdf'
            : Str::after((string) $document->previewContentType(), 'image/');

        return static::directoryFor($product->id)."/{$document->id}-{$hash}.{$extension}";
    }

    /**
     * Get the directory a product's stamped copies are kept in: inside the
     * product's own, so they go when the product does.
     */
    protected static function directoryFor(int $productId): string
    {
        return "product-documents/{$productId}/watermarked";
    }

    /**
     * Stamp the text once across a picture, letter by letter along its wave.
     *
     * A picture larger than it needs to be for a screen is scaled down
     * first: the full resolution stays behind the login.
     */
    protected static function stampImage(string $bytes, string $text, string $contentType): string
    {
        $image = @imagecreatefromstring($bytes);

        if (! $image instanceof GdImage) {
            throw new RuntimeException('The picture could not be read for watermarking.');
        }

        $maxEdge = (int) config('watermark.image.max_edge');
        $width = imagesx($image);
        $height = imagesy($image);

        if (max($width, $height) > $maxEdge) {
            $scale = $maxEdge / max($width, $height);
            $scaled = imagescale($image, (int) round($width * $scale), (int) round($height * $scale), IMG_BICUBIC);

            if ($scaled instanceof GdImage) {
                $image = $scaled;
                $width = imagesx($image);
                $height = imagesy($image);
            }
        }

        imagealphablending($image, true);
        imagesavealpha($image, true);

        $font = resource_path('fonts/DejaVuSans-Bold.ttf');
        $settings = config('watermark.image');
        $opacity = max(0.0, min(1.0, (float) $settings['opacity']));
        $transparency = fn (float $opacity): int => max(0, min(127, (int) round(127 * (1 - $opacity))));
        $fill = imagecolorallocatealpha($image, 255, 255, 255, $transparency($opacity));
        $edge = imagecolorallocatealpha($image, 0, 0, 0, $transparency($opacity * 0.6));

        if ($fill === false || $edge === false) {
            throw new RuntimeException('The watermark colours could not be allocated.');
        }

        $measure = function (string $text, float $size) use ($font): float {
            $box = imagettfbbox($size, 0, $font, $text);

            return $box === false ? $size * 0.6 * mb_strlen($text) : (float) abs($box[4] - $box[6]);
        };

        $stamp = new WatermarkStamp($width, $height, $settings);
        $lineHeightRatio = (float) $settings['line_height_ratio'];

        foreach ($stamp->glyphsFor($text, $measure, new Randomizer) as $glyph) {
            /**
             * GD writes from the left end of the baseline, so step back
             * half a glyph along the text and down a third of a line.
             */
            $radians = deg2rad($glyph['angleDegrees']);
            $half = $measure($glyph['text'], $glyph['fontSize']) / 2;
            $drop = $glyph['fontSize'] * $lineHeightRatio * 0.35;
            $x = (int) round($glyph['x'] - ($half * cos($radians)) + ($drop * sin($radians)));
            $y = (int) round($glyph['y'] + ($half * sin($radians)) + ($drop * cos($radians)));

            imagettftext($image, $glyph['fontSize'], $glyph['angleDegrees'], $x + 1, $y + 1, $edge, $font, $glyph['text']);
            imagettftext($image, $glyph['fontSize'], $glyph['angleDegrees'], $x, $y, $fill, $font, $glyph['text']);
        }

        ob_start();

        match ($contentType) {
            'image/png' => imagepng($image, null, 6),
            'image/webp' => imagewebp($image, null, 85),
            default => imagejpeg($image, null, 88),
        };

        return (string) ob_get_clean();
    }

    /**
     * Stamp the text across every page of a PDF, with Ghostscript.
     *
     * Each page's stamps are laid out here, for that page's own size, and
     * drawn last on the page, over whatever it holds, so a scan is marked
     * as surely as typed text. They blend with Multiply, so text crossing
     * them stays readable. Every letter goes in as hex, so nothing in the
     * text is ever read as PostScript, and -dSAFER keeps a hostile file
     * from reaching anything beyond itself.
     */
    protected static function stampPdf(string $bytes, string $text): string
    {
        $input = tempnam(sys_get_temp_dir(), 'wm-in-');
        $output = tempnam(sys_get_temp_dir(), 'wm-out-');
        $program = tempnam(sys_get_temp_dir(), 'wm-ps-');

        if ($input === false || $output === false || $program === false) {
            throw new RuntimeException('No temporary file for the watermark.');
        }

        try {
            file_put_contents($input, $bytes);
            file_put_contents($program, static::pageStamps(static::pageSizes($input), $text));

            $result = Process::timeout(self::PDF_TIMEOUT_SECONDS)->run([
                static::ghostscript(),
                '-dSAFER',
                '-dALLOWPSTRANSPARENCY',
                '-dBATCH',
                '-dNOPAUSE',
                '-dQUIET',
                '-sDEVICE=pdfwrite',
                '-sOutputFile='.$output,
                $program,
                '-f', $input,
            ]);

            $stamped = (string) file_get_contents($output);

            /**
             * Ghostscript drops a page it cannot draw and carries on, so a
             * clean exit is not enough: a copy missing pages is no copy.
             */
            if ($result->failed()
                || ! str_starts_with($stamped, '%PDF')
                || str_contains($result->output().$result->errorOutput(), 'Could not draw this page')) {
                throw new RuntimeException('Ghostscript could not watermark the PDF: '.Str::limit($result->errorOutput().$result->output(), 500));
            }

            return $stamped;
        } finally {
            @unlink($input);
            @unlink($output);
            @unlink($program);
        }
    }

    /**
     * Get the width and height, in points, of every page of a PDF, as the
     * reader sees it: a page turned a quarter is as wide as it is tall.
     *
     * @return array<int, array{0: float, 1: float}>
     */
    protected static function pageSizes(string $path): array
    {
        $result = Process::timeout(self::PDF_TIMEOUT_SECONDS)->run([
            static::ghostscript(),
            '-dSAFER',
            '-dNODISPLAY',
            '-dQUIET',
            '-dBATCH',
            '-dNOPAUSE',
            '--permit-file-read='.$path,
            '-sFile='.$path,
            '-c', 'File (r) file runpdfbegin 1 1 pdfpagecount { pdfgetpage dup /MediaBox pget pop ==only ( ) print /Rotate pget { == } { 0 == } ifelse } for quit',
        ]);

        preg_match_all('/\[\s*(-?[\d.]+)\s+(-?[\d.]+)\s+(-?[\d.]+)\s+(-?[\d.]+)\s*\]\s+(-?\d+)/', $result->output(), $matches, PREG_SET_ORDER);

        if ($result->failed() || $matches === []) {
            throw new RuntimeException('Ghostscript could not read the PDF pages: '.Str::limit($result->errorOutput().$result->output(), 500));
        }

        return array_map(function (array $match): array {
            $width = abs((float) $match[3] - (float) $match[1]);
            $height = abs((float) $match[4] - (float) $match[2]);

            return ((int) $match[5]) % 180 === 0 ? [$width, $height] : [$height, $width];
        }, $matches);
    }

    /**
     * Get the PostScript that draws each page's stamps at its end.
     *
     * Each letter is [x y angle size <hex>], placed from the page's
     * top-left as WatermarkStamp lays it out and turned here into
     * PostScript's bottom-up space.
     *
     * @param  array<int, array{0: float, 1: float}>  $pageSizes
     */
    protected static function pageStamps(array $pageSizes, string $text): string
    {
        $settings = config('watermark.pdf');
        $text = mb_convert_encoding($text, 'ISO-8859-1', 'UTF-8');
        $measure = fn (string $text, float $size): float => static::helveticaWidth($text) * $size / 1000;
        $randomizer = new Randomizer;

        $pages = array_map(function (array $size) use ($settings, $text, $measure, $randomizer): string {
            [$width, $height] = $size;

            $glyphs = (new WatermarkStamp($width, $height, $settings))->glyphsFor($text, $measure, $randomizer);

            return '['.implode(' ', array_map(fn (array $glyph): string => sprintf(
                '[%.2F %.2F %.3F %.2F <%s>]',
                $glyph['x'],
                $height - $glyph['y'],
                $glyph['angleDegrees'],
                $glyph['fontSize'],
                bin2hex($glyph['text']),
            ), $glyphs)).']';
        }, $pageSizes);

        [$red, $green, $blue] = static::rgb((string) $settings['colour']);
        $opacity = max(0.0, min(1.0, (float) $settings['opacity']));
        $blendMode = in_array($settings['blend_mode'], ['Normal', 'Multiply', 'Darken'], true) ? $settings['blend_mode'] : 'Normal';

        return sprintf(<<<'PS'
            /WMFont /Helvetica-Bold findfont dup length dict begin
              { 1 index /FID ne { def } { pop pop } ifelse } forall
              /Encoding ISOLatin1Encoding def currentdict
            end definefont pop
            /WMPages [
            %s
            ] def
            << /EndPage {
              exch /WMIndex exch def
              dup 2 lt {
                pop
                gsave initmatrix
                %.4F %.4F %.4F setrgbcolor %.3F .setfillconstantalpha /%s .setblendmode
                WMPages WMIndex WMPages length mod get {
                  aload pop /WMChar exch def /WMSize exch def /WMAngle exch def
                  gsave translate WMAngle rotate
                  /WMFont WMSize selectfont
                  WMChar stringwidth pop -2 div WMSize -0.35 mul moveto WMChar show
                  grestore
                } forall
                grestore true
              } { pop false } ifelse
            } bind >> setpagedevice
            PS, implode("\n", $pages), $red, $green, $blue, $opacity, $blendMode);
    }

    /**
     * Get how wide a text set in Helvetica Bold is, in thousandths of the
     * font size.
     */
    protected static function helveticaWidth(string $text): float
    {
        if (static::$helveticaWidths === null) {
            preg_match_all('/^C (\d+) ; WX (\d+) ;/m', (string) file_get_contents(resource_path('fonts/Helvetica-Bold.afm')), $matches);

            static::$helveticaWidths = array_combine(array_map('intval', $matches[1]), array_map('intval', $matches[2]));
        }

        $width = 0;

        foreach (str_split($text) as $character) {
            $width += static::$helveticaWidths[ord($character)] ?? 556;
        }

        return (float) $width;
    }

    /**
     * Get a hex colour as red, green and blue between 0 and 1.
     *
     * @return array{0: float, 1: float, 2: float}
     */
    protected static function rgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        if (! preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
            return [0.18, 0.36, 0.61];
        }

        return [
            hexdec(substr($hex, 0, 2)) / 255,
            hexdec(substr($hex, 2, 2)) / 255,
            hexdec(substr($hex, 4, 2)) / 255,
        ];
    }

    /**
     * Get the Ghostscript binary: the configured one, or whichever the PATH
     * or the usual places turn up.
     */
    protected static function ghostscript(): string
    {
        $configured = config('services.ghostscript.binary');

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        return (new ExecutableFinder)->find('gs', '/usr/bin/gs', ['/usr/bin', '/usr/local/bin', '/opt/homebrew/bin']) ?? 'gs';
    }
}
