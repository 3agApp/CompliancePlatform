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
import { t, tn } from '@/lib/i18n';
import { leave as leaveOrganizationAction } from '@/routes/organizations';
import type { Organization } from '@/types';

type Props = {
    organization: Organization | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export default function LeaveOrganizationModal({
    organization,
    open,
    onOpenChange,
}: Props) {
    const [processing, setProcessing] = useState(false);

    const leaveOrganization = () => {
        if (!organization) {
            return;
        }

        router.visit(leaveOrganizationAction(organization.slug), {
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
            onSuccess: () => onOpenChange(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{t('Leave organization')}</DialogTitle>
                    <DialogDescription>
                        {tn('Are you sure you want to leave :name?', {
                            name: <strong>{organization?.name}</strong>,
                        })}
                    </DialogDescription>
                </DialogHeader>

                <DialogFooter className="gap-2">
                    <DialogClose asChild>
                        <Button variant="secondary">{t('Cancel')}</Button>
                    </DialogClose>

                    <Button
                        variant="destructive"
                        data-test="leave-organization-confirm"
                        disabled={processing}
                        onClick={leaveOrganization}
                    >
                        {t('Leave organization')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
