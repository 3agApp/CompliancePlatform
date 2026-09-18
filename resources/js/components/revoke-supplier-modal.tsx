import { Form } from '@inertiajs/react';
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
import { revoke } from '@/routes/suppliers';
import type { SupplierConnection } from '@/types';

type Props = {
    organizationSlug: string;
    connection: SupplierConnection | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export default function RevokeSupplierModal({
    organizationSlug,
    connection,
    open,
    onOpenChange,
}: Props) {
    if (!connection) {
        return null;
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <Form
                    {...revoke.form([organizationSlug, connection.id])}
                    className="space-y-6"
                    onSuccess={() => onOpenChange(false)}
                >
                    {({ processing }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>
                                    Disconnect {connection.companyName}?
                                </DialogTitle>
                                <DialogDescription>
                                    They lose access to the{' '}
                                    {connection.productsCount}{' '}
                                    {connection.productsCount === 1
                                        ? 'product'
                                        : 'products'}{' '}
                                    assigned to them right away. The products
                                    keep their assignment, and you can reconnect
                                    at any time.
                                </DialogDescription>
                            </DialogHeader>

                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button variant="secondary">Cancel</Button>
                                </DialogClose>

                                <Button
                                    type="submit"
                                    variant="destructive"
                                    data-test="revoke-supplier-confirm"
                                    disabled={processing}
                                >
                                    Disconnect supplier
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
