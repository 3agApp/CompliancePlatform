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
import { destroy } from '@/routes/brands';
import type { Brand } from '@/types';

type Props = {
    organizationSlug: string;
    brand: Brand | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export default function DeleteBrandModal({
    organizationSlug,
    brand,
    open,
    onOpenChange,
}: Props) {
    const [processing, setProcessing] = useState(false);

    /**
     * The server refuses a brand that is still on a product, so the dialog
     * says so up front rather than sending a request it knows will bounce.
     */
    const productCount = brand?.products_count ?? 0;
    const isInUse = productCount > 0;

    const deleteBrand = () => {
        if (!brand || isInUse) {
            return;
        }

        router.visit(destroy([organizationSlug, brand.id]), {
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
            onSuccess: () => onOpenChange(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Delete brand</DialogTitle>
                    <DialogDescription>
                        {isInUse ? (
                            <>
                                <strong>{brand?.name}</strong> is still carried
                                by{' '}
                                {productCount === 1
                                    ? '1 product'
                                    : `${productCount} products`}
                                . Move {productCount === 1 ? 'it' : 'them'} to
                                another brand before deleting it.
                            </>
                        ) : (
                            <>
                                This action cannot be undone. This will
                                permanently delete{' '}
                                <strong>{brand?.name}</strong>.
                            </>
                        )}
                    </DialogDescription>
                </DialogHeader>

                <DialogFooter className="gap-2">
                    <DialogClose asChild>
                        <Button variant="secondary">
                            {isInUse ? 'Close' : 'Cancel'}
                        </Button>
                    </DialogClose>

                    {isInUse ? null : (
                        <Button
                            variant="destructive"
                            data-test="delete-brand-confirm"
                            disabled={processing}
                            onClick={deleteBrand}
                        >
                            Delete brand
                        </Button>
                    )}
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
