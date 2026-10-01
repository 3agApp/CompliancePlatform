<?php

namespace App\Support;

use Illuminate\Contracts\Session\Session;

/**
 * A code drawn as a picture, typed back by a person before a packet may be
 * checked.
 *
 * Drawn here rather than borrowed from a third party, so nothing about
 * somebody checking a toy leaves this application: no script from another
 * company, no cookie of theirs, nothing to name in a privacy notice. It is
 * not meant to stop a determined machine -- the rate limit is for that --
 * only to keep checking a deliberate act by a person rather than something
 * a link, a crawler or a script does by accident.
 *
 * The code lives in the session and is good for one answer: right or wrong,
 * it is forgotten the moment it is checked, so a code read once cannot be
 * replayed against a thousand serials.
 */
class Captcha
{
    /**
     * Where the code waits in the session.
     */
    public const string SESSION_KEY = 'unit_captcha';

    /**
     * Letters and digits nobody can confuse with each other: no 0 and O, no
     * 1, I and L, no 5 and S, no 2 and Z, no 8 and B.
     */
    public const string ALPHABET = 'ACDEFGHJKMNPQRTUVWXY34679';

    public const int LENGTH = 5;

    /**
     * How long a code stays answerable, in seconds.
     */
    public const int LIFETIME = 600;

    protected const int WIDTH = 180;

    protected const int HEIGHT = 60;

    /**
     * The code every new captcha uses instead of a random one, for tests
     * that have to type it back.
     */
    protected static ?string $fixedCode = null;

    /**
     * Make every captcha from now on use this code.
     */
    public static function fake(?string $code): void
    {
        self::$fixedCode = $code;
    }

    /**
     * Draw a new code into the session and return it as a PNG.
     */
    public static function issue(Session $session): string
    {
        $code = self::$fixedCode ?? self::randomCode();

        $session->put(self::SESSION_KEY, [
            'code' => $code,
            'expires_at' => now()->addSeconds(self::LIFETIME)->getTimestamp(),
        ]);

        return self::draw($code);
    }

    /**
     * Check an answer against the code in the session, and forget the code
     * either way.
     */
    public static function check(Session $session, ?string $answer): bool
    {
        $stored = $session->pull(self::SESSION_KEY);

        if (! is_array($stored) || ! is_string($stored['code'] ?? null) || ($stored['expires_at'] ?? 0) < now()->getTimestamp()) {
            return false;
        }

        $answer = strtoupper((string) preg_replace('/\s+/', '', (string) $answer));

        return $answer !== '' && hash_equals($stored['code'], $answer);
    }

    /**
     * Draw a random code.
     */
    protected static function randomCode(): string
    {
        $code = '';

        for ($i = 0; $i < self::LENGTH; $i++) {
            $code .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }

        return $code;
    }

    /**
     * Draw the code: each character at its own angle and height, over a few
     * crossing lines and a scatter of dots.
     */
    protected static function draw(string $code): string
    {
        $image = imagecreatetruecolor(self::WIDTH, self::HEIGHT);

        imagefilledrectangle($image, 0, 0, self::WIDTH, self::HEIGHT, (int) imagecolorallocate($image, 248, 250, 252));

        for ($i = 0; $i < 6; $i++) {
            imageline(
                $image,
                random_int(0, self::WIDTH), random_int(0, self::HEIGHT),
                random_int(0, self::WIDTH), random_int(0, self::HEIGHT),
                (int) imagecolorallocate($image, random_int(150, 210), random_int(150, 210), random_int(150, 210)),
            );
        }

        for ($i = 0; $i < 300; $i++) {
            imagesetpixel(
                $image,
                random_int(0, self::WIDTH - 1), random_int(0, self::HEIGHT - 1),
                (int) imagecolorallocate($image, random_int(120, 200), random_int(120, 200), random_int(120, 200)),
            );
        }

        $font = base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSans-Bold.ttf');
        $step = (self::WIDTH - 20) / self::LENGTH;

        foreach (str_split($code) as $index => $character) {
            $colour = (int) imagecolorallocate($image, random_int(20, 80), random_int(20, 80), random_int(60, 120));

            if (is_file($font)) {
                imagettftext($image, random_int(22, 26), random_int(-25, 25), (int) (12 + $index * $step), random_int(38, 48), $colour, $font, $character);
            } else {
                imagestring($image, 5, (int) (16 + $index * $step), random_int(15, 30), $character, $colour);
            }
        }

        ob_start();
        imagepng($image);
        $png = (string) ob_get_clean();
        imagedestroy($image);

        return $png;
    }
}
