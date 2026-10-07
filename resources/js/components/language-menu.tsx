import { router, usePage } from '@inertiajs/react';
import { Check, Languages } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenuItem,
    DropdownMenuSub,
    DropdownMenuSubContent,
    DropdownMenuSubTrigger,
} from '@/components/ui/dropdown-menu';
import { currentLocale, t } from '@/lib/i18n';
import type { Locale } from '@/lib/i18n';
import { update } from '@/routes/language';

/**
 * Ask the server to read the application in another language.
 *
 * `null` hands a signed-in person back to their organization's default.
 * The page reloads itself once the new locale arrives, so nothing here has
 * to re-render anything.
 */
export function switchLanguage(locale: Locale | null): void {
    router.patch(
        update.url(),
        { locale },
        { preserveScroll: true, preserveState: false },
    );
}

/**
 * The language choice in the user menu.
 *
 * Three answers rather than two: following the organization is a choice
 * of its own, and the one that keeps a person in step if the organization
 * changes its default later.
 */
export function LanguageSubmenu() {
    const { auth, currentOrganization, availableLocales } = usePage().props;
    const chosen = auth.user.locale ?? null;

    const organizationLocale = currentOrganization?.locale ?? null;
    const organizationLabel = availableLocales.find(
        (option) => option.value === organizationLocale,
    )?.label;

    return (
        <DropdownMenuSub>
            <DropdownMenuSubTrigger data-test="language-menu">
                <Languages className="text-muted-foreground mr-2 size-4" />
                {t('Language')}
            </DropdownMenuSubTrigger>
            <DropdownMenuSubContent>
                {organizationLocale ? (
                    <LanguageItem
                        selected={chosen === null}
                        onSelect={() => switchLanguage(null)}
                        testId="language-organization-default"
                    >
                        {t('Organization default (:language)', {
                            language: organizationLabel,
                        })}
                    </LanguageItem>
                ) : null}

                {availableLocales.map((option) => (
                    <LanguageItem
                        key={option.value}
                        selected={chosen === option.value}
                        onSelect={() => switchLanguage(option.value)}
                        testId={`language-${option.value}`}
                    >
                        {option.label}
                    </LanguageItem>
                ))}
            </DropdownMenuSubContent>
        </DropdownMenuSub>
    );
}

function LanguageItem({
    selected,
    onSelect,
    testId,
    children,
}: {
    selected: boolean;
    onSelect: () => void;
    testId: string;
    children: React.ReactNode;
}) {
    return (
        <DropdownMenuItem
            onSelect={onSelect}
            data-test={testId}
            aria-checked={selected}
            role="menuitemradio"
        >
            <Check
                className={selected ? 'mr-2 size-4' : 'invisible mr-2 size-4'}
            />
            {children}
        </DropdownMenuItem>
    );
}

/**
 * The switch on pages read before signing in: one button that names the
 * other language in that language, so it can be found by somebody who
 * cannot read the current one.
 */
export function LanguageToggle() {
    const { availableLocales } = usePage().props;
    const other = availableLocales.find(
        (option) => option.value !== currentLocale(),
    );

    if (!other) {
        return null;
    }

    return (
        <Button
            variant="ghost"
            size="sm"
            onClick={() => switchLanguage(other.value)}
            data-test="language-toggle"
            lang={other.value}
        >
            <Languages className="size-4" />
            {other.label}
        </Button>
    );
}

/**
 * The language choice on the profile page, laid out like the appearance
 * choice beside it: following the organization first, then each language
 * under its own name.
 */
export function LanguageTabs() {
    const { auth, currentOrganization, availableLocales } = usePage().props;
    const chosen = auth.user.locale ?? null;
    const organizationLabel = availableLocales.find(
        (option) => option.value === currentOrganization?.locale,
    )?.label;

    const options: {
        value: Locale | null;
        label: string;
        hint?: string;
        lang?: string;
    }[] = [
        ...(currentOrganization
            ? [
                  {
                      value: null,
                      label: t('Organization default'),
                      hint: organizationLabel,
                  },
              ]
            : []),
        ...availableLocales.map((option) => ({
            value: option.value,
            label: option.label,
            lang: option.value,
        })),
    ];

    return (
        <div
            className="bg-muted/50 grid w-full gap-2 rounded-xl p-2 sm:grid-cols-3"
            role="radiogroup"
            aria-label={t('Language')}
        >
            {options.map((option) => {
                const selected = chosen === option.value;

                return (
                    <button
                        key={option.value ?? 'organization'}
                        type="button"
                        role="radio"
                        aria-checked={selected}
                        lang={option.lang}
                        data-test={`language-option-${option.value ?? 'organization'}`}
                        onClick={() => switchLanguage(option.value)}
                        className={
                            selected
                                ? 'bg-card text-foreground ring-border focus-visible:ring-ring flex flex-col items-center justify-center gap-0.5 rounded-lg px-3 py-4 shadow-xs ring-1 transition-colors focus-visible:ring-2 focus-visible:outline-none'
                                : 'focus-visible:ring-ring flex flex-col items-center justify-center gap-0.5 rounded-lg px-3 py-4 text-neutral-500 transition-colors hover:bg-neutral-200/60 hover:text-black focus-visible:ring-2 focus-visible:outline-none dark:text-neutral-400 dark:hover:bg-neutral-700/60'
                        }
                    >
                        <span className="text-sm">{option.label}</span>
                        {option.hint ? (
                            <span className="text-xs opacity-70">
                                {option.hint}
                            </span>
                        ) : null}
                    </button>
                );
            })}
        </div>
    );
}
