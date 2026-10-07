import { t } from '@/lib/i18n';
import { cn } from '@/lib/utils';

type Props = {
    score: number;
    className?: string;
    /** Show the bar without the reading, for a table cell that is short on room. */
    hideLabel?: boolean;
    /** Stretch the bar across the space it is given, for a panel rather than a cell. */
    fill?: boolean;
};

/**
 * How far along a product is against the template it is held to.
 *
 * Three states rather than a gradient: not started, under way, done. The
 * number already carries the precision, so the colour is left to say which
 * of the three it is -- and a sliver of amber at five percent has to be as
 * visible as one at ninety, or a product nobody has begun reads as a blank
 * cell rather than as work outstanding.
 */
export function completenessTone(score: number): string {
    if (score >= 100) {
        return 'bg-emerald-500 dark:bg-emerald-400';
    }

    if (score > 0) {
        return 'bg-amber-500 dark:bg-amber-400';
    }

    return 'bg-muted-foreground/40';
}

export default function CompletenessMeter({
    score,
    className,
    hideLabel = false,
    fill = false,
}: Props) {
    return (
        <div
            className={cn('flex items-center gap-2', className)}
            data-test="product-completeness"
            title={t(":score% of what this product's template asks for", {
                score,
            })}
        >
            <div
                className={cn(
                    'bg-muted h-1.5 shrink-0 overflow-hidden rounded-full',
                    fill ? 'flex-1' : 'w-16',
                )}
                role="progressbar"
                aria-valuenow={score}
                aria-valuemin={0}
                aria-valuemax={100}
                aria-label={t('Completeness')}
            >
                <div
                    className={cn(
                        'h-full rounded-full',
                        completenessTone(score),
                    )}
                    style={{ width: `${score}%` }}
                />
            </div>

            {hideLabel ? null : (
                <span className="text-muted-foreground text-xs tabular-nums">
                    {score}%
                </span>
            )}
        </div>
    );
}
