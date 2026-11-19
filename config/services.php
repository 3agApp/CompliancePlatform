<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    /*
     * Watermarks the PDFs on a product's public page. Found on the PATH
     * when left empty.
     */
    'ghostscript' => [
        'binary' => env('GHOSTSCRIPT_BINARY'),
    ],

    /*
     * Cloudflare Turnstile, asked before a packet's serial is checked on
     * the public page. A token is only taken from the hostnames listed,
     * the host of APP_URL when left empty.
     */
    'turnstile' => [
        'site_key' => env('TURNSTILE_SITE_KEY'),
        'secret_key' => env('TURNSTILE_SECRET_KEY'),
        'hostnames' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) (env('TURNSTILE_HOSTNAMES') ?: parse_url((string) env('APP_URL'), PHP_URL_HOST))),
        ))),
    ],

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
