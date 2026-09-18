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
import { destroy } from '@/routes/categories';
import type { ProductCategory } from '@/types';

type Props = {
    organizationSlug: string;
    category: ProductCategory | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export default function DeleteCategoryModal({
    organizationSlug,
    category,
    open,
    onOpenChange,
}: Props) {
    const [processing, setProcessing] = useState(false);

    /**
     * The server refuses a category that is still on a product, so the dialog
     * says so up front rather than sending a request it knows will bounce.
     */
    const productCount = category?.products_count ?? 0;
    const isInUse = productCount > 0;

    const deleteCategory = () => {
        if (!category || isInUse) {
            return;
        }

        router.visit(destroy([organizationSlug, category.id]), {
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
            onSuccess: () => onOpenChange(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Delete category</DialogTitle>
                    <DialogDescription>
                        {isInUse ? (
                            <>
                                <strong>{category?.name}</strong> is still used
                                by{' '}
                                {productCount === 1
                                    ? '1 product'
                                    : `${productCount} products`}
                                . Move {productCount === 1 ? 'it' : 'them'} to
                                another category before deleting it.
                            </>
                        ) : (
                            <>
                                This action cannot be undone. This will
                                permanently delete{' '}
                                <strong>{category?.name}</strong>.
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
                            data-test="delete-category-confirm"
                            disabled={processing}
                            onClick={deleteCategory}
                        >
                            Delete category
                        </Button>
                    )}
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
