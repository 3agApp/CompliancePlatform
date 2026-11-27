import { useHttp, usePage } from '@inertiajs/react';
import { Languages, Loader2 } from 'lucide-react';
import { useRef, useState } from 'react';
import { flushSync } from 'react-dom';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { t } from '@/lib/i18n';
import type { Locale } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import type { ProductTranslation, TranslatableField } from '@/types';

export const TRANSLATABLE_FIELDS: TranslatableField[] = [
    'safety_notice',
    'warning_text',
    'material_information',
    'usage_restrictions',
    'safety_instructions',
];

export type TranslatableTextField = {
    name: TranslatableField;
    slug: string;
    label: string;
};

type Source = ProductTranslation['source'];

type SuggestionResponse = {
    translations: Partial<
        Record<Locale, Partial<Record<TranslatableField, string>>>
    >;
    unavailable: 'not_configured' | 'unavailable' | null;
};

type Props = {
    /** The language the original texts are written in, as saved. */
    textLocale: Locale;
    translations: Partial<Record<Locale, ProductTranslation>>;
    /** Where to ask for AI translations; omitted where nobody may ask. */
    suggestUrl?: string;
    disabled: boolean;
    idPrefix: string;
    errors: Record<string, string | undefined>;
    /** The text fields the original shows, which are the ones translated. */
    fields: TranslatableTextField[];
    /** Read the original texts as they stand on the form right now. */
    readOriginals: () => Partial<Record<TranslatableField, string>>;
    /** The original fields, drawn under the "original" tab. */
    children: React.ReactNode;
};

/**
 * The product's safety texts in every language a buyer may read them in.
 *
 * The original is what was always on this form; each other language gets a
 * tab of its own with the same fields, filled by hand or by the AI and
 * saved with the rest of the product. Every tab stays mounted and is only
 * hidden, so what was typed in one is still there, and still sent, after a
 * look at another.
 */
export default function SafetyTextTranslations({
    textLocale: savedTextLocale,
    translations,
    suggestUrl,
    disabled,
    idPrefix,
    errors,
    fields,
    readOriginals,
    children,
}: Props) {
    const { availableLocales } = usePage().props;
    const [textLocale, setTextLocale] = useState<Locale>(savedTextLocale);
    const [active, setActive] = useState<Locale | 'original'>('original');
    const [originals, setOriginals] = useState<
        Partial<Record<TranslatableField, string>>
    >({});
    const [sources, setSources] = useState<Partial<Record<Locale, Source>>>(
        () =>
            Object.fromEntries(
                Object.entries(translations).map(([locale, translation]) => [
                    locale,
                    translation?.source,
                ]),
            ),
    );
    const [notice, setNotice] = useState<string | null>(null);
    /** Outdated translations somebody read again and found still right. */
    const [confirmed, setConfirmed] = useState<Partial<Record<Locale, true>>>(
        {},
    );
    /*
     * A confirmation answers the translations as they were saved; once
     * the page brings back newer ones, it is spent.
     */
    const [confirmedFor, setConfirmedFor] = useState(translations);

    if (confirmedFor !== translations) {
        setConfirmedFor(translations);
        setConfirmed({});
    }

    const confirmations = useRef<
        Partial<Record<Locale, HTMLInputElement | null>>
    >({});

    const confirm = (locale: Locale) => {
        flushSync(() =>
            setConfirmed((current) => ({ ...current, [locale]: true })),
        );
        /* Bubbles to the form, which then knows there is something to save. */
        confirmations.current[locale]?.dispatchEvent(
            new Event('input', { bubbles: true }),
        );
    };

    const isOutdated = (locale: Locale) =>
        Boolean(translations[locale]?.outdated) && !confirmed[locale];

    /**
     * Writing the original in a language it has a translation into puts
     * the original in that translation's place, and saving removes it.
     */
    const replaced =
        textLocale !== savedTextLocale &&
        TRANSLATABLE_FIELDS.some((field) =>
            Boolean(translations[textLocale]?.[field]),
        );

    const refs = useRef<
        Partial<
            Record<
                Locale,
                Partial<Record<TranslatableField, HTMLTextAreaElement>>
            >
        >
    >({});

    /** Set while the AI's answer is written in, so it is not taken for typing. */
    const filling = useRef(false);

    const others = availableLocales.filter(
        (option) => option.value !== textLocale,
    );
    const labelFor = (locale: Locale) =>
        availableLocales.find((option) => option.value === locale)?.label ??
        locale;

    const open = (tab: Locale | 'original') => {
        if (tab !== 'original') {
            setOriginals(readOriginals());
        }

        setActive(tab);
    };

    const http = useHttp<Record<string, never>, SuggestionResponse>({});

    const translate = (targets: Locale[]) => {
        if (suggestUrl === undefined) {
            return;
        }

        const texts = readOriginals();
        setOriginals(texts);
        setNotice(null);

        http.transform(() => ({ from: textLocale, to: targets, texts }));

        void http.post(suggestUrl, {
            onSuccess: (response: SuggestionResponse) => {
                if (response.unavailable !== null) {
                    setNotice(
                        response.unavailable === 'not_configured'
                            ? t(
                                  'Connect an AI provider in the organization settings to use this.',
                              )
                            : t(
                                  'The AI provider did not answer. Try again in a few minutes.',
                              ),
                    );

                    return;
                }

                filling.current = true;

                for (const [locale, texts] of Object.entries(
                    response.translations,
                ) as [Locale, Partial<Record<TranslatableField, string>>][]) {
                    for (const [field, text] of Object.entries(texts) as [
                        TranslatableField,
                        string,
                    ][]) {
                        const element = refs.current[locale]?.[field];

                        if (element) {
                            element.value = text;
                            /*
                             * Bubbles to the form, which is how it learns
                             * there is something unsaved.
                             */
                            element.dispatchEvent(
                                new Event('input', { bubbles: true }),
                            );
                        }
                    }

                    setSources((current) => ({ ...current, [locale]: 'ai' }));
                }

                filling.current = false;
            },
            onError: () =>
                setNotice(
                    t(
                        'The AI provider did not answer. Try again in a few minutes.',
                    ),
                ),
        });
    };

    const tabClass = (selected: boolean) =>
        cn(
            'inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm transition-colors',
            selected
                ? 'bg-background text-foreground shadow-xs'
                : 'text-muted-foreground hover:text-foreground',
        );

    return (
        <div className="grid gap-4">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <div
                    role="tablist"
                    aria-label={t('Language')}
                    className="bg-muted inline-flex flex-wrap gap-1 rounded-lg p-1"
                >
                    <button
                        type="button"
                        role="tab"
                        aria-selected={active === 'original'}
                        data-test="safety-language-original"
                        className={tabClass(active === 'original')}
                        onClick={() => open('original')}
                    >
                        {labelFor(textLocale)}
                        <span className="text-muted-foreground text-xs">
                            {t('Original')}
                        </span>
                    </button>

                    {others.map((option) => {
                        return (
                            <button
                                key={option.value}
                                type="button"
                                role="tab"
                                lang={option.value}
                                aria-selected={active === option.value}
                                data-test={`safety-language-${option.value}`}
                                className={tabClass(active === option.value)}
                                onClick={() => open(option.value)}
                            >
                                {option.label}
                                {isOutdated(option.value) ? (
                                    <span
                                        className="size-1.5 rounded-full bg-amber-500"
                                        aria-label={t('Outdated')}
                                    />
                                ) : null}
                            </button>
                        );
                    })}
                </div>

                {suggestUrl !== undefined && !disabled ? (
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        data-test="safety-translate-all"
                        disabled={http.processing}
                        onClick={() =>
                            translate(others.map((option) => option.value))
                        }
                    >
                        {http.processing ? (
                            <Loader2 className="size-4 animate-spin" />
                        ) : (
                            <Languages className="size-4" />
                        )}
                        {t('Translate all with AI')}
                    </Button>
                ) : null}
            </div>

            {notice !== null ? (
                <p className="text-sm text-amber-700 dark:text-amber-400">
                    {notice}
                </p>
            ) : null}

            <div
                className={active === 'original' ? 'grid gap-4' : 'hidden'}
                role="tabpanel"
            >
                <div className="grid gap-2 sm:max-w-3xs">
                    <Label htmlFor={`${idPrefix}-text-locale`}>
                        {t('Written in')}
                    </Label>
                    <select
                        id={`${idPrefix}-text-locale`}
                        name="text_locale"
                        data-test="product-text-locale"
                        value={textLocale}
                        disabled={disabled}
                        onChange={(event) =>
                            setTextLocale(event.target.value as Locale)
                        }
                        className="border-input bg-background focus-visible:ring-ring/50 h-9 rounded-md border px-3 text-sm shadow-xs outline-none focus-visible:ring-[3px]"
                    >
                        {availableLocales.map((option) => (
                            <option
                                key={option.value}
                                value={option.value}
                                lang={option.value}
                            >
                                {option.label}
                            </option>
                        ))}
                    </select>
                    {replaced ? (
                        <p
                            className="text-sm text-amber-700 dark:text-amber-400"
                            data-test="product-text-locale-replaces"
                        >
                            {t(
                                'Saving removes the :language translation, as the original is now in that language.',
                                { language: labelFor(textLocale) },
                            )}
                        </p>
                    ) : null}
                </div>

                {children}
            </div>

            {others.map((option) => {
                const locale = option.value;
                const saved = translations[locale];
                const shown = active === locale;

                return (
                    <div
                        key={locale}
                        role="tabpanel"
                        lang={locale}
                        data-test={`safety-translation-${locale}`}
                        className={shown ? 'grid gap-4' : 'hidden'}
                    >
                        <div className="flex flex-wrap items-center gap-2">
                            {isOutdated(locale) ? (
                                <>
                                    <Badge
                                        variant="outline"
                                        className="border-amber-300 text-amber-700 dark:text-amber-400"
                                        data-test={`safety-translation-${locale}-outdated`}
                                    >
                                        {t(
                                            'The original has changed since this was translated',
                                        )}
                                    </Badge>
                                    {!disabled ? (
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            data-test={`safety-translation-${locale}-confirm`}
                                            onClick={() => confirm(locale)}
                                        >
                                            {t('Still correct')}
                                        </Button>
                                    ) : null}
                                </>
                            ) : null}
                            {sources[locale] === 'ai' ? (
                                <Badge variant="secondary">
                                    {t('Translated by AI — check it')}
                                </Badge>
                            ) : null}

                            {suggestUrl !== undefined && !disabled ? (
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    className="ml-auto"
                                    data-test={`safety-translate-${locale}`}
                                    disabled={http.processing}
                                    onClick={() => translate([locale])}
                                >
                                    {http.processing ? (
                                        <Loader2 className="size-4 animate-spin" />
                                    ) : (
                                        <Languages className="size-4" />
                                    )}
                                    {t('Translate into :language with AI', {
                                        language: option.label,
                                    })}
                                </Button>
                            ) : null}
                        </div>

                        <input
                            type="hidden"
                            name={`translations[${locale}][source]`}
                            value={sources[locale] ?? 'manual'}
                        />
                        <input
                            ref={(element) => {
                                confirmations.current[locale] = element;
                            }}
                            type="hidden"
                            name={`translations[${locale}][confirmed]`}
                            value={confirmed[locale] ? '1' : '0'}
                        />

                        {fields.length === 0 ? (
                            <p className="text-muted-foreground text-sm">
                                {t(
                                    'Fill in the safety texts in the original language first.',
                                )}
                            </p>
                        ) : null}

                        {fields.map((field) => {
                            const id = `${idPrefix}-${field.slug}-${locale}`;
                            const error =
                                errors[`translations.${locale}.${field.name}`];
                            const original = originals[field.name];

                            return (
                                <div key={field.name} className="grid gap-2">
                                    <Label htmlFor={id}>
                                        {field.label}{' '}
                                        <span className="text-muted-foreground text-xs">
                                            ({option.label})
                                        </span>
                                    </Label>
                                    <Textarea
                                        ref={(element) => {
                                            if (element !== null) {
                                                refs.current[locale] = {
                                                    ...refs.current[locale],
                                                    [field.name]: element,
                                                };
                                            }
                                        }}
                                        id={id}
                                        name={`translations[${locale}][${field.name}]`}
                                        data-test={`product-${field.slug}-${locale}`}
                                        defaultValue={saved?.[field.name] ?? ''}
                                        rows={3}
                                        disabled={disabled}
                                        onInput={() => {
                                            if (!filling.current) {
                                                setSources((current) => ({
                                                    ...current,
                                                    [locale]: 'manual',
                                                }));
                                            }
                                        }}
                                    />
                                    {original ? (
                                        <p
                                            className="text-muted-foreground border-l-2 pl-2 text-xs whitespace-pre-line"
                                            lang={textLocale}
                                        >
                                            {t('Original')}: {original}
                                        </p>
                                    ) : null}
                                    <InputError message={error} />
                                </div>
                            );
                        })}
                    </div>
                );
            })}
        </div>
    );
}
