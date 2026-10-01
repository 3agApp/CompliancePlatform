import { Form } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { reopen } from '@/routes/products';
import type { ProductDetail } from '@/types';

type Props = {
    organizationSlug: string;
    product: ProductDetail;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

/**
 * Take back a sign-off that should not have been given.
 *
 * The public seal stops saying "Verified" the moment this goes through, so
 * the reason is required and kept in the product's history word for word:
 * it is the only answer anybody will have later to why it changed.
 */
export default function ReopenProductReviewModal({
    organizationSlug,
    product,
    open,
    onOpenChange,
}: Props) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <Form
                    {...reopen.form([organizationSlug, product.id])}
                    options={{ preserveScroll: true }}
                    resetOnSuccess
                    className="space-y-6"
                    onSuccess={() => onOpenChange(false)}
                >
                    {({ errors, processing }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>
                                    Take back the approval on {product.name}?
                                </DialogTitle>
                                <DialogDescription>
                                    The product goes back into review and its
                                    public seal stops showing as verified. You
                                    can then approve it again or send it back to{' '}
                                    {product.counterparty ?? 'the supplier'}{' '}
                                    with a note.
                                </DialogDescription>
                            </DialogHeader>

                            <div className="grid gap-2">
                                <Label htmlFor="reopen-note">
                                    Why the approval is being taken back
                                </Label>
                                <Textarea
                                    id="reopen-note"
                                    name="note"
                                    rows={4}
                                    autoFocus
                                    data-test="reopen-note"
                                    placeholder="Approved by mistake: the test report has not been checked yet."
                                />
                                <InputError message={errors.note} />
                            </div>

                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button variant="secondary">Cancel</Button>
                                </DialogClose>

                                <Button
                                    type="submit"
                                    variant="destructive"
                                    data-test="reopen-review-confirm"
                                    disabled={processing}
                                >
                                    Take back approval
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
