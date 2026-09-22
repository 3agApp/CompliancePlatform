import { Form } from '@inertiajs/react';
import { Check, Send, Undo2 } from 'lucide-react';
import { useState } from 'react';
import ProductReviewStatusBadge from '@/components/product-review-status-badge';
import RequestProductChangesModal from '@/components/request-product-changes-modal';
import { Button } from '@/components/ui/button';
import { approve, submit } from '@/routes/products';
import type { ProductDetail, ProductPermissions } from '@/types';

type Props = {
    organizationSlug: string;
    product: ProductDetail;
    permissions: ProductPermissions;
    /** What the distributor wrote when they last sent the product back. */
    reviewNote: string | null;
};

/**
 * When something last happened to the product, to the day.
 */
function on(timestamp: string | null): string {
    if (timestamp === null) {
        return '';
    }

    return new Date(timestamp).toLocaleDateString(undefined, {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
}

/**
 * Whose move it is, and the move itself.
 *
 * The panel says one thing at a time: a supplier with work outstanding is
 * offered the button that hands it over, and a distributor holding somebody
 * else's homework is offered the two that hand it back. Nobody is shown a
 * button for a move that is not theirs to make -- the server refuses those
 * anyway, and offering one is a way of asking somebody to find that out the
 * hard way.
 */
export default function ProductReviewPanel({
    organizationSlug,
    product,
    permissions,
    reviewNote,
}: Props) {
    const [changesDialogOpen, setChangesDialogOpen] = useState(false);

    const canSubmit =
        permissions.canUpdateProduct &&
        (product.review_status === 'draft' ||
            product.review_status === 'changes_requested');

    const canRule =
        permissions.canReviewProduct && product.review_status === 'in_review';

    return (
        <div
            className="workspace-panel space-y-4 p-5"
            data-test="product-review-panel"
        >
            <div className="flex flex-wrap items-center justify-between gap-2">
                <h2 className="text-sm font-medium">Review</h2>
                <ProductReviewStatusBadge
                    status={product.review_status}
                    label={product.review_status_label}
                />
            </div>

            <p className="text-muted-foreground text-sm leading-relaxed">
                {product.review_status_description}
            </p>

            {/*
             * The note is the instruction for everything the supplier is
             * about to do on this page, so it sits above the button rather
             * than down in the history with the rest of the record.
             */}
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
                    Submitted {on(product.submitted_at)}
                    {product.reviewed_at
                        ? ` · Reviewed ${on(product.reviewed_at)}`
                        : null}
                </p>
            ) : null}

            {canSubmit ? (
                <Form
                    {...submit.form([organizationSlug, product.id])}
                    options={{ preserveScroll: true }}
                >
                    {({ processing }) => (
                        <Button
                            type="submit"
                            className="w-full"
                            data-test="product-submit-review"
                            disabled={processing}
                        >
                            <Send className="h-4 w-4" /> Submit for review
                        </Button>
                    )}
                </Form>
            ) : null}

            {canRule ? (
                <div className="grid gap-2">
                    <Form
                        {...approve.form([organizationSlug, product.id])}
                        options={{ preserveScroll: true }}
                    >
                        {({ processing }) => (
                            <Button
                                type="submit"
                                className="w-full"
                                data-test="product-approve"
                                disabled={processing}
                            >
                                <Check className="h-4 w-4" /> Approve
                            </Button>
                        )}
                    </Form>

                    <Button
                        variant="outline"
                        className="w-full"
                        data-test="product-request-changes"
                        onClick={() => setChangesDialogOpen(true)}
                    >
                        <Undo2 className="h-4 w-4" /> Request changes
                    </Button>
                </div>
            ) : null}

            {/*
             * A supplier looking at a product that is already with the
             * distributor has nothing to press, and should be told that
             * rather than left looking for the button.
             */}
            {!canSubmit && !canRule && product.review_status === 'in_review' ? (
                <p className="text-muted-foreground text-xs">
                    Waiting on the distributor.
                </p>
            ) : null}

            <RequestProductChangesModal
                organizationSlug={organizationSlug}
                product={product}
                open={changesDialogOpen}
                onOpenChange={setChangesDialogOpen}
            />
        </div>
    );
}
