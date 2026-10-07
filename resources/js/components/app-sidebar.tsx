import { Link, usePage } from '@inertiajs/react';
import {
    Copyright,
    Factory,
    LayoutGrid,
    Mail,
    Package,
    Settings2,
    Tags,
    Truck,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { OrganizationSwitcher } from '@/components/organization-switcher';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { t } from '@/lib/i18n';
import { dashboard, onboarding } from '@/routes';
import { index as invitationsIndex } from '@/routes/invitations';
import { edit as editOrganization } from '@/routes/organizations';
import { index as brandsIndex } from '@/routes/brands';
import { index as categoriesIndex } from '@/routes/categories';
import { index as distributorsIndex } from '@/routes/distributors';
import { index as productsIndex } from '@/routes/products';
import { index as suppliersIndex } from '@/routes/suppliers';
import type { NavItem } from '@/types';

export function AppSidebar() {
    const { currentOrganization, pendingInvitationsCount } = usePage().props;
    const dashboardUrl = currentOrganization
        ? dashboard(currentOrganization.slug)
        : onboarding();

    const mainNavItems: NavItem[] = [
        {
            title: t('Dashboard'),
            href: dashboardUrl,
            icon: LayoutGrid,
            testId: 'nav-dashboard',
        },
    ];

    if (currentOrganization) {
        const isSupplier = currentOrganization.type === 'supplier';

        mainNavItems.push({
            title: isSupplier ? t('Assigned products') : t('Products'),
            href: productsIndex(currentOrganization.slug),
            icon: Package,
            testId: 'nav-products',
        });

        if (!isSupplier) {
            mainNavItems.push({
                title: t('Categories'),
                href: categoriesIndex(currentOrganization.slug),
                icon: Tags,
                testId: 'nav-categories',
            });
        }

        /**
         * Both sides reach the brands: a maker is named under a trade, and
         * it is the supplier's to name even though it lands in the
         * distributor's catalog.
         */
        mainNavItems.push({
            title: t('Brands'),
            href: brandsIndex(currentOrganization.slug),
            icon: Copyright,
            testId: 'nav-brands',
        });

        mainNavItems.push(
            isSupplier
                ? {
                      title: t('Distributors'),
                      href: distributorsIndex(currentOrganization.slug),
                      icon: Truck,
                      testId: 'nav-distributors',
                  }
                : {
                      title: t('Suppliers'),
                      href: suppliersIndex(currentOrganization.slug),
                      icon: Factory,
                      testId: 'nav-suppliers',
                  },
        );

        mainNavItems.push({
            title: t('Organization settings'),
            href: editOrganization(currentOrganization.slug),
            icon: Settings2,
            testId: 'nav-organization-settings',
        });
    }

    if (pendingInvitationsCount > 0) {
        mainNavItems.push({
            title: t('Invitations'),
            href: invitationsIndex(),
            icon: Mail,
            testId: 'nav-invitations',
            badge: pendingInvitationsCount,
        });
    }

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboardUrl} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <OrganizationSwitcher />
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
