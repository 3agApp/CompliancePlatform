import { Head, Link, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { index as distributorsIndex } from '@/routes/distributors';
import { index as productsIndex } from '@/routes/products';
import { index as suppliersIndex } from '@/routes/suppliers';
import type {
    DistributorDashboardStats,
    SupplierDashboardStats,
} from '@/types';

type Props =
    | { viewerType: 'distributor'; stats: DistributorDashboardStats }
    | { viewerType: 'supplier'; stats: SupplierDashboardStats };

type Stat = {
    label: string;
    value: number;
    href: string;
    testId: string;
};

/**
 * One number and where to go to act on it. Every tile is a link, because a
 * count you cannot click through to is a dead end.
 */
function StatTile({ stat }: { stat: Stat }) {
    return (
        <Link
            href={stat.href}
            data-test={stat.testId}
            className="workspace-panel hover:border-primary/40 flex flex-col gap-2 px-6 py-5 transition-colors"
        >
            <span className="text-muted-foreground text-xs font-medium tracking-[0.16em] uppercase">
                {stat.label}
            </span>
            <span className="text-3xl font-semibold tabular-nums">
                {stat.value}
            </span>
        </Link>
    );
}

export default function Dashboard({ viewerType, stats }: Props) {
    const { currentOrganization } = usePage().props;
    const organizationSlug = currentOrganization?.slug ?? '';

    const isSupplier = viewerType === 'supplier';

    const tiles: Stat[] = isSupplier
        ? [
              {
                  label: 'Products assigned to you',
                  value: stats.products,
                  href: productsIndex(organizationSlug).url,
                  testId: 'dashboard-products',
              },
              {
                  label: 'Distributors',
                  value: stats.distributors,
                  href: distributorsIndex(organizationSlug).url,
                  testId: 'dashboard-distributors',
              },
          ]
        : [
              {
                  label: 'Products',
                  value: stats.products,
                  href: productsIndex(organizationSlug).url,
                  testId: 'dashboard-products',
              },
              {
                  label: 'Active suppliers',
                  value: stats.activeSuppliers,
                  href: suppliersIndex(organizationSlug).url,
                  testId: 'dashboard-suppliers',
              },
              {
                  label: 'Pending invitations',
                  value: stats.pendingInvitations,
                  href: suppliersIndex(organizationSlug).url,
                  testId: 'dashboard-pending',
              },
          ];

    /**
     * Only the one step that actually unblocks the organization is offered.
     * A distributor cannot add a product before it has a supplier to assign
     * it to, and a supplier can do nothing at all until a distributor
     * connects with them.
     */
    const nextStep = isSupplier
        ? stats.distributors === 0
            ? {
                  title: 'Waiting on a distributor',
                  body: 'Once a distributor connects with you and assigns products, they will show up here.',
                  action: null,
              }
            : null
        : stats.activeSuppliers === 0 && stats.pendingInvitations === 0
          ? {
                title: 'Invite your first supplier',
                body: 'Every product is assigned to a supplier, so start by inviting one.',
                action: {
                    label: 'Invite a supplier',
                    href: suppliersIndex(organizationSlug).url,
                    testId: 'dashboard-invite-supplier',
                },
            }
          : stats.products === 0
            ? {
                  title: 'Add your first product',
                  body: 'You have a supplier to assign products to.',
                  action: {
                      label: 'Go to products',
                      href: productsIndex(organizationSlug).url,
                      testId: 'dashboard-add-product',
                  },
              }
            : null;

    return (
        <>
            <Head title="Dashboard" />

            <div className="workspace-page">
                <div className="page-heading">
                    <p className="text-muted-foreground text-xs font-medium tracking-[0.16em] uppercase">
                        {isSupplier
                            ? 'Supplier overview'
                            : 'Distributor overview'}
                    </p>
                    <h1 className="page-title">Dashboard</h1>
                    <p className="text-muted-foreground text-sm">
                        You're working in {currentOrganization?.name}.
                    </p>
                </div>

                <div
                    className={`grid gap-4 ${isSupplier ? 'sm:grid-cols-2' : 'sm:grid-cols-3'}`}
                >
                    {tiles.map((tile) => (
                        <StatTile key={tile.testId} stat={tile} />
                    ))}
                </div>

                {nextStep ? (
                    <div
                        className="workspace-panel flex flex-wrap items-center justify-between gap-4 px-6 py-5"
                        data-test="dashboard-next-step"
                    >
                        <div className="min-w-0 space-y-1">
                            <h2 className="font-medium">{nextStep.title}</h2>
                            <p className="text-muted-foreground max-w-xl text-sm leading-relaxed">
                                {nextStep.body}
                            </p>
                        </div>

                        {nextStep.action ? (
                            <Button asChild>
                                <Link
                                    href={nextStep.action.href}
                                    data-test={nextStep.action.testId}
                                >
                                    {nextStep.action.label}
                                </Link>
                            </Button>
                        ) : null}
                    </div>
                ) : null}
            </div>
        </>
    );
}
