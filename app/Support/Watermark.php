<?php

namespace App\Support;

use App\Enums\DocumentPreviewKind;
use App\Models\Product;
use App\Models\ProductDocument;
use GdImage;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
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
 * a copy passed along still says where it came from. A picture and every
 * PDF page get one stamp each, tilted at random and bent along a random
 * wave (see WatermarkStamp). The
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
    public const int VERSION = 3;

    /**
     * How long a stamp on a PDF may take before it is given up on.
     */
    protected const int PDF_TIMEOUT_SECONDS = 60;

    /**
     * What a locked copy still allows: printing, at full quality, and
     * reading out to a screen reader. Copying, editing, filling in and
     * taking pages out all need the owner's password, which nobody keeps.
     */
    protected const int PRINT_ONLY = -1340;

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
            }
        }

        imagealphablending($image, true);
        imagesavealpha($image, true);

        $settings = config('watermark.image');
        $opacity = max(0.0, min(1.0, (float) $settings['opacity']));
        $fill = imagecolorallocatealpha($image, 255, 255, 255, static::gdTransparency($opacity));
        $edge = imagecolorallocatealpha($image, 0, 0, 0, static::gdTransparency($opacity * 0.6));

        if ($fill === false || $edge === false) {
            throw new RuntimeException('The watermark colours could not be allocated.');
        }

        static::drawStamp($image, $text, $settings, $fill, $edge);

        ob_start();

        match ($contentType) {
            'image/png' => imagepng($image, null, 6),
            'image/webp' => imagewebp($image, null, 85),
            default => imagejpeg($image, null, 88),
        };

        return (string) ob_get_clean();
    }

    /**
     * Paint the stamps across a picture, letter by letter along their wave,
     * with an optional edge one pixel down and right of each letter.
     *
     * @param  array{stamps: int, font_size_ratio: float, min_font_size: float, max_font_size: float, line_height_ratio: float}  $settings
     */
    protected static function drawStamp(GdImage $image, string $text, array $settings, int $fill, ?int $edge = null): void
    {
        $font = resource_path('fonts/DejaVuSans-Bold.ttf');

        $measure = function (string $text, float $size) use ($font): float {
            $box = imagettfbbox($size, 0, $font, $text);

            return $box === false ? $size * 0.6 * mb_strlen($text) : (float) abs($box[4] - $box[6]);
        };

        $stamp = new WatermarkStamp(imagesx($image), imagesy($image), $settings);
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

            if ($edge !== null) {
                imagettftext($image, $glyph['fontSize'], $glyph['angleDegrees'], $x + 1, $y + 1, $edge, $font, $glyph['text']);
            }

            imagettftext($image, $glyph['fontSize'], $glyph['angleDegrees'], $x, $y, $fill, $font, $glyph['text']);
        }
    }

    /**
     * Get GD's alpha for an opacity: 0 is solid, 127 fully see-through.
     *
     * @return int<0, 127>
     */
    protected static function gdTransparency(float $opacity): int
    {
        return max(0, min(127, (int) round(127 * (1 - max(0.0, min(1.0, $opacity))))));
    }

    /**
     * Stamp the text across every page of a PDF, with Ghostscript, and seal
     * the result.
     *
     * Each page's stamp is laid out here, for that page's own size, and
     * drawn last on the page, over whatever it holds. Its letters are
     * filled outlines rather than text, so nothing that strips text takes
     * them along. A scanned page is flattened with its stamp into a single
     * picture, so the stamp is part of the scan rather than a layer over
     * it. The copy is then slimmed down and, unless switched off, locked
     * against editing.
     *
     * Every letter and path goes in as hex, so nothing in them is ever read
     * as PostScript, and -dSAFER keeps a hostile file from reaching
     * anything beyond itself.
     */
    protected static function stampPdf(string $bytes, string $text): string
    {
        $workspace = sys_get_temp_dir().'/watermark-'.Str::random(20);

        if (! mkdir($workspace, 0700)) {
            throw new RuntimeException('No temporary directory for the watermark.');
        }

        try {
            $input = "{$workspace}/in.pdf";
            $output = "{$workspace}/out.pdf";
            file_put_contents($input, $bytes);

            $settings = config('watermark.pdf');
            $sizes = static::pageSizes($input);
            $stamps = static::pageStamps($sizes, $text);
            $scanned = static::scannedPages($input, $workspace, count($sizes));

            $pages = [];

            foreach ($sizes as $index => $size) {
                $pages[] = ['page' => $index + 1, 'stamp' => $stamps[$index]];
            }

            foreach (static::bakePages($input, $workspace, $scanned, $text) as $index => $picture) {
                $pages[$index] = $picture + ['size' => $sizes[$index]];
            }

            $result = static::runGhostscript([
                '-sDEVICE=pdfwrite',
                '-dPDFSETTINGS=/'.(in_array($settings['quality'], ['screen', 'ebook', 'printer', 'prepress'], true) ? $settings['quality'] : 'ebook'),
                ...($settings['lock'] ? [
                    '-sOwnerPassword='.Str::random(32),
                    '-dEncryptionR=3',
                    '-dKeyLength=128',
                    '-dPermissions='.self::PRINT_ONLY,
                ] : []),
                '--permit-file-read='.$workspace.'/',
                '-sOutputFile='.$output,
            ], $workspace, static::pageProgram($input, $pages));

            $stamped = is_file($output) ? (string) file_get_contents($output) : '';

            if ($result->failed() || ! str_starts_with($stamped, '%PDF')) {
                throw new RuntimeException('Ghostscript could not watermark the PDF: '.Str::limit($result->errorOutput().$result->output(), 500));
            }

            return $stamped;
        } finally {
            File::deleteDirectory($workspace);
        }
    }

    /**
     * Get the numbers of the pages that hold next to no text: scans, or
     * text already turned into shapes.
     *
     * @return array<int, int>
     */
    protected static function scannedPages(string $input, string $workspace, int $pageCount): array
    {
        $result = static::runGhostscript([
            '-sDEVICE=txtwrite',
            '-sOutputFile='.$workspace.'/text-%d.txt',
            '-f', $input,
        ]);

        if ($result->failed()) {
            throw new RuntimeException('Ghostscript could not read the PDF text: '.Str::limit($result->errorOutput().$result->output(), 500));
        }

        $limit = (int) config('watermark.pdf.scan_characters');

        return array_values(array_filter(range(1, $pageCount), function (int $page) use ($workspace, $limit): bool {
            $file = "{$workspace}/text-{$page}.txt";
            $characters = is_file($file) ? (string) preg_replace('/\s+/u', '', (string) file_get_contents($file)) : '';

            return mb_strlen($characters) <= $limit;
        }));
    }

    /**
     * Draw the scanned pages as pictures with the stamp painted into them,
     * and get each one, as a JPEG, by its page's index.
     *
     * Ghostscript draws the bare page and GD paints the stamp, the same way
     * it does on a photo: Ghostscript's own drawing of a see-through stamp
     * onto a picture leaves stray tinted blocks behind.
     *
     * @param  array<int, int>  $scanned
     * @return array<int, array{picture: string, width: int, height: int}>
     */
    protected static function bakePages(string $input, string $workspace, array $scanned, string $text): array
    {
        if ($scanned === []) {
            return [];
        }

        $settings = config('watermark.pdf');
        $dpi = (int) $settings['scan_dpi'];

        $result = static::runGhostscript([
            '-sDEVICE=png16m',
            '-r'.$dpi,
            '-sPageList='.implode(',', $scanned),
            '-sOutputFile='.$workspace.'/scan-%d.png',
            '-f', $input,
        ]);

        if ($result->failed()) {
            throw new RuntimeException('Ghostscript could not draw the scanned pages: '.Str::limit($result->errorOutput().$result->output(), 500));
        }

        /** The stamp's sizes are set in points; the picture counts pixels. */
        $pixelsPerPoint = $dpi / 72;
        $stampSettings = [
            'stamps' => (int) $settings['stamps'],
            'font_size_ratio' => (float) $settings['font_size_ratio'],
            'min_font_size' => (float) $settings['min_font_size'] * $pixelsPerPoint,
            'max_font_size' => (float) $settings['max_font_size'] * $pixelsPerPoint,
            'line_height_ratio' => (float) $settings['line_height_ratio'],
        ];
        $channel = fn (float $share): int => max(0, min(255, (int) round($share * 255)));

        $pictures = [];

        foreach ($scanned as $position => $page) {
            $drawn = "{$workspace}/scan-".($position + 1).'.png';
            $image = is_file($drawn) ? @imagecreatefrompng($drawn) : false;

            if (! $image instanceof GdImage) {
                throw new RuntimeException("Ghostscript drew no picture of page {$page}.");
            }

            [$red, $green, $blue] = static::rgb((string) $settings['colour']);
            $colour = imagecolorallocatealpha(
                $image,
                $channel($red),
                $channel($green),
                $channel($blue),
                static::gdTransparency((float) $settings['opacity']),
            );

            if ($colour === false) {
                throw new RuntimeException('The watermark colour could not be allocated.');
            }

            imagealphablending($image, true);
            static::drawStamp($image, $text, $stampSettings, $colour);

            $picture = "{$workspace}/scan-".($position + 1).'.jpg';
            imagejpeg($image, $picture, (int) $settings['scan_quality']);

            $pictures[$page - 1] = ['picture' => $picture, 'width' => imagesx($image), 'height' => imagesy($image)];
        }

        return $pictures;
    }

    /**
     * Run Ghostscript on a PDF the way every pass here does: safely, with
     * the extra arguments given and, if any, a program written beside it.
     *
     * @param  array<int, string>  $arguments
     */
    protected static function runGhostscript(array $arguments, ?string $workspace = null, ?string $program = null): ProcessResult
    {
        $programFile = null;

        if ($program !== null && $workspace !== null) {
            $programFile = $workspace.'/program-'.Str::random(8).'.ps';
            file_put_contents($programFile, $program);
        }

        $result = Process::timeout(self::PDF_TIMEOUT_SECONDS)->run([
            static::ghostscript(),
            '-dSAFER',
            '-dALLOWPSTRANSPARENCY',
            '-dBATCH',
            '-dNOPAUSE',
            '-dQUIET',
            ...$arguments,
            ...($programFile !== null ? [$programFile] : []),
        ]);

        /**
         * Ghostscript drops a page it cannot draw and carries on, so a clean
         * exit is not enough: a copy missing pages is no copy.
         */
        if ($result->successful() && str_contains($result->output().$result->errorOutput(), 'Could not draw this page')) {
            throw new RuntimeException('Ghostscript dropped a page: '.Str::limit($result->errorOutput().$result->output(), 500));
        }

        return $result;
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
     * Lay out each page's stamp, for that page's own size, as a PostScript
     * array of letters: [x y angle size <hex>], placed from the page's
     * top-left as WatermarkStamp lays it out and turned here into
     * PostScript's bottom-up space.
     *
     * @param  array<int, array{0: float, 1: float}>  $pageSizes
     * @return array<int, string>
     */
    protected static function pageStamps(array $pageSizes, string $text): array
    {
        $settings = config('watermark.pdf');
        $text = mb_convert_encoding($text, 'ISO-8859-1', 'UTF-8');
        $measure = fn (string $text, float $size): float => static::helveticaWidth($text) * $size / 1000;
        $randomizer = new Randomizer;

        return array_map(function (array $size) use ($settings, $text, $measure, $randomizer): string {
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
    }

    /**
     * Get the PostScript that puts out the given pages one by one: a page
     * of the PDF with its stamp drawn over it at its end, or a flattened
     * scan drawn as the whole page.
     *
     * Each stamp letter is filled as an outline of Helvetica Bold.
     *
     * @param  array<int, array{page: int, stamp: string}|array{picture: string, width: int, height: int, size: array{0: float, 1: float}}>  $pages
     */
    protected static function pageProgram(string $input, array $pages): string
    {
        $settings = config('watermark.pdf');
        [$red, $green, $blue] = static::rgb((string) $settings['colour']);
        $opacity = max(0.0, min(1.0, (float) $settings['opacity']));
        $blendMode = in_array($settings['blend_mode'], ['Normal', 'Multiply', 'Darken'], true) ? $settings['blend_mode'] : 'Normal';

        $body = array_map(function (array $page): string {
            if (isset($page['picture'])) {
                [$width, $height] = $page['size'];

                return sprintf(
                    "userdict /WMStamp [] put\n"
                    ."<< /PageSize [%1\$.2F %2\$.2F] >> setpagedevice\n"
                    ."gsave %1\$.2F %2\$.2F scale /DeviceRGB setcolorspace\n"
                    ."<< /ImageType 1 /Width %3\$d /Height %4\$d /BitsPerComponent 8 /Decode [0 1 0 1 0 1]\n"
                    ."   /ImageMatrix [%3\$d 0 0 -%4\$d 0 %4\$d] /DataSource <%5\$s> (r) file /DCTDecode filter >> image\n"
                    .'grestore showpage',
                    $width,
                    $height,
                    $page['width'],
                    $page['height'],
                    bin2hex($page['picture']),
                );
            }

            return "userdict /WMStamp {$page['stamp']} put\n{$page['page']} pdfgetpage pdfshowpage";
        }, $pages);

        return sprintf(<<<'PS'
            /WMFont /Helvetica-Bold findfont dup length dict begin
              { 1 index /FID ne { def } { pop pop } ifelse } forall
              /Encoding ISOLatin1Encoding def currentdict
            end definefont pop
            userdict /WMStamp [] put
            << /EndPage {
              exch pop
              dup 2 lt {
                pop
                gsave initmatrix
                %.4F %.4F %.4F setrgbcolor %.3F .setfillconstantalpha /%s .setblendmode
                userdict /WMStamp get {
                  aload pop /WMChar exch def /WMSize exch def /WMAngle exch def
                  gsave translate WMAngle rotate
                  /WMFont WMSize selectfont newpath
                  WMChar stringwidth pop -2 div WMSize -0.35 mul moveto
                  WMChar false charpath fill
                  grestore
                } forall
                grestore true
              } { pop false } ifelse
            } bind >> setpagedevice
            <%s> (r) file runpdfbegin process_trailer_attrs
            %s
            runpdfend
            PS, $red, $green, $blue, $opacity, $blendMode, bin2hex($input), implode("\n", $body));
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
