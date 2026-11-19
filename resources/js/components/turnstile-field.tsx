import { useEffect, useRef, useState } from 'react';
import InputError from '@/components/input-error';
import type { PublicLocale, PublicTranslate } from '@/lib/public-i18n';

type TurnstileApi = {
    render: (
        container: HTMLElement,
        options: Record<string, unknown>,
    ) => string;
    reset: (widgetId: string) => void;
    remove: (widgetId: string) => void;
};

declare global {
    interface Window {
        turnstile?: TurnstileApi;
    }
}

const SCRIPT_URL =
    'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit';

let loading: Promise<TurnstileApi> | null = null;

/**
 * Load Cloudflare's script once per page, however many widgets ask.
 */
function loadTurnstile(): Promise<TurnstileApi> {
    if (window.turnstile) {
        return Promise.resolve(window.turnstile);
    }

    loading ??= new Promise<TurnstileApi>((resolve, reject) => {
        const script = document.createElement('script');
        script.src = SCRIPT_URL;
        script.async = true;
        script.onload = () =>
            window.turnstile
                ? resolve(window.turnstile)
                : reject(new Error('Turnstile did not start.'));
        script.onerror = () => {
            loading = null;
            reject(new Error('Turnstile could not be loaded.'));
        };
        document.head.appendChild(script);
    });

    return loading;
}

type Props = {
    siteKey: string | null;
    /** What the token is for; the server turns away a token for anything else. */
    action: string;
    /**
     * Bumped to ask for a new token. Cloudflare redeems a token once, right
     * or wrong, so every submit needs a fresh one.
     */
    nonce: number;
    onSolvedChange: (solved: boolean) => void;
    error?: string;
    locale: PublicLocale;
    t: PublicTranslate;
};

/**
 * Cloudflare Turnstile's widget, which puts its token in the form it sits
 * in once it is satisfied a person is there.
 */
export default function TurnstileField({
    siteKey,
    action,
    nonce,
    onSolvedChange,
    error,
    locale,
    t,
}: Props) {
    const container = useRef<HTMLDivElement>(null);
    const widgetId = useRef<string | null>(null);
    const solvedChange = useRef(onSolvedChange);
    const [unavailable, setUnavailable] = useState(false);

    useEffect(() => {
        solvedChange.current = onSolvedChange;
    }, [onSolvedChange]);

    useEffect(() => {
        if (!siteKey || !container.current) {
            return;
        }

        let cancelled = false;
        const element = container.current;

        loadTurnstile()
            .then((turnstile) => {
                if (cancelled) {
                    return;
                }

                widgetId.current = turnstile.render(element, {
                    sitekey: siteKey,
                    action,
                    language: locale,
                    theme: 'light',
                    callback: () => solvedChange.current(true),
                    'expired-callback': () => solvedChange.current(false),
                    'error-callback': () => solvedChange.current(false),
                });
            })
            .catch(() => !cancelled && setUnavailable(true));

        return () => {
            cancelled = true;

            if (widgetId.current && window.turnstile) {
                window.turnstile.remove(widgetId.current);
            }

            widgetId.current = null;
            solvedChange.current(false);
        };
    }, [siteKey, action, locale]);

    useEffect(() => {
        if (nonce === 0 || !widgetId.current || !window.turnstile) {
            return;
        }

        solvedChange.current(false);
        window.turnstile.reset(widgetId.current);
    }, [nonce]);

    return (
        <div className="space-y-2">
            <div
                ref={container}
                className="min-h-[65px]"
                data-test="turnstile-widget"
            />
            <InputError
                message={
                    !siteKey || unavailable ? t('turnstileUnavailable') : error
                }
                data-test="turnstile-error"
            />
        </div>
    );
}
