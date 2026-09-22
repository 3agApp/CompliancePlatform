import { CheckCircle2, CircleDashed, Clock, Undo2 } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { cn } from '@/lib/utils';
import type { ProductReviewStatus } from '@/types';

type Props = {
    status: ProductReviewStatus;
    label: string;
    className?: string;
};

/**
 * How each state of review reads at a glance.
 *
 * Colour alone does not say it -- half the readers of a compliance catalogue
 * are reading a printout -- so every badge carries its own icon and its own
 * word, and the colour only makes the two easier to find.
 */
const TONES: Record<
    ProductReviewStatus,
    { icon: LucideIcon; className: string }
> = {
    draft: {
        icon: CircleDashed,
        className: 'bg-muted text-muted-foreground border-transparent',
    },
    in_review: {
        icon: Clock,
        className:
            'border-transparent bg-sky-100 text-sky-800 dark:bg-sky-500/15 dark:text-sky-300',
    },
    approved: {
        icon: CheckCircle2,
        className:
            'border-transparent bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300',
    },
    changes_requested: {
        icon: Undo2,
        className:
            'border-transparent bg-amber-100 text-amber-900 dark:bg-amber-500/15 dark:text-amber-300',
    },
};

export default function ProductReviewStatusBadge({
    status,
    label,
    className,
}: Props) {
    const tone = TONES[status];
    const Icon = tone.icon;

    return (
        <span
            data-test="product-review-status"
            data-status={status}
            className={cn(
                'inline-flex w-fit shrink-0 items-center gap-1 rounded-md border px-2 py-0.5 text-xs font-medium whitespace-nowrap',
                tone.className,
                className,
            )}
        >
            <Icon className="size-3" aria-hidden />
            {label}
        </span>
    );
}
