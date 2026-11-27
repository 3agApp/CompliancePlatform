import { usePage } from '@inertiajs/react';
import { Globe } from 'lucide-react';
import AppLogoIcon from '@/components/app-logo-icon';
import { PUBLIC_LOCALES } from '@/lib/public-i18n';
import type { PublicLocale, PublicTranslate } from '@/lib/public-i18n';

type Props = {
    locale: PublicLocale;
    onLocaleChange: (locale: PublicLocale) => void;
    t: PublicTranslate;
};

/**
 * The bar across the top of every public page: whose platform this is, and
 * the language switch.
 */
export default function PublicHeader({ locale, onLocaleChange, t }: Props) {
    const { name } = usePage<{ name: string }>().props;

    return (
        <header className="sticky top-0 z-20 border-b border-gray-200/80 bg-white/95 shadow-sm backdrop-blur-md">
            <div className="mx-auto flex max-w-lg items-center justify-between px-4 py-3">
                <div className="flex items-center gap-2.5">
                    <AppLogoIcon className="size-8 shrink-0 text-[#1F2933]" />
                    <div>
                        <span className="block text-sm leading-none font-bold tracking-tight text-gray-900">
                            {name}
                        </span>
                        <span className="text-[10px] tracking-wide text-gray-400 uppercase">
                            {t('productCheck')}
                        </span>
                    </div>
                </div>
                {/*
                 * A plain select rather than a menu: on a phone, where most
                 * of these pages are read, it opens the system's own picker.
                 */}
                <label className="relative flex items-center gap-1.5 rounded-full border border-gray-200 bg-white px-3 py-1.5 text-xs font-medium text-gray-500 transition-all focus-within:ring-2 focus-within:ring-gray-300 hover:border-gray-300 hover:bg-gray-50 hover:text-gray-800">
                    <Globe className="size-3" />
                    <span className="sr-only">{t('language')}</span>
                    <select
                        value={locale}
                        onChange={(event) =>
                            onLocaleChange(event.target.value as PublicLocale)
                        }
                        data-test="public-locale-toggle"
                        className="cursor-pointer appearance-none bg-transparent pr-0.5 outline-none"
                    >
                        {PUBLIC_LOCALES.map((option) => (
                            <option
                                key={option.value}
                                value={option.value}
                                lang={option.value}
                            >
                                {option.label}
                            </option>
                        ))}
                    </select>
                </label>
            </div>
        </header>
    );
}
