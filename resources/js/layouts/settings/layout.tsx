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

type NavGroup = {
    title: string;
    /** Shown under the page title while one of the group's pages is open. */
    description: string;
    items: NavItem[];
};

/**
 * Personal settings first, then the ones shared with everyone in the organization,
 * so it is clear which changes affect only you.
 */
/**
 * A function rather than a constant, so the strings are looked up after
 * the translations load.
 */
function navGroups(): NavGroup[] {
    return [
        {
            title: t('Account'),
            description: t(
                'Manage your profile, sign-in, language and appearance',
            ),
            items: [
                {
                    title: t('Profile'),
                    href: edit(),
                    icon: UserRound,
                },
                {
                    title: t('Security'),
                    href: editSecurity(),
                    icon: ShieldCheck,
                },
            ],
        },
        {
            title: t('Workspace'),
            description: t('Manage your organizations and their members'),
            items: [
                {
                    title: t('Organizations'),
                    href: organizations(),
                    icon: Building2,
                },
            ],
        },
    ];
}

export default function SettingsLayout({ children }: PropsWithChildren) {
    const { isCurrentOrParentUrl } = useCurrentUrl();

    const groups = navGroups();

    const activeGroup =
        groups.find((group) =>
            group.items.some((item) => isCurrentOrParentUrl(item.href)),
        ) ?? groups[0];

    return (
        <div className="workspace-page [&>header]:mb-0">
            <Heading
                title={t('Settings')}
                description={activeGroup.description}
            />

            <div className="grid min-w-0 gap-6 lg:grid-cols-[200px_minmax(0,1fr)] lg:gap-8">
                <aside className="min-w-0">
                    <nav
                        className="workspace-panel flex flex-col gap-3 p-2 lg:sticky lg:top-6"
                        aria-label={t('Settings')}
                    >
                        {groups.map((group) => (
                            <div key={group.title} className="grid gap-1">
                                <p className="text-muted-foreground px-3 pt-1 text-xs font-medium tracking-[0.16em] uppercase">
                                    {group.title}
                                </p>
                                <div className="grid grid-cols-2 gap-1 lg:grid-cols-1">
                                    {group.items.map((item, index) => (
                                        <Button
                                            key={`${toUrl(item.href)}-${index}`}
                                            size="sm"
                                            variant="ghost"
                                            asChild
                                            className={cn(
                                                'h-11 w-full justify-start gap-3 px-3',
                                                {
                                                    'bg-muted font-semibold':
                                                        isCurrentOrParentUrl(
                                                            item.href,
                                                        ),
                                                },
                                            )}
                                        >
                                            <Link
                                                href={item.href}
                                                aria-current={
                                                    isCurrentOrParentUrl(
                                                        item.href,
                                                    )
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
                                </div>
                            </div>
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
