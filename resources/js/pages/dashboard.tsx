import { Head, Link, usePage } from '@inertiajs/react';
import {
    ChevronRight,
    FileText,
    Inbox,
    PenLine,
    Plus,
    UserPlus,
} from 'lucide-react';
import CompletenessMeter from '@/components/completeness-meter';
import ProductReviewStatusBadge from '@/components/product-review-status-badge';
import ReviewProgress, { STAGE_TONES } from '@/components/review-progress';
import { Button } from '@/components/ui/button';
import { formatDay, formatRelative } from '@/lib/format';
import { t, tc } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { index as distributorsIndex } from '@/routes/distributors';
import { create, edit, index as productsIndex } from '@/routes/products';
import { index as suppliersIndex } from '@/routes/suppliers';
import type {
    DashboardActivityItem,
    DashboardAttention,
    DashboardInsights,
    DashboardPipelineStage,
    DashboardQueue,
    DashboardQueueItem,
    DashboardSupplierProgress,
    DistributorDashboardStats,
    ProductRequirementKey,
    ProductReviewStatus,
    SupplierDashboardStats,
} from '@/types';

type Props = (
    | { viewerType: 'distributor'; stats: DistributorDashboardStats }
    | { viewerType: 'supplier'; stats: SupplierDashboardStats }
) & {
    pipeline: DashboardPipelineStage[];
    insights: DashboardInsights;
    queue: DashboardQueue;
    /** Each supplier's progress; empty for a supplier looking at it. */
    suppliers: DashboardSupplierProgress[];
    /** What is stuck until somebody acts; null for a supplier. */
    attention: DashboardAttention | null;
    activity: DashboardActivityItem[];
    canCreateProduct: boolean;
    canUpdateProduct: boolean;
    canInviteSupplier: boolean;
};

/**
 * The bulk change on the product list that answers a gap, where one does:
 * the details a whole series shares can be filled in for all of it at once.
 */
const FILL_FOR: Partial<Record<ProductRequirementKey, 'safety' | 'country'>> = {
    requires_age_grading: 'safety',
    requires_warning_text: 'safety',
    requires_safety_notice: 'safety',
    requires_material_information: 'safety',
    requires_safety_instructions: 'safety',
    requires_usage_restrictions: 'safety',
    requires_customs_tariff_number: 'safety',
    requires_country_of_origin: 'country',
};

type Stat = {
    label: string;
    value: number;
    href: string;
    testId: string;
};

/**
 * What started the clock on a product in the queue, and how long ago.
 */
function waitingSince(status: ProductReviewStatus, when: string): string {
    switch (status) {
        case 'in_review':
            return t('Submitted :when', { when });
        case 'changes_requested':
            return t('Sent back :when', { when });
        case 'approved':
            return t('Approved :when', { when });
        default:
            return t('Added :when', { when });
    }
}

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
 * Where the whole catalog stands: how many of the products not yet
 * submitted are ready to go, and one bar across every stage of the review.
 *
 * Each stage in the legend is a link into the list, filtered to exactly the
 * products it counts.
 */
function Readiness({
    stages,
    insights,
    organizationSlug,
}: {
    stages: DashboardPipelineStage[];
    insights: DashboardInsights;
    organizationSlug: string;
}) {
    const counts = Object.fromEntries(
        stages.map((stage) => [stage.status, stage.count]),
    ) as Record<ProductReviewStatus, number>;

    return (
        <section
            aria-label={t('Products by status')}
            className="workspace-panel grid gap-4 px-6 py-5"
            data-test="dashboard-pipeline"
        >
            <div className="flex flex-wrap items-end justify-between gap-3">
                <p data-test="dashboard-readiness">
                    <span className="text-3xl font-semibold tracking-tight tabular-nums">
                        {insights.ready}
                    </span>{' '}
                    <span className="text-muted-foreground">
                        {tc(
                            'of 1 open product ready to submit|of :count open products ready to submit',
                            insights.open,
                        )}
                    </span>
                </p>
                {insights.open > 0 ? (
                    <span className="text-muted-foreground text-sm">
                        {t('Average completeness :score%', {
                            score: insights.averageCompleteness,
                        })}
                    </span>
                ) : null}
            </div>

            <ReviewProgress counts={counts} />

            <ul className="flex flex-wrap gap-x-6 gap-y-2">
                {stages.map((stage) => (
                    <li key={stage.status}>
                        <Link
                            href={
                                productsIndex(organizationSlug, {
                                    query: { status: stage.status },
                                }).url
                            }
                            data-test={`dashboard-stage-${stage.status}`}
                            className="group flex items-center gap-2 text-sm"
                        >
                            <span
                                className={cn(
                                    'size-2 shrink-0 rounded-full',
                                    STAGE_TONES[stage.status],
                                )}
                                aria-hidden
                            />
                            <span className="group-hover:underline">
                                {stage.label}
                            </span>
                            <span className="font-medium tabular-nums">
                                {stage.count}
                            </span>
                        </Link>
                    </li>
                ))}
            </ul>
        </section>
    );
}

/**
 * What holds the most unsubmitted products back, with the way to fix it
 * for all of them at once where there is one.
 *
 * A series of products usually shares its safety details, so the answer is
 * typed once on the list rather than once per product.
 */
function Gaps({
    insights,
    organizationSlug,
    canUpdate,
}: {
    insights: DashboardInsights;
    organizationSlug: string;
    canUpdate: boolean;
}) {
    if (insights.gaps.length === 0) {
        return null;
    }

    return (
        <section
            className="workspace-panel min-w-0 overflow-hidden"
            aria-labelledby="gaps-heading"
            data-test="dashboard-gaps"
        >
            <div className="border-b px-6 py-4">
                <h2 id="gaps-heading" className="font-medium">
                    {t("What's holding products back")}
                </h2>
            </div>

            <ul className="divide-y">
                {insights.gaps.map((gap) => {
                    const fill = canUpdate
                        ? FILL_FOR[gap.requirement]
                        : undefined;
                    const Icon = gap.group === 'document' ? FileText : PenLine;

                    return (
                        <li
                            key={gap.requirement}
                            className="flex flex-wrap items-center gap-x-4 gap-y-2 px-6 py-3"
                            data-test="dashboard-gap"
                        >
                            <Icon
                                className={cn(
                                    'size-4 shrink-0',
                                    gap.group === 'document'
                                        ? 'text-red-600 dark:text-red-400'
                                        : 'text-amber-600 dark:text-amber-400',
                                )}
                                aria-hidden
                            />
                            <span className="min-w-0 flex-1 text-sm font-medium">
                                {gap.label}
                            </span>
                            <span className="text-muted-foreground text-sm tabular-nums">
                                {tc(
                                    'Missing on 1 of :total|Missing on :count of :total',
                                    gap.count,
                                    { total: insights.open },
                                )}
                            </span>
                            <Button variant="outline" size="sm" asChild>
                                <Link
                                    href={
                                        productsIndex(organizationSlug, {
                                            query: {
                                                missing: gap.requirement,
                                                ...(fill
                                                    ? { fill, per_page: 100 }
                                                    : {}),
                                            },
                                        }).url
                                    }
                                    data-test={
                                        fill
                                            ? 'dashboard-gap-fill'
                                            : 'dashboard-gap-view'
                                    }
                                >
                                    {fill
                                        ? t('Fill for all')
                                        : t('View products')}
                                </Link>
                            </Button>
                        </li>
                    );
                })}
            </ul>
        </section>
    );
}

/**
 * What is stuck until somebody acts, each with the step that unsticks it.
 *
 * Nothing at all when nothing is stuck: an empty list of problems is not
 * worth the space.
 */
function Attention({
    attention,
    notInvited,
    organizationSlug,
}: {
    attention: DashboardAttention;
    notInvited: number;
    organizationSlug: string;
}) {
    const { sentBack, expiredInvitations, waitingSuppliers, quietSuppliers } =
        attention;

    if (
        sentBack.total === 0 &&
        expiredInvitations.length === 0 &&
        waitingSuppliers.length === 0 &&
        quietSuppliers.length === 0 &&
        notInvited === 0
    ) {
        return null;
    }

    const suppliersUrl = suppliersIndex(organizationSlug).url;

    return (
        <section
            className="workspace-panel min-w-0 overflow-hidden"
            aria-labelledby="attention-heading"
            data-test="dashboard-attention"
        >
            <div className="border-b px-6 py-4">
                <h2 id="attention-heading" className="font-medium">
                    {t('Needs your attention')}
                </h2>
            </div>

            <ul className="divide-y">
                {expiredInvitations.length > 0 ? (
                    <AttentionRow
                        tone="red"
                        tag={t('Invitation expired')}
                        title={tc(
                            '1 supplier never joined|:count suppliers never joined',
                            expiredInvitations.length,
                        )}
                        body={t(
                            ':names hold :count products. Nobody can fill those in until the invitation is accepted.',
                            {
                                names: expiredInvitations
                                    .map((supplier) => supplier.label)
                                    .join(', '),
                                count: expiredInvitations.reduce(
                                    (sum, supplier) => sum + supplier.products,
                                    0,
                                ),
                            },
                        )}
                        action={{
                            label: t('Resend from Suppliers'),
                            href: suppliersUrl,
                        }}
                        testId="dashboard-attention-expired"
                    />
                ) : null}

                {waitingSuppliers.map((supplier) => (
                    <AttentionRow
                        key={supplier.id}
                        tone="amber"
                        tag={t("Hasn't joined yet")}
                        title={supplier.label}
                        body={tc(
                            '1 draft is waiting on them. You can fill it in yourself instead.|:count drafts are waiting on them. You can fill them in yourself instead.',
                            supplier.draft,
                        )}
                        action={{
                            label: t('Fill in myself'),
                            href: productsIndex(organizationSlug, {
                                query: {
                                    connection: supplier.id,
                                    status: 'draft',
                                },
                            }).url,
                        }}
                        testId="dashboard-attention-waiting"
                    />
                ))}

                {notInvited > 0 ? (
                    <AttentionRow
                        tone="grey"
                        tag={t('Not invited')}
                        title={tc(
                            '1 supplier has not been invited yet|:count suppliers have not been invited yet',
                            notInvited,
                        )}
                        action={{
                            label: t('Go to suppliers'),
                            href: suppliersUrl,
                        }}
                        testId="dashboard-attention-not-invited"
                    />
                ) : null}

                {sentBack.items.map((product) => (
                    <AttentionRow
                        key={product.id}
                        tone="amber"
                        tag={t('Changes requested')}
                        title={product.name}
                        titleHref={edit([organizationSlug, product.id]).url}
                        body={[
                            product.counterparty
                                ? t('Waiting on :name', {
                                      name: product.counterparty,
                                  })
                                : null,
                            product.since
                                ? t('Sent back :when', {
                                      when: formatRelative(product.since),
                                  })
                                : null,
                        ]
                            .filter(Boolean)
                            .join(' · ')}
                        testId="dashboard-attention-sent-back"
                    />
                ))}

                {sentBack.total > sentBack.items.length ? (
                    <li className="px-6 py-3">
                        <Link
                            href={
                                productsIndex(organizationSlug, {
                                    query: { status: 'changes_requested' },
                                }).url
                            }
                            className="text-muted-foreground hover:text-foreground text-sm"
                        >
                            {t('All :count sent back', {
                                count: sentBack.total,
                            })}
                        </Link>
                    </li>
                ) : null}

                {quietSuppliers.map((supplier) => (
                    <AttentionRow
                        key={supplier.id}
                        tone="grey"
                        tag={t('No activity')}
                        title={supplier.label}
                        body={
                            supplier.lastActivity
                                ? tc(
                                      '1 draft untouched since :date|:count drafts untouched since :date',
                                      supplier.draft,
                                      {
                                          date: formatDay(
                                              supplier.lastActivity,
                                          ),
                                      },
                                  )
                                : tc(
                                      '1 draft not started|:count drafts not started',
                                      supplier.draft,
                                  )
                        }
                        action={{
                            label: t('View drafts'),
                            href: productsIndex(organizationSlug, {
                                query: {
                                    connection: supplier.id,
                                    status: 'draft',
                                },
                            }).url,
                        }}
                        testId="dashboard-attention-quiet"
                    />
                ))}
            </ul>
        </section>
    );
}

const TAG_TONES = {
    red: 'bg-red-500/10 text-red-700 dark:text-red-400',
    amber: 'bg-amber-500/15 text-amber-700 dark:text-amber-400',
    grey: 'bg-muted text-muted-foreground',
};

function AttentionRow({
    tone,
    tag,
    title,
    titleHref,
    body,
    action,
    testId,
}: {
    tone: keyof typeof TAG_TONES;
    tag: string;
    title: string;
    titleHref?: string;
    body?: string;
    action?: { label: string; href: string };
    testId: string;
}) {
    return (
        <li
            className="flex flex-wrap items-center justify-between gap-3 px-6 py-3.5"
            data-test={testId}
        >
            <div className="grid min-w-0 gap-1">
                <div className="flex flex-wrap items-center gap-2">
                    <span
                        className={cn(
                            'rounded-full px-2 py-0.5 text-xs font-medium',
                            TAG_TONES[tone],
                        )}
                    >
                        {tag}
                    </span>
                    {titleHref ? (
                        <Link
                            href={titleHref}
                            className="text-sm font-medium hover:underline"
                        >
                            {title}
                        </Link>
                    ) : (
                        <span className="text-sm font-medium">{title}</span>
                    )}
                </div>
                {body ? (
                    <p className="text-muted-foreground text-sm">{body}</p>
                ) : null}
            </div>

            {action ? (
                <Button variant="outline" size="sm" asChild>
                    <Link href={action.href}>{action.label}</Link>
                </Button>
            ) : null}
        </li>
    );
}

const SUPPLIER_STATUS_LABELS = {
    invited: () => t('Invitation pending'),
    expired: () => t('Invitation expired'),
    not_invited: () => t('Not invited'),
};

/**
 * Each supplier's share of the catalog and how far through the review it
 * is, so the one falling behind stands out without opening the list.
 */
function SupplierProgress({
    suppliers,
    organizationSlug,
}: {
    suppliers: DashboardSupplierProgress[];
    organizationSlug: string;
}) {
    return (
        <section
            className="workspace-panel min-w-0 overflow-hidden"
            aria-labelledby="suppliers-heading"
            data-test="dashboard-supplier-progress"
        >
            <div className="flex items-center justify-between gap-4 border-b px-6 py-4">
                <h2 id="suppliers-heading" className="font-medium">
                    {t('Progress by supplier')}
                </h2>
                <Link
                    href={suppliersIndex(organizationSlug).url}
                    className="text-muted-foreground hover:text-foreground text-sm"
                >
                    {t('All suppliers')}
                </Link>
            </div>

            <ul className="divide-y">
                {suppliers.map((supplier) => {
                    return (
                        <li
                            key={supplier.id}
                            className="grid items-center gap-x-6 gap-y-2 px-6 py-3.5 sm:grid-cols-[minmax(0,1.3fr)_minmax(0,1.4fr)_6rem]"
                            data-test="dashboard-supplier-row"
                        >
                            <div className="grid min-w-0 gap-0.5">
                                <Link
                                    href={
                                        productsIndex(organizationSlug, {
                                            query: { connection: supplier.id },
                                        }).url
                                    }
                                    className="truncate text-sm font-medium hover:underline"
                                >
                                    {supplier.label}
                                </Link>
                                <span className="text-muted-foreground text-xs">
                                    {tc(
                                        '1 product|:count products',
                                        supplier.products,
                                    )}
                                    {supplier.status !== 'active' ? (
                                        <span
                                            className={cn(
                                                'ml-2',
                                                supplier.status === 'expired' &&
                                                    'text-red-700 dark:text-red-400',
                                            )}
                                        >
                                            {SUPPLIER_STATUS_LABELS[
                                                supplier.status
                                            ]()}
                                        </span>
                                    ) : null}
                                </span>
                            </div>

                            <ReviewProgress
                                counts={{
                                    draft: supplier.draft,
                                    in_review: supplier.inReview,
                                    changes_requested:
                                        supplier.changesRequested,
                                    approved: supplier.approved,
                                }}
                            />

                            <span className="text-muted-foreground text-sm sm:text-right">
                                {formatRelative(supplier.lastActivity) ?? '—'}
                            </span>
                        </li>
                    );
                })}
            </ul>
        </section>
    );
}

/**
 * The latest things to happen to any product the viewer can see.
 */
function Activity({
    activity,
    organizationSlug,
}: {
    activity: DashboardActivityItem[];
    organizationSlug: string;
}) {
    return (
        <section
            className="workspace-panel min-w-0 overflow-hidden"
            aria-labelledby="activity-heading"
            data-test="dashboard-activity"
        >
            <div className="border-b px-5 py-4">
                <h2 id="activity-heading" className="font-medium">
                    {t('Recent activity')}
                </h2>
            </div>

            {activity.length === 0 ? (
                <p className="text-muted-foreground px-5 py-4 text-sm">
                    {t('Nothing has happened yet.')}
                </p>
            ) : (
                <ol className="divide-y">
                    {activity.map((event) => (
                        <li key={event.id} className="grid gap-0.5 px-5 py-3">
                            <span className="text-sm">
                                <span className="font-medium">
                                    {event.type_label}
                                </span>{' '}
                                ·{' '}
                                {event.count > 1 ? (
                                    <span data-test="dashboard-activity-run">
                                        {tc(
                                            '1 product|:count products',
                                            event.count,
                                        )}
                                    </span>
                                ) : (
                                    <Link
                                        href={
                                            edit([
                                                organizationSlug,
                                                event.product.id,
                                            ]).url
                                        }
                                        className="hover:underline"
                                    >
                                        {event.product.name}
                                    </Link>
                                )}
                            </span>
                            <span className="text-muted-foreground text-xs">
                                {[event.actor, formatRelative(event.created_at)]
                                    .filter(Boolean)
                                    .join(' · ')}
                            </span>
                        </li>
                    ))}
                </ol>
            )}
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
    viewAllHref,
    showStatus,
    organizationSlug,
}: {
    title: string;
    queue: DashboardQueue;
    emptyTitle: string;
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
                        {t('View all')}
                    </Link>
                ) : null}
            </div>

            {queue.items.length === 0 ? (
                <div className="flex items-center gap-3 px-6 py-6">
                    <Inbox className="text-muted-foreground size-5 shrink-0" />
                    <p className="text-sm font-medium">{emptyTitle}</p>
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
    const since = formatRelative(item.since);

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
                                ? waitingSince(item.review_status, since)
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
    insights,
    queue,
    suppliers,
    attention,
    activity,
    canCreateProduct,
    canUpdateProduct,
    canInviteSupplier,
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
                  label: t('Distributors'),
                  value: stats.distributors,
                  href: distributorsIndex(organizationSlug).url,
                  testId: 'dashboard-distributors',
              },
          ]
        : /**
           * A distributor's suppliers are covered, in more detail, by the
           * attention list and the progress table, so they get no tiles.
           */
          [];

    /**
     * Only the one step that actually unblocks the organization is offered.
     * A distributor cannot add a product before it has a supplier to assign
     * it to, and a supplier can do nothing at all until a distributor
     * connects with them.
     */
    const nextStep = isSupplier
        ? stats.distributors === 0
            ? {
                  title: t('Waiting on a distributor'),
                  action: null,
              }
            : null
        : stats.activeSuppliers === 0 &&
            stats.pendingInvitations === 0 &&
            stats.notInvited === 0
          ? {
                title: t('Invite your first supplier'),
                action: {
                    label: t('Invite a supplier'),
                    href: suppliersIndex(organizationSlug).url,
                    testId: 'dashboard-invite-supplier',
                },
            }
          : stats.products === 0
            ? {
                  title: t('Add your first product'),
                  action: {
                      label: t('Go to products'),
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
        : stats.awaitingReview > 0
          ? 'in_review'
          : 'draft';

    return (
        <>
            <Head title={t('Dashboard')} />

            <div className="workspace-page">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <h1 className="page-title">{t('Dashboard')}</h1>

                    {canInviteSupplier || canCreateProduct ? (
                        <div className="flex flex-wrap gap-2">
                            {canInviteSupplier ? (
                                <Button variant="outline" asChild>
                                    <Link
                                        href={suppliersIndex(organizationSlug)}
                                        data-test="dashboard-invite-supplier-button"
                                    >
                                        <UserPlus /> {t('Invite supplier')}
                                    </Link>
                                </Button>
                            ) : null}
                            {canCreateProduct ? (
                                <Button asChild>
                                    <Link
                                        href={create(organizationSlug)}
                                        data-test="dashboard-new-product-button"
                                    >
                                        <Plus /> {t('New product')}
                                    </Link>
                                </Button>
                            ) : null}
                        </div>
                    ) : null}
                </div>

                {nextStep ? (
                    <div
                        className="workspace-panel flex flex-wrap items-center justify-between gap-4 px-6 py-5"
                        data-test="dashboard-next-step"
                    >
                        <h2 className="min-w-0 font-medium">
                            {nextStep.title}
                        </h2>

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
                    <Readiness
                        stages={pipeline}
                        insights={insights}
                        organizationSlug={organizationSlug}
                    />
                ) : null}

                <div
                    className={cn(
                        'grid items-start gap-4',
                        (tiles.length > 0 || stats.products > 0) &&
                            'lg:grid-cols-[minmax(0,1fr)_20rem]',
                    )}
                >
                    {/*
                     * The review queue leads while it holds anything, since
                     * that is the distributor's own move; when it is empty,
                     * what is stuck elsewhere goes first.
                     */}
                    <div
                        className={cn(
                            'grid min-w-0 gap-4',
                            queue.total === 0 &&
                                '[&>[data-test=dashboard-attention]]:order-first',
                        )}
                    >
                        <Gaps
                            insights={insights}
                            organizationSlug={organizationSlug}
                            canUpdate={canUpdateProduct}
                        />

                        {/*
                         * A distributor's empty queue says nothing the gaps
                         * and the readiness above do not, so it only shows
                         * once there is something in it.
                         */}
                        {stats.products > 0 &&
                        (isSupplier || queue.total > 0) ? (
                            <Queue
                                title={
                                    isSupplier
                                        ? t('Your to-do')
                                        : t('Waiting on you')
                                }
                                queue={queue}
                                emptyTitle={
                                    isSupplier
                                        ? t('Nothing to fill in right now')
                                        : t('Nothing is waiting on your review')
                                }

                                viewAllHref={
                                    productsIndex(organizationSlug, {
                                        query: { status: viewAllStatus },
                                    }).url
                                }
                                showStatus
                                organizationSlug={organizationSlug}
                            />
                        ) : null}

                        {attention !== null && !isSupplier ? (
                            <Attention
                                attention={attention}
                                notInvited={stats.notInvited}
                                organizationSlug={organizationSlug}
                            />
                        ) : null}

                        {suppliers.length > 0 ? (
                            <SupplierProgress
                                suppliers={suppliers}
                                organizationSlug={organizationSlug}
                            />
                        ) : null}
                    </div>

                    {/*
                     * Only laid out when there is something to put in it:
                     * before the first product a distributor has no tiles
                     * and no activity, and an empty column reads as a page
                     * that has not finished loading.
                     */}
                    {tiles.length > 0 || stats.products > 0 ? (
                        <div className="grid gap-4">
                            {tiles.map((tile) => (
                                <StatTile key={tile.testId} stat={tile} />
                            ))}

                            {stats.products > 0 ? (
                                <Activity
                                    activity={activity}
                                    organizationSlug={organizationSlug}
                                />
                            ) : null}
                        </div>
                    ) : null}
                </div>
            </div>
        </>
    );
}
