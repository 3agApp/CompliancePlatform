<?php

namespace App\Enums;

/**
 * How a document can be shown without being downloaded first.
 *
 * Only two kinds of file are ever put in front of a reader unopened: a PDF,
 * which the browser has a viewer for, and a raster image, which it simply
 * draws. Word and Excel files are left to be downloaded, because a browser
 * cannot show them and a viewer that renders nothing is worse than a link.
 */
enum DocumentPreviewKind: string
{
    case Pdf = 'pdf';
    case Image = 'image';

    /**
     * What a browser shows inline, and the content type to serve it under.
     *
     * This doubles as an allowlist. The stored content type came from the
     * uploading browser and was never checked -- only the bytes were, by the
     * `mimes:` rule on the way in -- so a preview is served under the type
     * found here and never under the string in the column. A file that
     * claimed to be something else cannot choose its own header on the way
     * back out.
     *
     * @var array<string, array{kind: string, content_type: string}>
     */
    private const array PREVIEWABLE = [
        'application/pdf' => ['kind' => 'pdf', 'content_type' => 'application/pdf'],
        'image/png' => ['kind' => 'image', 'content_type' => 'image/png'],
        'image/jpeg' => ['kind' => 'image', 'content_type' => 'image/jpeg'],
        /** Some browsers send this for a JPEG; it is served as the real thing. */
        'image/jpg' => ['kind' => 'image', 'content_type' => 'image/jpeg'],
        'image/webp' => ['kind' => 'image', 'content_type' => 'image/webp'],
    ];

    /**
     * Get how a file of this content type would be shown, if it can be.
     */
    public static function forContentType(?string $mimeType): ?self
    {
        $row = self::PREVIEWABLE[self::normalize($mimeType)] ?? null;

        return $row === null ? null : self::from($row['kind']);
    }

    /**
     * Get the content type a preview of this file is served under.
     */
    public static function contentTypeFor(?string $mimeType): ?string
    {
        return self::PREVIEWABLE[self::normalize($mimeType)]['content_type'] ?? null;
    }

    /**
     * Reduce a stored content type to the bare type it names.
     *
     * A content type may arrive cased oddly or carrying parameters --
     * `IMAGE/PNG`, `image/png; charset=binary` -- and neither should decide
     * whether a file is previewable.
     */
    private static function normalize(?string $mimeType): string
    {
        return strtolower(trim(explode(';', (string) $mimeType, 2)[0]));
    }
}
