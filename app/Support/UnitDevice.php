<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

/**
 * The browser looking at a serial, as far as the platform ever knows it.
 *
 * A random id in a long-lived cookie, and only ever stored as a hash keyed
 * with the application key. It is enough to tell the browser that
 * checked a packet "this was you" when it checks again, and to tell its
 * checks apart from everybody else's. It says nothing about who is behind any of them: no address,
 * no user agent, no account.
 */
class UnitDevice
{
    /**
     * The cookie the id lives in.
     */
    public const string COOKIE = 'unit_device';

    /**
     * How long the cookie lasts, in minutes: five years, the life of a
     * packet on a shelf and then some.
     */
    public const int LIFETIME = 60 * 24 * 365 * 5;

    /**
     * Get the browser's id, giving it one if it has none yet.
     *
     * A new id is queued onto the response, so the browser keeps it.
     */
    public static function id(Request $request): string
    {
        $id = $request->cookie(self::COOKIE);

        if (is_string($id) && Str::isUuid($id)) {
            return $id;
        }

        $id = (string) Str::uuid();

        Cookie::queue(self::COOKIE, $id, self::LIFETIME);

        /**
         * Set on the request too, so a second read in the same request --
         * a check that also reads the history -- sees the same browser.
         */
        $request->cookies->set(self::COOKIE, $id);

        return $id;
    }

    /**
     * Get the hash the browser is stored as.
     */
    public static function hash(Request $request): string
    {
        return hash_hmac('sha256', self::id($request), (string) config('app.key'));
    }
}
