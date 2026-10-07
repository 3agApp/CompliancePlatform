import { Head, Link, usePage } from '@inertiajs/react';
import { ChevronRight, Inbox } from 'lucide-react';
import CompletenessMeter from '@/components/completeness-meter';
import ProductReviewStatusBadge from '@/components/product-review-status-badge';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { index as distributorsIndex } from '@/routes/distributors';
import { edit, index as productsIndex } from '@/routes/products';
import { index as suppliersIndex } from '@/routes/suppliers';
import type {
    DashboardPipelineStage,
    DashboardQueue,
    DashboardQueueItem,
    DistributorDashboardStats,
    ProductReviewStatus,
    SupplierDashboardStats,
} from '@/types';

type Props = (
    | { viewerType: 'distributor'; stats: DistributorDashboardStats }
    | { viewerType: 'supplier'; stats: SupplierDashboardStats }
) & {
    pipeline: DashboardPipelineStage[];
    queue: DashboardQueue;
};

type Stat = {
    label: string;
    value: number;
    href: string;
    testId: string;
};

/**
 * The colour of each stage in the pipeline bar, matched to the status
 * badges so a stage reads the same here as it does on the product.
 */
const STAGE_TONES: Record<ProductReviewStatus, string> = {
    draft: 'bg-muted-foreground/30',
    in_review: 'bg-sky-500 dark:bg-sky-400',
    changes_requested: 'bg-amber-500 dark:bg-amber-400',
    approved: 'bg-emerald-500 dark:bg-emerald-400',
};

/**
 * How long ago something happened, in the largest unit that is not zero,
 * because "waiting 12 days" is what a reviewer weighs and the exact minute
 * is not.
 */
function ago(timestamp: string | null): string | null {
    if (timestamp === null) {
        return null;
    }

    const seconds = (new Date(timestamp).getTime() - Date.now()) / 1000;
    const format = new Intl.RelativeTimeFormat(undefined, { numeric: 'auto' });
    const units: [Intl.RelativeTimeFormatUnit, number][] = [
        ['year', 31_536_000],
        ['month', 2_592_000],
        ['week', 604_800],
        ['day', 86_400],
        ['hour', 3_600],
        ['minute', 60],
    ];

    for (const [unit, size] of units) {
        if (Math.abs(seconds) >= size) {
            return format.format(Math.round(seconds / size), unit);
        }
    }

    return format.format(0, 'minute');
}

/**
 * What started the clock on a product in the queue.
 */
const WAITING_SINCE: Record<ProductReviewStatus, string> = {
    in_review: 'Submitted',
    changes_requested: 'Sent back',
    draft: 'Added',
    approved: 'Approved',
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
            className="workspace-panel hover:border-primary/40 flex items-center justify-between gap-4 px-5 py-4 transition-colors"
        >
            <span className="text-muted-foreground text-sm">{stat.label}</span>
            <span className="text-xl font-semibold tabular-nums">
                {stat.value}
            </span>
        </Link>
    );
}

/**
 * Where the whole catalog stands, as one bar.
 *
 * The headline is the share signed off, because that is the number the
 * work is for; the stages under it are links into the list, filtered to
 * exactly the products the stage counts.
 */
function Pipeline({
    stages,
    organizationSlug,
}: {
    stages: DashboardPipelineStage[];
    organizationSlug: string;
}) {
    const total = stages.reduce((sum, stage) => sum + stage.count, 0);
    const approved =
        stages.find((stage) => stage.status === 'approved')?.count ?? 0;

    return (
        <section
            className="workspace-panel space-y-4 px-6 py-5"
            aria-labelledby="pipeline-heading"
            data-test="dashboard-pipeline"
        >
            <div className="flex flex-wrap items-baseline justify-between gap-2">
                <h2 id="pipeline-heading" className="font-medium">
                    Review progress
                </h2>
                <p className="text-muted-foreground text-sm">
                    <span className="text-foreground font-semibold tabular-nums">
                        {approved}
                    </span>{' '}
                    of <span className="tabular-nums">{total}</span> products
                    approved
                </p>
            </div>

            <div
                className="bg-muted flex h-2.5 gap-0.5 overflow-hidden rounded-full"
                aria-hidden
            >
                {stages.map((stage) =>
                    stage.count > 0 ? (
                        <div
                            key={stage.status}
                            className={cn(
                                'h-full min-w-1',
                                STAGE_TONES[stage.status],
                            )}
                            style={{ flexGrow: stage.count }}
                        />
                    ) : null,
                )}
            </div>

            <ul className="flex flex-wrap gap-x-4 gap-y-1">
                {stages.map((stage) => (
                    <li key={stage.status}>
                        <Link
                            href={
                                productsIndex(organizationSlug, {
                                    query: { status: stage.status },
                                }).url
                            }
                            data-test={`dashboard-stage-${stage.status}`}
                            className="hover:bg-muted/60 -mx-2 flex items-center gap-1.5 rounded-md px-2 py-1 text-sm transition-colors"
                        >
                            <span
                                className={cn(
                                    'size-2 shrink-0 rounded-full',
                                    STAGE_TONES[stage.status],
                                )}
                                aria-hidden
                            />
                            <span className="font-medium tabular-nums">
                                {stage.count}
                            </span>
                            <span className="text-muted-foreground">
                                {stage.label}
                            </span>
                        </Link>
                    </li>
                ))}
            </ul>
        </section>
    );
}

/**
 * The products where it is the viewer's move, named one by one, so the
 * dashboard is somewhere to start work rather than a count of it.
 */
function Queue({
    title,
    queue,
    emptyTitle,
    emptyBody,
    viewAllHref,
    showStatus,
    organizationSlug,
}: {
    title: string;
    queue: DashboardQueue;
    emptyTitle: string;
    emptyBody: string;
    viewAllHref: string;
    showStatus: boolean;
    organizationSlug: string;
}) {
    return (
        <section
            className="workspace-panel min-w-0 overflow-hidden"
            aria-labelledby="queue-heading"
            data-test="dashboard-queue"
        >
            <div className="flex items-center justify-between gap-4 border-b px-6 py-4">
                <h2 id="queue-heading" className="font-medium">
                    {title}
                    {queue.total > 0 ? (
                        <span className="text-muted-foreground ml-2 text-sm font-normal tabular-nums">
                            {queue.total}
                        </span>
                    ) : null}
                </h2>

                {queue.total > queue.items.length ? (
                    <Link
                        href={viewAllHref}
                        className="text-muted-foreground hover:text-foreground text-sm"
                        data-test="dashboard-queue-view-all"
                    >
                        View all
                    </Link>
                ) : null}
            </div>

            {queue.items.length === 0 ? (
                <div className="flex items-start gap-3 px-6 py-6">
                    <Inbox className="text-muted-foreground mt-0.5 size-5 shrink-0" />
                    <div className="space-y-1">
                        <p className="text-sm font-medium">{emptyTitle}</p>
                        <p className="text-muted-foreground text-sm">
                            {emptyBody}
                        </p>
                    </div>
                </div>
            ) : (
                <ul className="divide-y">
                    {queue.items.map((item) => (
                        <QueueRow
                            key={item.id}
                            item={item}
                            showStatus={showStatus}
                            organizationSlug={organizationSlug}
                        />
                    ))}
                </ul>
            )}
        </section>
    );
}

function QueueRow({
    item,
    showStatus,
    organizationSlug,
}: {
    item: DashboardQueueItem;
    showStatus: boolean;
    organizationSlug: string;
}) {
    const since = ago(item.since);

    return (
        <li>
            <Link
                href={edit([organizationSlug, item.id])}
                data-test="dashboard-queue-item"
                className="hover:bg-muted/50 flex items-center gap-4 px-6 py-3 transition-colors"
            >
                <span className="min-w-0 flex-1">
                    <span className="block truncate text-sm font-medium">
                        {item.name}
                    </span>
                    <span className="text-muted-foreground block truncate text-xs">
                        {[
                            item.counterparty,
                            since
                                ? `${WAITING_SINCE[item.review_status]} ${since}`
                                : null,
                        ]
                            .filter(Boolean)
                            .join(' · ')}
                    </span>
                </span>

                {showStatus ? (
                    <ProductReviewStatusBadge
                        status={item.review_status}
                        label={item.review_status_label}
                        className="max-sm:hidden"
                    />
                ) : null}

                <CompletenessMeter
                    score={item.completeness_score}
                    className="max-sm:hidden"
                />

                <ChevronRight className="text-muted-foreground size-4 shrink-0" />
            </Link>
        </li>
    );
}

export default function Dashboard({
    viewerType,
    stats,
    pipeline,
    queue,
}: Props) {
    const { currentOrganization } = usePage().props;
    const organizationSlug = currentOrganization?.slug ?? '';

    const isSupplier = viewerType === 'supplier';

    /**
     * What is left once the products have the pipeline to themselves: the
     * relationships the catalog runs through.
     */
    const tiles: Stat[] = isSupplier
        ? [
              {
                  label: 'Distributors',
                  value: stats.distributors,
                  href: distributorsIndex(organizationSlug).url,
                  testId: 'dashboard-distributors',
              },
          ]
        : [
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

    /**
     * A supplier's queue leads with what was sent back, so "view all" goes
     * to those first and to the untouched drafts only once there are none.
     */
    const viewAllStatus: ProductReviewStatus = isSupplier
        ? stats.changesRequested > 0
            ? 'changes_requested'
            : 'draft'
        : 'in_review';

    return (
        <>
            <Head title="Dashboard" />

            <div className="workspace-page">
                <div className="page-heading">
                    <h1 className="page-title">Dashboard</h1>
                    <p className="text-muted-foreground text-sm">
                        You're working in {currentOrganization?.name}.
                    </p>
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

                {stats.products > 0 ? (
                    <Pipeline
                        stages={pipeline}
                        organizationSlug={organizationSlug}
                    />
                ) : null}

                <div className="grid items-start gap-4 lg:grid-cols-[minmax(0,1fr)_18rem]">
                    {stats.products > 0 ? (
                        <Queue
                            title={
                                isSupplier
                                    ? 'Your to-do'
                                    : 'Waiting on your review'
                            }
                            queue={queue}
                            emptyTitle={
                                isSupplier
                                    ? 'Nothing to fill in right now'
                                    : 'Nothing is waiting on your review'
                            }
                            emptyBody={
                                isSupplier
                                    ? 'Products sent back to you, and ones not yet submitted, show up here.'
                                    : 'Products show up here as soon as a supplier submits them.'
                            }
                            viewAllHref={
                                productsIndex(organizationSlug, {
                                    query: { status: viewAllStatus },
                                }).url
                            }
                            showStatus={isSupplier}
                            organizationSlug={organizationSlug}
                        />
                    ) : null}

                    <div
                        className={cn(
                            'grid gap-4',
                            stats.products === 0 &&
                                'sm:grid-cols-2 lg:col-span-2 lg:grid-cols-4',
                        )}
                    >
                        {tiles.map((tile) => (
                            <StatTile key={tile.testId} stat={tile} />
                        ))}
                    </div>
                </div>
            </div>
        </>
    );
}
