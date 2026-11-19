import { Link, usePage } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import AppLogoIcon from '@/components/app-logo-icon';
import { LanguageToggle } from '@/components/language-menu';
import { t } from '@/lib/i18n';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

export default function AuthSimpleLayout({ children, title }: AuthLayoutProps) {
    const { name } = usePage().props;

    return (
        <div className="bg-muted/30 flex min-h-svh flex-col justify-center p-5 sm:p-10">
            <main className="mx-auto w-full max-w-md">
                <div className="mb-8 flex items-center justify-between gap-4">
                    <Link
                        href={home()}
                        className="text-muted-foreground hover:text-foreground focus-visible:ring-ring inline-flex items-center gap-2 rounded text-sm focus-visible:ring-2 focus-visible:outline-none"
                    >
                        <ArrowLeft className="size-4" />{' '}
                        {t('Back to :name', { name })}
                    </Link>
                    <LanguageToggle />
                </div>
                <div className="workspace-panel p-6 sm:p-8">
                    <div className="bg-primary text-primary-foreground mb-6 flex size-10 items-center justify-center rounded-xl">
                        <AppLogoIcon className="size-6" />
                    </div>
                    <h1 className="mb-8 text-2xl font-semibold tracking-tight">
                        {title ? t(title) : null}
                    </h1>
                    {children}
                </div>
            </main>
        </div>
    );
}
