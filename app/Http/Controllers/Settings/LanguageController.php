<?php

namespace App\Http\Controllers\Settings;

use App\Enums\Locale;
use App\Http\Controllers\Controller;
use App\Http\Middleware\SetLocale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Validation\Rule;

/**
 * Switch the language the application is read in.
 *
 * Open to everybody, signed in or not, because the login page needs a
 * switch as much as the dashboard does. A signed-in person's choice is kept
 * on their account, and an empty one hands them back to their
 * organization's default. Either way the browser remembers it too, so the
 * login page after signing out still speaks it.
 */
class LanguageController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'locale' => ['present', 'nullable', Rule::enum(Locale::class)],
        ]);

        $locale = isset($validated['locale']) ? Locale::from($validated['locale']) : null;

        $request->user()?->update(['locale' => $locale]);

        if ($locale !== null) {
            Cookie::queue(SetLocale::COOKIE, $locale->value, 60 * 24 * 365);
        }

        return back();
    }
}
