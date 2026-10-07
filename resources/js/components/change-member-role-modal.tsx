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
import { update as updateMember } from '@/routes/organizations/members';
import type { RoleOption, Organization, OrganizationMember } from '@/types';

type Props = {
    organization: Organization;
    member: OrganizationMember | null;
    role: RoleOption | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export default function ChangeMemberRoleModal({
    organization,
    member,
    role,
    open,
    onOpenChange,
}: Props) {
    const [processing, setProcessing] = useState(false);

    const changeRole = () => {
        if (!member || !role) {
            return;
        }

        router.visit(updateMember([organization.slug, member.id]), {
            data: { role: role.value },
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
            onSuccess: () => onOpenChange(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{t('Change role')}</DialogTitle>
                    <DialogDescription>
                        {tn(
                            role && /^[aeiou]/i.test(role.label)
                                ? 'Make :name an :role of this organization? This changes what they can see and do straight away.'
                                : 'Make :name a :role of this organization? This changes what they can see and do straight away.',
                            {
                                name: <strong>{member?.name}</strong>,
                                role: <strong>{role?.label}</strong>,
                            },
                        )}
                    </DialogDescription>
                </DialogHeader>

                <DialogFooter className="gap-2">
                    <DialogClose asChild>
                        <Button variant="secondary">{t('Cancel')}</Button>
                    </DialogClose>

                    <Button
                        data-test="member-role-confirm"
                        disabled={processing}
                        onClick={changeRole}
                    >
                        {t('Change role')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
