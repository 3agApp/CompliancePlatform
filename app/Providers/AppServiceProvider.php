<?php

namespace App\Providers;

use App\Models\Organization;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureRateLimiting();
    }

    /**
     * Configure the application's rate limiters.
     */
    protected function configureRateLimiting(): void
    {
        /**
         * Guessing document kinds is billed to the organization's own AI
         * account, so it is capped twice: per person, so one member cannot
         * spend the whole allowance, and per organization, so a team all
         * uploading at once cannot either.
         */
        RateLimiter::for('ai-suggestions', function (Request $request): array {
            $organization = $request->route('current_organization');

            return [
                Limit::perMinute(10)->by('ai-suggestions-user:'.$request->user()?->id),
                Limit::perMinute(30)->by('ai-suggestions-organization:'.($organization instanceof Organization ? $organization->id : 'none')),
            ];
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
