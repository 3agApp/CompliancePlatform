import { Head, Link, usePage } from '@inertiajs/react';
import { ChevronRight, Inbox, Plus, UserPlus } from 'lucide-react';
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
    DashboardPipelineStage,
    DashboardQueue,
    DashboardQueueItem,
    DashboardSupplierProgress,
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
    /** Each supplier's progress; empty for a supplier looking at it. */
    suppliers: DashboardSupplierProgress[];
    /** What is stuck until somebody acts; null for a supplier. */
    attention: DashboardAttention | null;
    activity: DashboardActivityItem[];
    canCreateProduct: boolean;
    canInviteSupplier: boolean;
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
 * Where the whole catalog stands, one tile per stage of the review.
 *
 * Every tile is a link into the list, filtered to exactly the products it
 * counts, and says in a few words whose move those products are.
 */
function StatusTiles({
    stages,
    organizationSlug,
    isSupplier,
}: {
    stages: DashboardPipelineStage[];
    organizationSlug: string;
    isSupplier: boolean;
}) {
    const hints: Record<ProductReviewStatus, string> = isSupplier
        ? {
              draft: t('Not submitted yet'),
              in_review: t('With the distributor'),
              changes_requested: t('Sent back to you'),
              approved: t('Signed off'),
          }
        : {
              draft: t('Suppliers still filling in'),
              in_review: t('Waiting on your decision'),
              changes_requested: t('Back with the supplier'),
              approved: t('Signed off'),
          };

    return (
        <section
            aria-label={t('Products by status')}
            className="grid grid-cols-2 gap-3 xl:grid-cols-4"
            data-test="dashboard-pipeline"
        >
            {stages.map((stage) => (
                <Link
                    key={stage.status}
                    href={
                        productsIndex(organizationSlug, {
                            query: { status: stage.status },
                        }).url
                    }
                    data-test={`dashboard-stage-${stage.status}`}
                    className="workspace-panel hover:border-foreground/30 grid gap-1 px-5 py-4 transition-colors"
                >
                    <span className="text-muted-foreground flex items-center gap-2 text-sm">
                        <span
                            className={cn(
                                'size-2 shrink-0 rounded-full',
                                STAGE_TONES[stage.status],
                            )}
                            aria-hidden
                        />
                        {stage.label}
                    </span>
                    <span className="text-3xl font-semibold tracking-tight tabular-nums">
                        {stage.count}
                    </span>
                    <span className="text-muted-foreground text-xs">
                        {hints[stage.status]}
                    </span>
                </Link>
            ))}
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
    const { sentBack, expiredInvitations, quietSuppliers } = attention;

    if (
        sentBack.total === 0 &&
        expiredInvitations.length === 0 &&
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
                <p className="text-muted-foreground text-sm">
                    {t('Things that are stuck until someone acts.')}
                </p>
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

                {notInvited > 0 ? (
                    <AttentionRow
                        tone="grey"
                        tag={t('Not invited')}
                        title={tc(
                            '1 supplier has not been invited yet|:count suppliers have not been invited yet',
                            notInvited,
                        )}
                        body={t(
                            'They cannot see their products until they are sent an invitation.',
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
    body: string;
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
                <p className="text-muted-foreground text-sm">{body}</p>
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
            <div className="flex items-end justify-between gap-4 border-b px-6 py-4">
                <div>
                    <h2 id="suppliers-heading" className="font-medium">
                        {t('Progress by supplier')}
                    </h2>
                    <p className="text-muted-foreground text-sm">
                        {t('Who is keeping up, and who is not.')}
                    </p>
                </div>
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
                        {t('View all')}
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
    queue,
    suppliers,
    attention,
    activity,
    canCreateProduct,
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
        : [
              {
                  label: t('Active suppliers'),
                  value: stats.activeSuppliers,
                  href: suppliersIndex(organizationSlug).url,
                  testId: 'dashboard-suppliers',
              },
              {
                  label: t('Pending invitations'),
                  value: stats.pendingInvitations,
                  href: suppliersIndex(organizationSlug).url,
                  testId: 'dashboard-pending',
              },
              ...(stats.notInvited > 0
                  ? [
                        {
                            label: t('Not invited yet'),
                            value: stats.notInvited,
                            href: suppliersIndex(organizationSlug).url,
                            testId: 'dashboard-not-invited',
                        },
                    ]
                  : []),
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
                  title: t('Waiting on a distributor'),
                  body: t(
                      'Once a distributor connects with you and assigns products, they will show up here.',
                  ),
                  action: null,
              }
            : null
        : stats.activeSuppliers === 0 &&
            stats.pendingInvitations === 0 &&
            stats.notInvited === 0
          ? {
                title: t('Invite your first supplier'),
                body: t(
                    'Every product is assigned to a supplier, so start by inviting one.',
                ),
                action: {
                    label: t('Invite a supplier'),
                    href: suppliersIndex(organizationSlug).url,
                    testId: 'dashboard-invite-supplier',
                },
            }
          : stats.products === 0
            ? {
                  title: t('Add your first product'),
                  body: t('You have a supplier to assign products to.'),
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
        : 'in_review';

    return (
        <>
            <Head title={t('Dashboard')} />

            <div className="workspace-page">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div className="page-heading">
                        <h1 className="page-title">{t('Dashboard')}</h1>
                        <p className="text-muted-foreground text-sm">
                            {t("You're working in :organization.", {
                                organization: currentOrganization?.name,
                            })}
                        </p>
                    </div>

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
                    <StatusTiles
                        stages={pipeline}
                        organizationSlug={organizationSlug}
                        isSupplier={isSupplier}
                    />
                ) : null}

                <div className="grid items-start gap-4 lg:grid-cols-[minmax(0,1fr)_20rem]">
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
                        {stats.products > 0 ? (
                            <Queue
                                title={
                                    isSupplier
                                        ? t('Your to-do')
                                        : t('Waiting on your review')
                                }
                                queue={queue}
                                emptyTitle={
                                    isSupplier
                                        ? t('Nothing to fill in right now')
                                        : t('Nothing is waiting on your review')
                                }
                                emptyBody={
                                    isSupplier
                                        ? t(
                                              'Products sent back to you, and ones not yet submitted, show up here.',
                                          )
                                        : t(
                                              'Products show up here as soon as a supplier submits them.',
                                          )
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

                    <div
                        className={cn(
                            'grid gap-4',
                            stats.products === 0 &&
                                suppliers.length === 0 &&
                                'sm:grid-cols-2 lg:col-span-2 lg:grid-cols-4',
                        )}
                    >
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
                </div>
            </div>
        </>
    );
}
