import { Form } from '@inertiajs/react';
import { CircleDashed } from 'lucide-react';
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
import { approve } from '@/routes/products';
import type { ProductCompletenessItem, ProductDetail } from '@/types';

type Props = {
    organizationSlug: string;
    product: ProductDetail;
    /** What the template asks for and the product does not yet carry. */
    outstanding: ProductCompletenessItem[];
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /** Swap this dialog for the one that sends the product back. */
    onRequestChanges: () => void;
};

/**
 * Sign off a product its template says is not finished.
 *
 * Approving is what turns the public seal to "Verified", so it is the one
 * move on the page that a buyer ends up reading. A complete product is
 * approved with one click; an incomplete one is approved only after the
 * reviewer has seen, by name, what they are signing off without -- and with
 * sending it back offered right beside, since that is usually the answer.
 */
export default function ApproveProductModal({
    organizationSlug,
    product,
    outstanding,
    open,
    onOpenChange,
    onRequestChanges,
}: Props) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <Form
                    {...approve.form([organizationSlug, product.id])}
                    options={{ preserveScroll: true }}
                    className="space-y-6"
                    onSuccess={() => onOpenChange(false)}
                >
                    {({ processing }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>
                                    Approve {product.name} with{' '}
                                    {outstanding.length === 1
                                        ? 'a requirement'
                                        : `${outstanding.length} requirements`}{' '}
                                    still open?
                                </DialogTitle>
                                <DialogDescription>
                                    Its {product.template_label} template still
                                    asks for the following. Once approved, the
                                    public page shows the product as verified.
                                </DialogDescription>
                            </DialogHeader>

                            <ul
                                className="divide-y rounded-lg border"
                                data-test="approve-outstanding"
                            >
                                {outstanding.map((item) => (
                                    <li
                                        key={item.requirement}
                                        className="flex items-center gap-2 px-3 py-2 text-sm"
                                    >
                                        <CircleDashed
                                            className="size-4 shrink-0 text-amber-600 dark:text-amber-400"
                                            aria-hidden
                                        />
                                        {item.label}
                                    </li>
                                ))}
                            </ul>

                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button variant="ghost">Cancel</Button>
                                </DialogClose>

                                <Button
                                    type="button"
                                    variant="outline"
                                    data-test="approve-request-changes-instead"
                                    onClick={onRequestChanges}
                                >
                                    Request changes instead
                                </Button>

                                <Button
                                    type="submit"
                                    data-test="approve-confirm"
                                    disabled={processing}
                                >
                                    Approve anyway
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
