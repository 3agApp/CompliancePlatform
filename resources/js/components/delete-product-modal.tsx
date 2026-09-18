import { router } from '@inertiajs/react';
import { useState } from 'react';
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
import { destroy } from '@/routes/products';
import type { Product } from '@/types';

type Props = {
    organizationSlug: string;
    product: Product | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export default function DeleteProductModal({
    organizationSlug,
    product,
    open,
    onOpenChange,
}: Props) {
    const [processing, setProcessing] = useState(false);

    const deleteProduct = () => {
        if (!product) {
            return;
        }

        router.visit(destroy([organizationSlug, product.id]), {
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
            onSuccess: () => onOpenChange(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Delete product</DialogTitle>
                    <DialogDescription>
                        This action cannot be undone. This will permanently
                        delete <strong>{product?.name}</strong>.
                    </DialogDescription>
                </DialogHeader>

                <DialogFooter className="gap-2">
                    <DialogClose asChild>
                        <Button variant="secondary">Cancel</Button>
                    </DialogClose>

                    <Button
                        variant="destructive"
                        data-test="delete-product-confirm"
                        disabled={processing}
                        onClick={deleteProduct}
                    >
                        Delete product
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
