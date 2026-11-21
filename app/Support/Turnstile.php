<?php

namespace App\Support;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cloudflare Turnstile, asked before a packet may be checked.
 *
 * The page shows Cloudflare's widget, which hands the form a token once it
 * is satisfied a person is there. The token is worth nothing on its own:
 * it is sent on to Cloudflare's Siteverify from here, with the secret, and
 * only a token Cloudflare vouches for -- for this action, on one of our
 * own hostnames -- lets the check through. Cloudflare redeems a token
 * once, so one solved widget cannot be replayed against a thousand
 * serials. It is not meant to stop a determined machine -- the rate limit
 * is for that -- only to keep checking a deliberate act by a person.
 */
class Turnstile
{
    /**
     * Where Cloudflare answers whether a token is good.
     */
    public const string VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    /**
     * The field the widget puts its token in.
     */
    public const string RESPONSE_FIELD = 'cf-turnstile-response';

    /**
     * What the widget on the check form says it is for, so a token solved
     * for anything else is turned away.
     */
    public const string UNIT_CHECK_ACTION = 'unit_check';

    /**
     * Cloudflare never issues a token longer than this.
     */
    protected const int MAX_TOKEN_LENGTH = 2048;

    protected const int TIMEOUT_SECONDS = 10;

    /**
     * Get the public key the widget is drawn with, if one is configured.
     */
    public static function siteKey(): ?string
    {
        $key = config('services.turnstile.site_key');

        return is_string($key) && $key !== '' ? $key : null;
    }

    /**
     * Ask Cloudflare whether a token is good for the given action.
     *
     * Anything short of a clear yes is a no: a missing secret, a token of
     * the wrong shape, Cloudflare out of reach or answering oddly.
     *
     * Cloudflare's testing secrets answer for any hostname and no action,
     * and mark their answer as such; that mark only comes back when the
     * secret configured here is a testing one, so the hostname and action
     * are only held against a real answer.
     */
    public static function verify(?string $token, ?string $remoteIp, string $action): bool
    {
        $secret = config('services.turnstile.secret_key');

        if (! is_string($secret) || $secret === '') {
            Log::warning('Turnstile has no secret key configured, so every check is turned away.');

            return false;
        }

        if ($token === null || $token === '' || strlen($token) > self::MAX_TOKEN_LENGTH) {
            return false;
        }

        try {
            $response = Http::asForm()
                ->timeout(self::TIMEOUT_SECONDS)
                ->post(self::VERIFY_URL, array_filter([
                    'secret' => $secret,
                    'response' => $token,
                    'remoteip' => $remoteIp,
                ]));
        } catch (ConnectionException $exception) {
            report($exception);

            return false;
        }

        if (! $response->successful() || $response->json('success') !== true) {
            return false;
        }

        if ($response->json('metadata.result_with_testing_key') === true) {
            return true;
        }

        if (! in_array($response->json('hostname'), static::hostnames(), true)) {
            /**
             * Named in the log because the usual cause is configuration, not
             * an attack: the site answering on a host -- www, a second domain
             * -- that TURNSTILE_HOSTNAMES does not list, which turns every
             * real check away.
             */
            Log::warning('Turnstile token was solved on a hostname that is not allowed.', [
                'hostname' => $response->json('hostname'),
                'allowed' => static::hostnames(),
            ]);

            return false;
        }

        return $response->json('action') === $action;
    }

    /**
     * Get the hostnames a token may have been solved on.
     *
     * @return list<string>
     */
    protected static function hostnames(): array
    {
        $hostnames = config('services.turnstile.hostnames');

        return is_array($hostnames) ? array_values(array_filter($hostnames, is_string(...))) : [];
    }
}
