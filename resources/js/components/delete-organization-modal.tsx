import { Form } from '@inertiajs/react';
import { useState } from 'react';
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
import { t, tn } from '@/lib/i18n';
import { destroy } from '@/routes/organizations';
import type { Organization } from '@/types';

type Props = {
    organization: Organization;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export default function DeleteOrganizationModal({
    organization,
    open,
    onOpenChange,
}: Props) {
    const [confirmationName, setConfirmationName] = useState('');

    const canDeleteOrganization = confirmationName === organization.name;

    const handleOpenChange = (nextOpen: boolean) => {
        onOpenChange(nextOpen);

        if (!nextOpen) {
            setConfirmationName('');
        }
    };

    return (
        <Dialog open={open} onOpenChange={handleOpenChange}>
            <DialogContent>
                <Form
                    key={String(open)}
                    {...destroy.form(organization.slug)}
                    className="space-y-6"
                    onSuccess={() => handleOpenChange(false)}
                >
                    {({ errors, processing }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>{t('Are you sure?')}</DialogTitle>
                                <DialogDescription>
                                    {tn(
                                        'This action cannot be undone. This will permanently delete the organization :name.',
                                        {
                                            name: (
                                                <strong>
                                                    "{organization.name}"
                                                </strong>
                                            ),
                                        },
                                    )}
                                </DialogDescription>
                            </DialogHeader>

                            <div className="space-y-4 py-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="confirmation-name">
                                        {tn('Type :name to confirm', {
                                            name: (
                                                <strong>
                                                    "{organization.name}"
                                                </strong>
                                            ),
                                        })}
                                    </Label>
                                    <Input
                                        id="confirmation-name"
                                        name="name"
                                        data-test="delete-organization-name"
                                        value={confirmationName}
                                        onChange={(event) =>
                                            setConfirmationName(
                                                event.target.value,
                                            )
                                        }
                                        placeholder={t(
                                            'Enter organization name',
                                        )}
                                        autoComplete="off"
                                    />
                                    <InputError message={errors.name} />
                                </div>
                            </div>

                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button variant="secondary">
                                        {t('Cancel')}
                                    </Button>
                                </DialogClose>

                                <Button
                                    variant="destructive"
                                    type="submit"
                                    data-test="delete-organization-confirm"
                                    disabled={
                                        !canDeleteOrganization || processing
                                    }
                                >
                                    {t('Delete organization')}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
