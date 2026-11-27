<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Imagick;
use RuntimeException;
use Throwable;

/**
 * A HEIC or HEIF photo -- what an iPhone takes by default -- turned into a
 * JPEG as it is filed.
 *
 * Most browsers cannot draw a HEIC and GD cannot read one, so a photo kept
 * as it arrived could be neither previewed nor watermarked for the public
 * page. Converting once on the way in means everything after it only ever
 * sees a JPEG. It needs Imagick built with libheif; a server without that
 * refuses the photo with a message rather than filing something it cannot
 * show.
 */
class HeicImage
{
    /**
     * The extensions a HEIC or HEIF photo goes by.
     *
     * @var array<int, string>
     */
    public const array EXTENSIONS = ['heic', 'heif'];

    /**
     * Determine if the uploaded file is a HEIC or HEIF photo, judged by its
     * contents as the `mimes:` rule judges it.
     */
    public function isHeic(UploadedFile $file): bool
    {
        return in_array($file->guessExtension(), self::EXTENSIONS, true);
    }

    /**
     * Determine if this server can read a HEIC photo to convert it.
     */
    public function canConvert(): bool
    {
        if (! class_exists(Imagick::class)) {
            return false;
        }

        try {
            return Imagick::queryFormats('HEIC') !== [];
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Convert a HEIC photo to a JPEG.
     *
     * The photo is turned upright first, since a phone records which way
     * up it was held rather than turning the pixels. Its metadata is then
     * stripped, so the place it was taken never travels with it.
     */
    public function toJpeg(string $bytes): string
    {
        try {
            $image = new Imagick;
            $image->readImageBlob($bytes);
            $image->setIteratorIndex(0);
            $image->autoOrient();
            $image->stripImage();
            $image->setImageFormat('jpeg');
            $image->setImageCompressionQuality(90);

            $jpeg = $image->getImageBlob();
            $image->clear();
        } catch (Throwable $exception) {
            throw new RuntimeException('The HEIC photo could not be converted to JPEG.', previous: $exception);
        }

        return $jpeg;
    }

    /**
     * Get the name the converted photo is filed under: the uploader's name
     * with a `.jpg` ending, so a download opens as what it now is.
     */
    public function jpegName(string $name): string
    {
        return pathinfo($name, PATHINFO_FILENAME).'.jpg';
    }
}
