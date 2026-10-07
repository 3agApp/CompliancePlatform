import { CircleDashed, Clock, ShieldCheck } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { formatLocale, t } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import type { ProductSeal as Seal, ProductSealStatus } from '@/types';

type Props = {
    seal: Seal;
    className?: string;
};

/**
 * How each seal reads: green for a check that passed, amber for one still
 * running, grey for a product no check has got to yet.
 *
 * Colour never carries it alone. Every seal has its own icon and says in
 * words what it means, so it survives a black and white printout, a
 * colour-blind reader and a screen reader alike.
 */
const LOOKS: Record<
    ProductSealStatus,
    { icon: LucideIcon; ring: string; badge: string; bar: string }
> = {
    verified: {
        icon: ShieldCheck,
        ring: 'border-emerald-200 bg-emerald-50 dark:border-emerald-500/30 dark:bg-emerald-500/10',
        badge: 'bg-emerald-600 text-white dark:bg-emerald-500 dark:text-emerald-950',
        bar: 'bg-emerald-500',
    },
    in_progress: {
        icon: Clock,
        ring: 'border-amber-200 bg-amber-50 dark:border-amber-500/30 dark:bg-amber-500/10',
        badge: 'bg-amber-500 text-amber-950',
        bar: 'bg-amber-500',
    },
    not_verified: {
        icon: CircleDashed,
        ring: 'border-border bg-muted/50',
        badge: 'bg-muted-foreground/80 text-background',
        bar: 'bg-muted-foreground/40',
    },
};

/**
 * When a check passed, to the day. A seal is a claim about a date as much
 * as about a product: standards move, and "approved" without a date is a
 * claim nobody can check.
 */
function approvedOn(timestamp: string): string {
    return new Date(timestamp).toLocaleDateString(formatLocale(), {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
}

export default function ProductSealMark({ seal, className }: Props) {
    const look = LOOKS[seal.status];
    const Icon = look.icon;

    return (
        <div
            data-test="product-seal"
            data-seal={seal.status}
            className={cn('rounded-xl border p-5', look.ring, className)}
        >
            <div className="flex items-start gap-3">
                <span
                    className={cn(
                        'flex size-10 shrink-0 items-center justify-center rounded-full',
                        look.badge,
                    )}
                >
                    <Icon className="size-5" aria-hidden />
                </span>

                <div className="min-w-0 flex-1 space-y-1">
                    <p className="leading-none font-semibold">{seal.label}</p>
                    <p className="text-muted-foreground text-sm leading-relaxed">
                        {seal.message}
                    </p>

                    {seal.status === 'verified' && seal.approvedAt ? (
                        <p
                            className="text-muted-foreground text-xs"
                            data-test="product-seal-approved-at"
                        >
                            {t('Approved :date', {
                                date: approvedOn(seal.approvedAt),
                            })}
                        </p>
                    ) : null}
                </div>
            </div>

            {/*
             * The bar belongs to an unfinished check and nowhere else: on a
             * product that has passed, how complete it is is no longer the
             * question, and on one nothing has started there is nothing to
             * draw.
             */}
            {seal.status === 'in_progress' ? (
                <div className="mt-4 space-y-1.5" data-test="product-seal-bar">
                    <div className="flex items-center justify-between text-xs">
                        <span className="text-muted-foreground">
                            {t('Compliance data collected')}
                        </span>
                        <span className="font-medium tabular-nums">
                            {seal.score}%
                        </span>
                    </div>
                    <div
                        className="bg-background h-2 overflow-hidden rounded-full"
                        role="progressbar"
                        aria-valuenow={seal.score}
                        aria-valuemin={0}
                        aria-valuemax={100}
                        aria-label={t('Compliance data collected')}
                    >
                        <div
                            className={cn('h-full rounded-full', look.bar)}
                            style={{ width: `${seal.score}%` }}
                        />
                    </div>
                </div>
            ) : null}

            {/*
             * A seal somebody set by hand is not the outcome of a check, and
             * a public page that let the two read the same would be the one
             * dishonest thing on it.
             */}
            {seal.isOverridden ? (
                <p
                    className="text-muted-foreground mt-4 text-xs"
                    data-test="product-seal-overridden"
                >
                    {t(
                        'Set by the distributor rather than by a completed check.',
                    )}
                </p>
            ) : null}
        </div>
    );
}
