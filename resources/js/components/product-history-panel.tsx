import {
    Ban,
    Check,
    CircleDot,
    FilePlus2,
    Globe,
    FileX2,
    Pencil,
    Printer,
    Lock,
    PlusCircle,
    RotateCcw,
    Send,
    ShieldCheck,
    Undo2,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import Heading from '@/components/heading';
import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';
import type { ProductEvent, ProductEventType } from '@/types';

type Props = {
    events: ProductEvent[];
};

const ICONS: Record<ProductEventType, LucideIcon> = {
    created: PlusCircle,
    updated: Pencil,
    document_uploaded: FilePlus2,
    document_removed: FileX2,
    submitted: Send,
    approved: Check,
    changes_requested: Undo2,
    approval_revoked: RotateCcw,
    returned_to_draft: Undo2,
    seal_overridden: ShieldCheck,
    seal_override_cleared: ShieldCheck,
    labels_issued: Printer,
    labels_revoked: Ban,
    document_published: Globe,
    document_unpublished: Lock,
};

/**
 * What an event is drawn with when the server knows a kind this page does
 * not. A history that has recorded something is worth showing whatever it
 * was: a missing icon must never be the reason the page will not render.
 */
const FALLBACK_ICON = CircleDot;

/**
 * When something happened, to the minute.
 *
 * Unlike the date on a document, which is about the paper, this is about the
 * act: two edits a minute apart are a different story from two a month
 * apart, and the order only reads correctly with the time on it.
 */
function happenedAt(timestamp: string | null): string {
    if (timestamp === null) {
        return '';
    }

    return new Date(timestamp).toLocaleString(undefined, {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

/**
 * Say what one field changed from and to, in as few words as it takes.
 *
 * A field that was empty was filled in, one that was emptied was cleared,
 * and anything else is a change from one thing to another. The before and
 * after are both kept, because "who changed the warning text" is usually
 * followed by "what did it say before".
 */
function describe({ from, to }: { from: string | null; to: string | null }) {
    if (from === null && to !== null) {
        return <>set to “{to}”</>;
    }

    if (to === null) {
        return <>cleared</>;
    }

    return (
        <>
            “{from}” → “{to}”
        </>
    );
}

/**
 * Everything that has ever happened to the product, newest first.
 *
 * Two organizations fill in the same record here, so this is the only place
 * that can say who typed a warning text, who filed the test report, and what
 * the distributor said when they sent it back. Nothing in it is ever edited
 * or removed -- that is the whole point of keeping it.
 */
export default function ProductHistoryPanel({ events }: Props) {
    return (
        <div className="workspace-panel space-y-6 p-6">
            <Heading
                variant="small"
                title="History"
                description="Everything that has happened to this product, and who did it."
            />

            {events.length === 0 ? (
                <p className="text-muted-foreground text-sm">
                    Nothing has happened to this product yet.
                </p>
            ) : (
                <ol className="space-y-5" data-test="product-history">
                    {events.map((event) => {
                        const Icon = ICONS[event.type] ?? FALLBACK_ICON;

                        return (
                            <li
                                key={event.id}
                                className="flex gap-3"
                                data-test={`product-history-${event.type}`}
                            >
                                <span
                                    className={cn(
                                        'mt-0.5 flex size-7 shrink-0 items-center justify-center rounded-full border',
                                        event.is_review_step
                                            ? 'bg-primary/10 border-primary/20 text-primary'
                                            : 'bg-muted text-muted-foreground',
                                    )}
                                >
                                    <Icon className="size-3.5" aria-hidden />
                                </span>

                                <div className="min-w-0 flex-1 space-y-1">
                                    <p className="text-sm">
                                        <span className="font-medium">
                                            {event.type_label}
                                        </span>
                                        <span className="text-muted-foreground">
                                            {event.actor
                                                ? ` by ${event.actor}`
                                                : ''}
                                            {event.actor_organization
                                                ? ` (${event.actor_organization})`
                                                : ''}
                                        </span>
                                    </p>

                                    <p className="text-muted-foreground text-xs">
                                        {happenedAt(event.created_at)}
                                    </p>

                                    {event.note ? (
                                        <blockquote className="border-muted mt-2 border-l-2 pl-3 text-sm leading-relaxed whitespace-pre-line">
                                            {event.note}
                                        </blockquote>
                                    ) : null}

                                    {event.changes.length > 0 ? (
                                        <ul className="text-muted-foreground mt-1 space-y-0.5 text-xs">
                                            {event.changes.map((change) => (
                                                <li
                                                    key={change.field}
                                                    className="break-words"
                                                >
                                                    <span className="text-foreground font-medium">
                                                        {change.label}
                                                    </span>{' '}
                                                    {describe(change)}
                                                </li>
                                            ))}
                                        </ul>
                                    ) : null}
                                </div>
                            </li>
                        );
                    })}
                </ol>
            )}
        </div>
    );
}

/**
 * The panel's shape while the history is still on its way.
 *
 * The history is fetched after the page, so the space it will fill is held
 * open rather than left to appear underneath whatever the reader has just
 * scrolled to.
 */
export function ProductHistorySkeleton() {
    return (
        <div className="workspace-panel space-y-6 p-6">
            <Heading
                variant="small"
                title="History"
                description="Everything that has happened to this product, and who did it."
            />

            <div className="space-y-5">
                {[0, 1, 2].map((row) => (
                    <div key={row} className="flex gap-3">
                        <Skeleton className="size-7 shrink-0 rounded-full" />
                        <div className="flex-1 space-y-2">
                            <Skeleton className="h-4 w-48" />
                            <Skeleton className="h-3 w-32" />
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
}
