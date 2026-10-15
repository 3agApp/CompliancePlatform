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
import { t, tc } from '@/lib/i18n';
import { destroy as bulkDestroy } from '@/routes/products/bulk';
import type { Product } from '@/types';

type Props = {
    organizationSlug: string;
    products: Product[];
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onDeleted: () => void;
};

export default function BulkDeleteProductsModal({
    organizationSlug,
    products,
    open,
    onOpenChange,
    onDeleted,
}: Props) {
    const [processing, setProcessing] = useState(false);

    const deleteProducts = () => {
        router.delete(bulkDestroy(organizationSlug).url, {
            data: { ids: products.map((product) => product.id) },
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
            onSuccess: () => {
                onOpenChange(false);
                onDeleted();
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {tc(
                            'Delete 1 product|Delete :count products',
                            products.length,
                        )}
                    </DialogTitle>
                    <DialogDescription>
                        {tc(
                            'This action cannot be undone. This will permanently delete the selected product and its documents.|This action cannot be undone. This will permanently delete the :count selected products and their documents.',
                            products.length,
                        )}
                    </DialogDescription>
                </DialogHeader>

                <DialogFooter className="gap-2">
                    <DialogClose asChild>
                        <Button variant="secondary">{t('Cancel')}</Button>
                    </DialogClose>

                    <Button
                        variant="destructive"
                        data-test="bulk-delete-confirm"
                        disabled={processing}
                        onClick={deleteProducts}
                    >
                        {tc(
                            'Delete 1 product|Delete :count products',
                            products.length,
                        )}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
