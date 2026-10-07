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
import { t, tcn, tn } from '@/lib/i18n';
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
                    <DialogTitle>{t('Delete category')}</DialogTitle>
                    <DialogDescription>
                        {isInUse
                            ? tcn(
                                  ':name is still used by 1 product. Move it to another category before deleting it.|:name is still used by :count products. Move them to another category before deleting it.',
                                  productCount,
                                  { name: <strong>{category?.name}</strong> },
                              )
                            : tn(
                                  'This action cannot be undone. This will permanently delete :name.',
                                  { name: <strong>{category?.name}</strong> },
                              )}
                    </DialogDescription>
                </DialogHeader>

                <DialogFooter className="gap-2">
                    <DialogClose asChild>
                        <Button variant="secondary">
                            {isInUse ? t('Close') : t('Cancel')}
                        </Button>
                    </DialogClose>

                    {isInUse ? null : (
                        <Button
                            variant="destructive"
                            data-test="delete-category-confirm"
                            disabled={processing}
                            onClick={deleteCategory}
                        >
                            {t('Delete category')}
                        </Button>
                    )}
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
