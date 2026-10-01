<?php

namespace App\Support;

/**
 * The serial printed on one packet.
 *
 * Crockford base32: digits and capitals, without I, L, O and U, so nothing
 * on a label can be read two ways. Twelve characters is about sixty bits --
 * far too many to find a valid one by counting -- and still short enough to
 * type off a box in three groups of four.
 */
class UnitSerial
{
    /**
     * The characters a serial is written in.
     */
    public const string ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    /**
     * How many characters a serial has, without its dashes.
     */
    public const int LENGTH = 12;

    /**
     * Draw a new serial.
     */
    public static function generate(): string
    {
        $serial = '';

        for ($i = 0; $i < self::LENGTH; $i++) {
            $serial .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }

        return $serial;
    }

    /**
     * Read a serial the way somebody typed it.
     *
     * Case, dashes and spaces are forgiven, and so are the letters people
     * type for the digits they look like -- which is the point of an
     * alphabet that leaves those letters out. Returns null for anything that
     * cannot be a serial at all, so it never reaches the database.
     */
    public static function normalize(string $input): ?string
    {
        $serial = strtr(
            strtoupper((string) preg_replace('/[\s\-]+/', '', $input)),
            ['O' => '0', 'I' => '1', 'L' => '1'],
        );

        if (strlen($serial) !== self::LENGTH || strspn($serial, self::ALPHABET) !== self::LENGTH) {
            return null;
        }

        return $serial;
    }

    /**
     * Write a serial the way it is printed: XXXX-XXXX-XXXX.
     */
    public static function format(string $serial): string
    {
        return implode('-', str_split($serial, 4));
    }
}
