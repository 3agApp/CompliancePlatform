<?php

namespace App\Http\Middleware;

use App\Enums\Locale;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Speak the language of whoever is asking.
 *
 * A signed-in person reads the application in the language they chose, or
 * in their organization's default if they never chose one. Somebody signed
 * out -- on the login page, say -- gets the language they last picked on
 * this browser, then the first one their browser asks for that the
 * application speaks.
 *
 * The public product pages read their own switch on top of this, so a
 * buyer scanning a label is never shown the distributor's language.
 */
class SetLocale
{
    /**
     * The cookie a signed-out reader's choice is kept in. Shared with the
     * public pages, which is one choice per browser rather than two.
     */
    public const string COOKIE = SetPublicLocale::COOKIE;

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        App::setLocale($this->resolve($request)->value);

        return $next($request);
    }

    /**
     * Work out which language the request should be answered in.
     */
    protected function resolve(Request $request): Locale
    {
        $user = $request->user();

        if ($user !== null) {
            return Locale::from($user->preferredLocale());
        }

        $chosen = $request->cookie(self::COOKIE);

        return (is_string($chosen) ? Locale::tryFrom($chosen) : null)
            ?? Locale::tryFrom((string) $request->getPreferredLanguage(Locale::values()))
            ?? Locale::default();
    }
}
