import { router } from '@inertiajs/react';

/**
 * The application's own translations, for everything behind the login.
 *
 * Strings are written in English and looked up by that English, the same
 * way Laravel's JSON files work, so `lang/de.json` serves the server and the
 * browser alike and English needs no file at all. A string with no German
 * yet falls back to its English rather than to a key nobody can read.
 *
 * The strings are read once, before the first render, from the page the
 * server sent. They are held here rather than in React state because the
 * React Compiler memoises markup whose inputs look constant, and a `t()`
 * call with a literal looks exactly that -- so instead of re-rendering in
 * a new language, the page reloads whenever the language changes, and
 * every string is read fresh.
 *
 * The public product pages keep their own, smaller dictionary in
 * `public-i18n.ts`: they are read by buyers, not by anybody's account.
 */

export type Locale = 'en' | 'de';

type Replacements = Record<string, string | number | null | undefined>;

let locale: Locale = 'en';
let strings: Record<string, string> = {};

/**
 * Read the language and strings out of the page the server rendered.
 */
export function installTranslations(): void {
    const element = document.querySelector<HTMLScriptElement>(
        'script[data-page="app"]',
    );

    if (element === null) {
        return;
    }

    try {
        const page = JSON.parse(element.textContent ?? '{}') as {
            props?: { locale?: Locale; translations?: Record<string, string> };
        };

        locale = page.props?.locale ?? 'en';
        strings = page.props?.translations ?? {};
    } catch {
        // A page that cannot be read still renders, in English.
    }

    /**
     * Signing in as somebody who reads German, or switching language,
     * changes the locale without a full page load. Reloading is what
     * brings every string on the page across, including the ones in the
     * persistent layout. Listened for on success rather than navigate,
     * because both of those are a form posting back to a page, which
     * finishes a visit without always navigating.
     */
    router.on('success', (event) => {
        const next = event.detail.page.props.locale as Locale | undefined;

        if (next !== undefined && next !== locale) {
            window.location.reload();
        }
    });
}

/**
 * Put the values into a string, Laravel style: `:name` is replaced by the
 * `name` value. Longer names go first, so `:count` is not eaten by `:co`.
 */
function replace(text: string, replacements?: Replacements): string {
    if (!replacements) {
        return text;
    }

    return Object.keys(replacements)
        .sort((a, b) => b.length - a.length)
        .reduce(
            (result, key) =>
                result.replaceAll(`:${key}`, String(replacements[key] ?? '')),
            text,
        );
}

/**
 * Translate a string, filling in any `:placeholders`.
 */
export function t(key: string, replacements?: Replacements): string {
    return replace(strings[key] ?? key, replacements);
}

/**
 * Translate a string that changes with a count.
 *
 * The key holds both forms, `"one|many"`, as Laravel's `trans_choice` reads
 * them; English and German both use the first for exactly one. `:count` is
 * filled in for free.
 */
export function tc(
    key: string,
    count: number,
    replacements?: Replacements,
): string {
    const forms = (strings[key] ?? key).split('|');
    const form = count === 1 ? forms[0] : (forms[1] ?? forms[0]);

    return replace(form, { count, ...replacements });
}

/**
 * The language the page is in.
 */
export function currentLocale(): Locale {
    return locale;
}

/**
 * The locale to format dates and numbers in.
 *
 * German is read in Switzerland here, so its dates come out the Swiss way.
 * English keeps following the browser, which is what it always did.
 */
export function formatLocale(): string | undefined {
    return locale === 'de' ? 'de-CH' : undefined;
}
