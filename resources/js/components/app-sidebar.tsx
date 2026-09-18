import { Link, usePage } from '@inertiajs/react';
import {
    Factory,
    LayoutGrid,
    Mail,
    Package,
    Settings2,
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
import { dashboard, onboarding } from '@/routes';
import { index as invitationsIndex } from '@/routes/invitations';
import { edit as editOrganization } from '@/routes/organizations';
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
            title: 'Dashboard',
            href: dashboardUrl,
            icon: LayoutGrid,
        },
    ];

    if (currentOrganization) {
        const isSupplier = currentOrganization.type === 'supplier';

        mainNavItems.push({
            title: isSupplier ? 'Assigned products' : 'Products',
            href: productsIndex(currentOrganization.slug),
            icon: Package,
        });

        mainNavItems.push(
            isSupplier
                ? {
                      title: 'Distributors',
                      href: distributorsIndex(currentOrganization.slug),
                      icon: Truck,
                  }
                : {
                      title: 'Suppliers',
                      href: suppliersIndex(currentOrganization.slug),
                      icon: Factory,
                  },
        );

        mainNavItems.push({
            title: 'Organization settings',
            href: editOrganization(currentOrganization.slug),
            icon: Settings2,
        });
    }

    if (pendingInvitationsCount > 0) {
        mainNavItems.push({
            title: 'Invitations',
            href: invitationsIndex(),
            icon: Mail,
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
