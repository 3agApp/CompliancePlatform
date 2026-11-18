<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * A picture served from the private disk, usually to anyone.
 *
 * The type comes from the caller's allowlist rather than from what the
 * uploading browser claimed, and the headers keep the file from being
 * sniffed or run as anything but an image. Shared by every public picture
 * so they cannot drift apart.
 */
class PublicImage
{
    /**
     * Stream the image at the given path, or answer 404 when it is gone.
     *
     * A row whose file is missing -- an old record, a restored database, a
     * disk that lost it -- is a picture that does not exist, so it is a 404
     * and never a stack trace on an address open to anyone.
     */
    public static function response(string $disk, string $path, string $name, string $contentType, bool $public = true): StreamedResponse
    {
        abort_unless(Storage::disk($disk)->exists($path), 404);

        return Storage::disk($disk)->response($path, $name, [
            'Content-Type' => $contentType,
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; img-src 'self' data:; object-src 'none'; base-uri 'none'; form-action 'none'",
            /**
             * A public picture is as cacheable as the page showing it, and
             * it never changes: a new picture gets a new address. One only
             * its owners may see is kept out of shared caches.
             */
            'Cache-Control' => ($public ? 'public' : 'private').', max-age=86400',
        ], 'inline');
    }
}
