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
import { t } from '@/lib/i18n';
import { destroy } from '@/routes/suppliers';
import type { SupplierConnection } from '@/types';

type Props = {
    organizationSlug: string;
    connection: SupplierConnection | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export default function DeleteSupplierModal({
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
                    {...destroy.form([organizationSlug, connection.id])}
                    options={{ preserveScroll: true }}
                    className="space-y-6"
                    onSuccess={() => onOpenChange(false)}
                >
                    {({ processing }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>
                                    {t('Delete :name?', {
                                        name: connection.companyName,
                                    })}
                                </DialogTitle>
                                <DialogDescription>
                                    {connection.isInvited
                                        ? t(
                                              'They have no products yet. The supplier and its brands are removed, and the invitation already sent stops working.',
                                          )
                                        : t(
                                              'They have no products yet. The supplier and its brands are removed.',
                                          )}
                                </DialogDescription>
                            </DialogHeader>

                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button variant="secondary">
                                        {t('Cancel')}
                                    </Button>
                                </DialogClose>

                                <Button
                                    type="submit"
                                    variant="destructive"
                                    data-test="delete-supplier-confirm"
                                    disabled={processing}
                                >
                                    {t('Delete supplier')}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
