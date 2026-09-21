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
import { destroy } from '@/routes/products/documents';
import type { ProductDocument } from '@/types';

type Props = {
    organizationSlug: string;
    productId: number;
    document: ProductDocument | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export default function DeleteProductDocumentModal({
    organizationSlug,
    productId,
    document,
    open,
    onOpenChange,
}: Props) {
    const [processing, setProcessing] = useState(false);

    const deleteDocument = () => {
        if (!document) {
            return;
        }

        router.visit(destroy([organizationSlug, productId, document.id]), {
            preserveScroll: true,
            preserveState: true,
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
            onSuccess: () => onOpenChange(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Delete document</DialogTitle>
                    <DialogDescription>
                        This action cannot be undone. This will permanently
                        delete <strong>{document?.name}</strong> and the file
                        behind it.
                    </DialogDescription>
                </DialogHeader>

                <DialogFooter className="gap-2">
                    <DialogClose asChild>
                        <Button variant="secondary">Cancel</Button>
                    </DialogClose>

                    <Button
                        variant="destructive"
                        data-test="delete-document-confirm"
                        disabled={processing}
                        onClick={deleteDocument}
                    >
                        Delete document
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
