<?php

namespace App\Ai;

use App\Models\OrganizationAiSetting;
use Closure;
use Laravel\Ai\AiManager;

/**
 * Lends an organization's own AI key to one call, and takes it back.
 */
class OrganizationProvider
{
    public function __construct(private AiManager $ai)
    {
        //
    }

    /**
     * Run the callback against a provider configured with this organization's key.
     *
     * The SDK reads its credentials from config and caches the instance it
     * built from them, so a tenant's key is given a provider name of its own
     * rather than written over the shared one. Nothing else can then resolve
     * it by accident, and the shared entry is never in a state where one
     * organization's key is sitting in it.
     *
     * The finally is not tidying. A worker outlives the request, and a
     * provider call that throws would otherwise leave the key in config for
     * whoever prompts next.
     *
     * @param  Closure(string): mixed  $callback
     */
    public function using(OrganizationAiSetting $setting, Closure $callback): mixed
    {
        $name = self::nameFor($setting);

        config(["ai.providers.{$name}" => [
            'driver' => $setting->provider->lab()->value,
            'key' => $setting->api_key,
        ]]);

        try {
            return $callback($name);
        } finally {
            config(["ai.providers.{$name}" => null]);
            $this->ai->forgetInstance($name);
        }
    }

    /**
     * Get the provider name an organization's key is lent under.
     */
    public static function nameFor(OrganizationAiSetting $setting): string
    {
        return 'organization-'.$setting->organization_id;
    }
}
