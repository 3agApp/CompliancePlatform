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
