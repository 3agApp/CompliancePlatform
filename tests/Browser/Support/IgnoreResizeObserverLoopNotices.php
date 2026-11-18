<?php

namespace Tests\Browser\Support;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stop the browser tests counting the "ResizeObserver loop" notice as a
 * page error.
 *
 * Chromium raises it as an error event when a resize notification has to
 * wait for the next frame. Nothing is lost, and the spec calls it benign,
 * but a Radix select opening inside a dialog sets it off now and then on a
 * busy machine, which made assertNoJavaScriptErrors() fail at random on CI.
 *
 * Pest records page errors from a script it adds before any of the page's
 * own. This puts a small script at the top of every HTML page the browser
 * tests are served, so the recorder skips that one notice. Every other
 * error is still recorded, and the application itself is untouched.
 */
class IgnoreResizeObserverLoopNotices
{
    /**
     * The script that filters the notice out of Pest's error log.
     */
    private const string SCRIPT = <<<'HTML'
        <script>
            (() => {
                const errors = window.__pestBrowser?.jsErrors;

                if (!errors || errors.__ignoresResizeObserverLoops) {
                    return;
                }

                const isLoopNotice = (entry) => String(entry?.message).startsWith('ResizeObserver loop');
                const push = errors.push.bind(errors);

                errors.push = (...entries) => push(...entries.filter((entry) => !isLoopNotice(entry)));
                errors.__ignoresResizeObserverLoops = true;
            })();
        </script>
        HTML;

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $content = $response->getContent();

        if (! is_string($content) || ! str_contains((string) $response->headers->get('Content-Type'), 'text/html')) {
            return $response;
        }

        $position = stripos($content, '<head>');

        if ($position !== false) {
            $response->setContent(substr_replace($content, '<head>'.self::SCRIPT, $position, strlen('<head>')));
        }

        return $response;
    }
}
