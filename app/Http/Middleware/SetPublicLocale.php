<?php

namespace App\Http\Middleware;

use App\Enums\Locale;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Read the language a reader picked on the public pages.
 *
 * Kept in a plain cookie the page's own switch writes, so the messages the
 * server sends back -- a security check that failed, a serial nobody issued -- come
 * back in the language the rest of the page is in.
 */
class SetPublicLocale
{
    /**
     * The cookie the switch writes.
     */
    public const string COOKIE = 'public_lang';

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->cookie(self::COOKIE);

        $chosen = is_string($locale) ? Locale::tryFrom($locale) : null;

        App::setLocale(($chosen ?? Locale::default())->value);

        return $next($request);
    }
}
