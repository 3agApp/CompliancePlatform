import { Form } from '@inertiajs/react';
import { Check, ChevronRight, RotateCcw, Send, Undo2 } from 'lucide-react';
import { useState } from 'react';
import ApproveProductModal from '@/components/approve-product-modal';
import CompletenessMeter from '@/components/completeness-meter';
import ReopenProductReviewModal from '@/components/reopen-product-review-modal';
import RequestProductChangesModal from '@/components/request-product-changes-modal';
import { Button } from '@/components/ui/button';
import { formatDay } from '@/lib/format';
import { t, tc } from '@/lib/i18n';
import { approve, submit } from '@/routes/products';
import type {
    ProductCompleteness,
    ProductCompletenessItem,
    ProductDetail,
    ProductPermissions,
} from '@/types';

type ActionProps = {
    organizationSlug: string;
    product: ProductDetail;
    permissions: ProductPermissions;
    completeness: ProductCompleteness;
    /** The next product in the viewer's to-do, offered after a submit. */
    nextProduct?: { id: number; name: string } | null;
};

type StatusProps = {
    product: ProductDetail;
    permissions: ProductPermissions;
    /** What the distributor wrote when they last sent the product back. */
    reviewNote: string | null;
    completeness: ProductCompleteness;
    templateLabel: string;
    /** Take the person to wherever an outstanding item is answered. */
    onOpenItem: (item: ProductCompletenessItem) => void;
};

/**
 * Who may make which move, worked out once for both halves of the review.
 */
function reviewMoves(product: ProductDetail, permissions: ProductPermissions) {
    return {
        canSubmit:
            permissions.canUpdateProduct &&
            (product.review_status === 'draft' ||
                product.review_status === 'changes_requested'),
        canRule:
            permissions.canReviewProduct &&
            product.review_status === 'in_review',
        canReopen:
            permissions.canReviewProduct &&
            product.review_status === 'approved',
    };
}

/**
 * Where the review stands and what is still owed, across the top of the
 * product.
 *
 * The note is the instruction for everything the supplier is about to do
 * on the page, and the outstanding items are where that work is, so the
 * two sit together above the tabs rather than down a side rail. Each
 * outstanding item is a way to its own answer.
 */
export default function ProductReviewPanel({
    product,
    permissions,
    reviewNote,
    completeness,
    templateLabel,
    onOpenItem,
}: StatusProps) {
    const { canSubmit, canRule } = reviewMoves(product, permissions);

    const outstanding = completeness.items.filter((item) => !item.satisfied);

    return (
        <section
            aria-label={t('Review')}
            className="workspace-panel grid gap-6 p-5 md:grid-cols-2"
            data-test="product-review-panel"
        >
            <div className="grid content-start gap-2">
                <p className="text-muted-foreground text-sm leading-relaxed">
                    {product.review_status_description}
                </p>

                {reviewNote ? (
                    <blockquote
                        data-test="product-review-note"
                        className="border-l-2 border-amber-500 pl-3 text-sm leading-relaxed whitespace-pre-line dark:border-amber-400"
                    >
                        {reviewNote}
                    </blockquote>
                ) : null}

                {product.submitted_at ? (
                    <p className="text-muted-foreground text-xs">
                        {t('Submitted :date', {
                            date: formatDay(product.submitted_at),
                        })}
                        {product.reviewed_at
                            ? ` · ${t('Reviewed :date', { date: formatDay(product.reviewed_at) })}`
                            : null}
                    </p>
                ) : null}

                {/*
                 * Nothing here blocks the move -- the status says whose turn
                 * it is, not whether the product is done -- but whoever is
                 * about to make it should know what is still open first.
                 */}
                {(canSubmit || canRule) && outstanding.length > 0 ? (
                    <p
                        className="text-sm leading-relaxed text-amber-700 dark:text-amber-400"
                        data-test="product-review-outstanding"
                    >
                        {tc(
                            '1 requirement is still open.|:count requirements are still open.',
                            outstanding.length,
                        )}{' '}
                        {canSubmit
                            ? t(
                                  'You can submit anyway, but the distributor may send it back.',
                              )
                            : t('Check the list before approving.')}
                    </p>
                ) : null}
            </div>

            <div
                className="grid content-start gap-3"
                data-test="product-requirements-summary"
            >
                <div className="flex items-baseline justify-between gap-3">
                    <p className="text-muted-foreground text-xs font-medium tracking-wide uppercase">
                        {t(':template · :score% complete', {
                            template: templateLabel,
                            score: completeness.score,
                        })}
                    </p>
                    {outstanding.length > 0 ? (
                        <span className="text-sm whitespace-nowrap text-amber-700 dark:text-amber-400">
                            {tc(
                                '1 still needed|:count still needed',
                                outstanding.length,
                            )}
                        </span>
                    ) : null}
                </div>

                <CompletenessMeter
                    score={completeness.score}
                    className="w-full"
                    hideLabel
                    fill
                />

                {outstanding.length > 0 ? (
                    <div className="grid gap-2">
                        <p className="text-muted-foreground text-xs font-medium tracking-wide uppercase">
                            {t('Still needed')}
                        </p>
                        <ul className="flex flex-wrap gap-2">
                            {outstanding.map((item) => (
                                <li key={item.requirement}>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        data-test="product-outstanding-item"
                                        className="border-amber-500/40 bg-amber-500/5 hover:bg-amber-500/10"
                                        onClick={() => onOpenItem(item)}
                                    >
                                        {item.label}
                                        {item.weight === 0 ? (
                                            <span className="text-muted-foreground text-xs font-normal">
                                                {t(
                                                    '(does not affect the score)',
                                                )}
                                            </span>
                                        ) : null}
                                    </Button>
                                </li>
                            ))}
                        </ul>
                    </div>
                ) : completeness.items.length > 0 ? (
                    <p className="text-muted-foreground text-sm">
                        {t('Everything the template asks for is in.')}
                    </p>
                ) : null}
            </div>
        </section>
    );
}

/**
 * Whose move it is, and the move itself.
 *
 * Says one thing at a time: a supplier with work outstanding is offered
 * the button that hands it over, and a distributor holding somebody else's
 * homework is offered the two that hand it back. Nobody is shown a button
 * for a move that is not theirs to make -- the server refuses those anyway,
 * and offering one is a way of asking somebody to find that out the hard
 * way.
 */
export function ProductReviewActions({
    organizationSlug,
    product,
    permissions,
    completeness,
    nextProduct = null,
}: ActionProps) {
    const [changesDialogOpen, setChangesDialogOpen] = useState(false);
    const [reopenDialogOpen, setReopenDialogOpen] = useState(false);
    const [approveDialogOpen, setApproveDialogOpen] = useState(false);

    const { canSubmit, canRule, canReopen } = reviewMoves(product, permissions);

    const outstanding = completeness.items.filter((item) => !item.satisfied);

    return (
        <>
            {canSubmit ? (
                <Form
                    {...submit.form([organizationSlug, product.id])}
                    options={{ preserveScroll: true }}
                >
                    {({ processing }) => (
                        <Button
                            type="submit"
                            variant={nextProduct ? 'outline' : 'default'}
                            data-test="product-submit-review"
                            disabled={processing}
                        >
                            <Send className="h-4 w-4" />{' '}
                            {t('Submit for review')}
                        </Button>
                    )}
                </Form>
            ) : null}

            {/*
             * The supplier working down a list: hand this one over and land
             * on the next, without a trip back to the list in between.
             */}
            {canSubmit && nextProduct ? (
                <Form {...submit.form([organizationSlug, product.id])}>
                    {({ processing }) => (
                        <>
                            <input
                                type="hidden"
                                name="next"
                                value={nextProduct.id}
                            />
                            <Button
                                type="submit"
                                data-test="product-submit-and-next"
                                disabled={processing}
                                title={t('Then open :name', {
                                    name: nextProduct.name,
                                })}
                            >
                                {t('Submit and go to next')}
                                <ChevronRight className="h-4 w-4" />
                            </Button>
                        </>
                    )}
                </Form>
            ) : null}

            {canRule ? (
                <>
                    <Button
                        variant="outline"
                        data-test="product-request-changes"
                        onClick={() => setChangesDialogOpen(true)}
                    >
                        <Undo2 className="h-4 w-4" /> {t('Request changes')}
                    </Button>

                    {outstanding.length > 0 ? (
                        <Button
                            data-test="product-approve"
                            onClick={() => setApproveDialogOpen(true)}
                        >
                            <Check className="h-4 w-4" /> {t('Approve')}
                        </Button>
                    ) : (
                        <Form
                            {...approve.form([organizationSlug, product.id])}
                            options={{ preserveScroll: true }}
                        >
                            {({ processing }) => (
                                <Button
                                    type="submit"
                                    data-test="product-approve"
                                    disabled={processing}
                                >
                                    <Check className="h-4 w-4" /> {t('Approve')}
                                </Button>
                            )}
                        </Form>
                    )}
                </>
            ) : null}

            {/*
             * Quieter than the moves above on purpose: this is the way out
             * of a sign-off given by mistake, not a step in the usual run.
             */}
            {canReopen ? (
                <Button
                    variant="outline"
                    data-test="product-reopen-review"
                    onClick={() => setReopenDialogOpen(true)}
                >
                    <RotateCcw className="h-4 w-4" /> {t('Take back approval')}
                </Button>
            ) : null}

            {/*
             * A supplier looking at a product that is already with the
             * distributor has nothing to press, and should be told that
             * rather than left looking for the button.
             */}
            {!canSubmit && !canRule && product.review_status === 'in_review' ? (
                <p className="text-muted-foreground text-sm">
                    {t('Waiting on the distributor.')}
                </p>
            ) : null}

            <ApproveProductModal
                organizationSlug={organizationSlug}
                product={product}
                outstanding={outstanding}
                open={approveDialogOpen}
                onOpenChange={setApproveDialogOpen}
                onRequestChanges={() => {
                    setApproveDialogOpen(false);
                    setChangesDialogOpen(true);
                }}
            />

            <RequestProductChangesModal
                organizationSlug={organizationSlug}
                product={product}
                open={changesDialogOpen}
                onOpenChange={setChangesDialogOpen}
            />

            <ReopenProductReviewModal
                organizationSlug={organizationSlug}
                product={product}
                open={reopenDialogOpen}
                onOpenChange={setReopenDialogOpen}
            />
        </>
    );
}
