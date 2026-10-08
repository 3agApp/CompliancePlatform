import { t } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import type { ProductReviewStatus } from '@/types';

/**
 * The colour of each stage of the review, matched to the status badges so
 * a stage reads the same in a bar as it does on the product.
 */
export const STAGE_TONES: Record<ProductReviewStatus, string> = {
    draft: 'bg-muted-foreground/30',
    in_review: 'bg-sky-500 dark:bg-sky-400',
    changes_requested: 'bg-amber-500 dark:bg-amber-400',
    approved: 'bg-emerald-500 dark:bg-emerald-400',
};

type Props = {
    counts: Record<ProductReviewStatus, number>;
    className?: string;
};

/**
 * How far a set of products has got through the review: a bar of what is
 * signed off, waiting and sent back, with the counts spelled out under it.
 *
 * Drafts are the bar's empty track rather than a segment of their own, so
 * a supplier who has not started reads as an empty bar at a glance.
 */
export default function ReviewProgress({ counts, className }: Props) {
    const total =
        counts.draft +
        counts.in_review +
        counts.changes_requested +
        counts.approved;

    const share = (count: number) => (total === 0 ? 0 : (count / total) * 100);

    const parts = [
        counts.approved > 0
            ? t(':count approved', { count: counts.approved })
            : null,
        counts.in_review > 0
            ? t(':count in review', { count: counts.in_review })
            : null,
        counts.changes_requested > 0
            ? t(':count sent back', { count: counts.changes_requested })
            : null,
        counts.draft > 0 ? t(':count draft', { count: counts.draft }) : null,
    ].filter(Boolean);

    return (
        <div className={cn('grid gap-1.5', className)}>
            <div
                className="bg-muted flex h-1.5 overflow-hidden rounded-full"
                aria-hidden
            >
                {(['approved', 'in_review', 'changes_requested'] as const).map(
                    (status) => (
                        <div
                            key={status}
                            className={STAGE_TONES[status]}
                            style={{ width: `${share(counts[status])}%` }}
                        />
                    ),
                )}
            </div>
            <span className="text-muted-foreground text-xs">
                {parts.length > 0 ? parts.join(' · ') : t('No products yet')}
            </span>
        </div>
    );
}
