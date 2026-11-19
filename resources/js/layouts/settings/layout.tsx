import { ShieldCheck, Building2, UserRound } from 'lucide-react';
import { Link } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { t } from '@/lib/i18n';
import { cn, toUrl } from '@/lib/utils';
import { edit } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';
import { index as organizations } from '@/routes/organizations';
import type { NavItem } from '@/types';

/**
 * Personal settings first, then the ones shared with everyone in the
 * organization. A function rather than a constant, so the strings are looked
 * up after the translations load.
 */
function navItems(): NavItem[] {
    return [
        { title: t('Profile'), href: edit(), icon: UserRound },
        { title: t('Security'), href: editSecurity(), icon: ShieldCheck },
        { title: t('Organizations'), href: organizations(), icon: Building2 },
    ];
}

export default function SettingsLayout({ children }: PropsWithChildren) {
    const { isCurrentOrParentUrl } = useCurrentUrl();

    return (
        <div className="workspace-page">
            <Heading title={t('Settings')} />

            <div className="grid min-w-0 gap-6 lg:grid-cols-[200px_minmax(0,1fr)] lg:gap-8">
                <aside className="min-w-0">
                    <nav
                        className="workspace-panel flex gap-1 overflow-x-auto p-2 lg:sticky lg:top-6 lg:flex-col"
                        aria-label={t('Settings')}
                    >
                        {navItems().map((item) => (
                            <Button
                                key={toUrl(item.href)}
                                size="sm"
                                variant="ghost"
                                asChild
                                className={cn(
                                    'h-11 shrink-0 justify-start gap-3 px-3 lg:w-full',
                                    {
                                        'bg-muted font-semibold':
                                            isCurrentOrParentUrl(item.href),
                                    },
                                )}
                            >
                                <Link
                                    href={item.href}
                                    aria-current={
                                        isCurrentOrParentUrl(item.href)
                                            ? 'page'
                                            : undefined
                                    }
                                >
                                    {item.icon && (
                                        <item.icon className="h-4 w-4" />
                                    )}
                                    {item.title}
                                </Link>
                            </Button>
                        ))}
                    </nav>
                </aside>

                <div className="workspace-panel w-full max-w-4xl p-5 sm:p-8">
                    <section className="min-w-0 space-y-10">{children}</section>
                </div>
            </div>
        </div>
    );
}
