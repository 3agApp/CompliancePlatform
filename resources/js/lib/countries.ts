/**
 * Country names for ISO 3166-1 alpha-2 codes, in the reader's language.
 *
 * The server only keeps the codes. The browser already knows every
 * country's name in every language, so asking it means the list never has
 * to be translated or kept up to date by hand.
 */
export function countryName(
    code: string | null,
    locale: string,
): string | null {
    if (code === null || code === '') {
        return null;
    }

    try {
        return (
            new Intl.DisplayNames([locale], { type: 'region' }).of(code) ?? code
        );
    } catch {
        return code;
    }
}

/**
 * Turn the codes into select options, sorted by name in the reader's
 * language rather than by code.
 */
export function countryOptions(
    codes: string[],
    locale: string,
): { value: string; label: string }[] {
    const collator = new Intl.Collator(locale);

    return codes
        .map((code) => ({
            value: code,
            label: countryName(code, locale) ?? code,
        }))
        .sort((a, b) => collator.compare(a.label, b.label));
}
