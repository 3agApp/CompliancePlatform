<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Read the language a reader picked on the public pages.
 *
 * Kept in a plain cookie the page's own switch writes, so the messages the
 * server sends back -- a captcha typed wrong, a serial nobody issued -- come
 * back in the language the rest of the page is in.
 */
class SetPublicLocale
{
    /**
     * The cookie the switch writes.
     */
    public const string COOKIE = 'public_lang';

    /**
     * The languages the public pages speak, the first being the default.
     *
     * @var list<string>
     */
    public const array LOCALES = ['en', 'de'];

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->cookie(self::COOKIE);

        App::setLocale(in_array($locale, self::LOCALES, true) ? $locale : self::LOCALES[0]);

        return $next($request);
    }
}
