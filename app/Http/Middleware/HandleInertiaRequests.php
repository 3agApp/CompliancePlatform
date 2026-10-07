<?php

namespace App\Http\Middleware;

use App\Enums\Locale;
use App\Enums\OrganizationType;
use App\Models\OrganizationInvitation;
use App\Models\SupplierConnection;
use App\Support\Impersonation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Inertia\Inertia;
use Inertia\Middleware;
use Inertia\OnceProp;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user,
                'isAdmin' => (bool) $user?->isAdmin(),
            ],
            'impersonating' => fn () => app(Impersonation::class)->isImpersonating(),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'currentOrganization' => fn () => $user?->currentOrganization ? $user->toUserOrganization($user->currentOrganization) : null,
            'organizations' => fn () => $user?->toUserOrganizations(includeCurrent: true) ?? [],
            'organizationTypes' => OrganizationType::options(),
            'locale' => fn () => App::getLocale(),
            'availableLocales' => Locale::options(),
            'translations' => $this->translations(),
            'pendingInvitationsCount' => fn () => $user
                ? OrganizationInvitation::query()->pendingFor($user->email)->count()
                    + SupplierConnection::query()->pendingFor($user->email)->count()
                : 0,
        ];
    }

    /**
     * Get the strings the frontend needs to speak the current language.
     *
     * The source is written in English, so English needs none. The rest is
     * a few thousand strings that change only on a deploy, so they are sent
     * once and remembered by the browser. The key names the language and
     * the file's version: switching language, or shipping new strings,
     * fetches them again; anything else does not.
     */
    protected function translations(): OnceProp
    {
        $locale = App::getLocale();
        $path = lang_path("{$locale}.json");
        $version = is_file($path) ? filemtime($path).'-'.filesize($path) : 'none';

        return Inertia::once(function () use ($locale, $path): object {
            if ($locale === Locale::default()->value || ! is_file($path)) {
                return (object) [];
            }

            return (object) json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        })->as("translations.{$locale}.{$version}");
    }
}
