<?php

namespace App\Http\Controllers;

use App\Support\Captcha;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * The picture of a code the Check button asks for.
 */
class CaptchaController extends Controller
{
    /**
     * Draw a new code, replacing whatever code the session held.
     */
    public function __invoke(Request $request): Response
    {
        return response(Captcha::issue($request->session()), 200, [
            'Content-Type' => 'image/png',
            'X-Content-Type-Options' => 'nosniff',
            /**
             * Every fetch is a new code, so nothing may keep one: a cached
             * picture would show a code the session no longer holds.
             */
            'Cache-Control' => 'no-store, max-age=0',
        ]);
    }
}
