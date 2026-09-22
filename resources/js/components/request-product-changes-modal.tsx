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
import { requestChanges } from '@/routes/products';
import type { ProductDetail } from '@/types';

type Props = {
    organizationSlug: string;
    product: ProductDetail;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

/**
 * Hand the product back with a note saying what is still needed.
 *
 * The note is the whole difference between this and simply not approving:
 * the supplier is about to go and do what it says, so it is required, and
 * it is kept in the product's history word for word.
 */
export default function RequestProductChangesModal({
    organizationSlug,
    product,
    open,
    onOpenChange,
}: Props) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <Form
                    {...requestChanges.form([organizationSlug, product.id])}
                    options={{ preserveScroll: true }}
                    resetOnSuccess
                    className="space-y-6"
                    onSuccess={() => onOpenChange(false)}
                >
                    {({ errors, processing }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>
                                    Send {product.name} back?
                                </DialogTitle>
                                <DialogDescription>
                                    {product.counterparty ?? 'The supplier'}{' '}
                                    gets the product back with your note, and
                                    submits it again once they have made the
                                    changes.
                                </DialogDescription>
                            </DialogHeader>

                            <div className="grid gap-2">
                                <Label htmlFor="review-note">
                                    What still needs to change
                                </Label>
                                <Textarea
                                    id="review-note"
                                    name="note"
                                    rows={5}
                                    autoFocus
                                    data-test="review-note"
                                    placeholder="The test report is for the 2021 version. We need the one covering the current article number."
                                />
                                <InputError message={errors.note} />
                            </div>

                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button variant="secondary">Cancel</Button>
                                </DialogClose>

                                <Button
                                    type="submit"
                                    data-test="request-changes-confirm"
                                    disabled={processing}
                                >
                                    Send back
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
