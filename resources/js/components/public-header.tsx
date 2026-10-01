import { usePage } from '@inertiajs/react';
import { Globe } from 'lucide-react';
import AppLogoIcon from '@/components/app-logo-icon';
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
                    <div className="flex size-8 items-center justify-center rounded-lg bg-emerald-600 shadow-sm">
                        <AppLogoIcon className="size-5 text-white" />
                    </div>
                    <div>
                        <span className="block text-sm leading-none font-bold tracking-tight text-gray-900">
                            {name}
                        </span>
                        <span className="text-[10px] tracking-wide text-gray-400 uppercase">
                            {t('productCheck')}
                        </span>
                    </div>
                </div>
                <button
                    type="button"
                    onClick={() =>
                        onLocaleChange(locale === 'de' ? 'en' : 'de')
                    }
                    aria-label={t('switchLabel')}
                    data-test="public-locale-toggle"
                    className="flex items-center gap-1.5 rounded-full border border-gray-200 bg-white px-3 py-1.5 text-xs font-medium text-gray-500 transition-all hover:border-gray-300 hover:bg-gray-50 hover:text-gray-800"
                >
                    <Globe className="size-3" />
                    {t('switchTo')}
                </button>
            </div>
        </header>
    );
}
