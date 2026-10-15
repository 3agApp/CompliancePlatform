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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { t } from '@/lib/i18n';
import { update } from '@/routes/suppliers';
import type { SupplierConnection } from '@/types';

type Props = {
    organizationSlug: string;
    connection: SupplierConnection | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

/**
 * Correct the name or address of a supplier that has not joined yet.
 *
 * The address is optional only for a supplier that never had one -- one the
 * product import added -- and changing it on an invited supplier takes the
 * invitation back, which the form says before it is saved.
 */
export default function EditSupplierModal({
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
                    key={`${connection.id}-${String(open)}`}
                    {...update.form([organizationSlug, connection.id])}
                    options={{ preserveScroll: true }}
                    className="space-y-6"
                    onSuccess={() => onOpenChange(false)}
                >
                    {({ errors, processing }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>{t('Edit supplier')}</DialogTitle>
                                <DialogDescription>
                                    {connection.isInvited
                                        ? t(
                                              'Changing the email address takes back the invitation already sent. Invite them again afterwards.',
                                          )
                                        : t(
                                              'Correct the name or add the address the invitation will go to.',
                                          )}
                                </DialogDescription>
                            </DialogHeader>

                            <div className="grid gap-2">
                                <Label htmlFor="edit-supplier-company-name">
                                    {t('Company name')}
                                </Label>
                                <Input
                                    id="edit-supplier-company-name"
                                    name="company_name"
                                    data-test="edit-supplier-company-name"
                                    defaultValue={connection.companyName}
                                    required
                                />
                                <InputError message={errors.company_name} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="edit-supplier-contact-email">
                                    {t('Contact email address')}
                                </Label>
                                <Input
                                    id="edit-supplier-contact-email"
                                    name="contact_email"
                                    type="email"
                                    data-test="edit-supplier-contact-email"
                                    defaultValue={connection.contactEmail ?? ''}
                                    placeholder="compliance@acme.example"
                                    required={connection.contactEmail !== null}
                                />
                                <InputError message={errors.contact_email} />
                            </div>

                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button variant="secondary">
                                        {t('Cancel')}
                                    </Button>
                                </DialogClose>

                                <Button
                                    type="submit"
                                    data-test="edit-supplier-submit"
                                    disabled={processing}
                                >
                                    {processing ? t('Saving…') : t('Save')}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
