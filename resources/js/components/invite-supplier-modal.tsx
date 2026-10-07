import { Form } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { t } from '@/lib/i18n';
import { store } from '@/routes/suppliers';
import type { SupplierConnectionOption } from '@/types';

type Props = {
    organizationSlug: string;
    /** The button that opens the dialog, when the dialog owns its own state. */
    children?: ReactNode;
    open?: boolean;
    onOpenChange?: (open: boolean) => void;
    /**
     * The props to ask back for when adding from inside another form, so
     * nothing already typed into it is disturbed by the visit.
     */
    reloadOnly?: string[];
    /** Handed the new supplier once the page props have caught up. */
    onCreated?: (connection: SupplierConnectionOption) => void;
};

/**
 * Add a supplier by name and email, and invite them now or later.
 *
 * Products can be assigned to a supplier before they are told about it, so
 * a distributor filling in a catalog can add the supplier and leave the
 * invitation until there is something worth showing them. Inviting stays
 * the default, because that is what most people adding a supplier mean.
 *
 * Radix renders the dialog in a portal at the end of the body, so the form
 * inside is not nested in a product form even when opened from one.
 */
export default function InviteSupplierModal({
    organizationSlug,
    children,
    open: controlledOpen,
    onOpenChange,
    reloadOnly,
    onCreated,
}: Props) {
    const [ownOpen, setOwnOpen] = useState(false);
    const [sendInvitation, setSendInvitation] = useState(true);
    const [companyName, setCompanyName] = useState('');

    const open = controlledOpen ?? ownOpen;

    const setOpen = (next: boolean) => {
        setOwnOpen(next);
        onOpenChange?.(next);

        if (next) {
            setSendInvitation(true);
            setCompanyName('');
        }
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            {children ? (
                <DialogTrigger asChild>{children}</DialogTrigger>
            ) : null}
            {/*
             * React carries events up its own tree, not the page's, so a
             * submit from this portal would also reach a product form the
             * dialog was opened from -- which then tries to post itself
             * with a button it does not own. The dialog's form has already
             * handled it by the time it gets here.
             */}
            <DialogContent onSubmit={(event) => event.stopPropagation()}>
                <Form
                    key={String(open)}
                    {...store.form(organizationSlug)}
                    options={
                        reloadOnly
                            ? {
                                  preserveState: true,
                                  preserveScroll: true,
                                  only: reloadOnly,
                              }
                            : undefined
                    }
                    className="space-y-6"
                    onSuccess={(page) => {
                        const connections = (page.props.availableConnections ??
                            []) as SupplierConnectionOption[];

                        /**
                         * A distributor keeps one live connection per
                         * address but the option carries only the name, so
                         * the newest row with the name just typed is the
                         * one written. Ids only grow.
                         */
                        const created = connections
                            .filter(
                                (connection) =>
                                    connection.label.toLowerCase() ===
                                    companyName.trim().toLowerCase(),
                            )
                            .sort((a, b) => b.id - a.id)[0];

                        if (created) {
                            onCreated?.(created);
                        }

                        setOpen(false);
                    }}
                >
                    {({ errors, processing }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>{t('Add a supplier')}</DialogTitle>
                                <DialogDescription>
                                    {t(
                                        'You can assign products to them straight away, whether or not they have been invited yet.',
                                    )}
                                </DialogDescription>
                            </DialogHeader>

                            <div className="grid gap-2">
                                <Label htmlFor="supplier-company-name">
                                    {t('Company name')}
                                </Label>
                                <Input
                                    id="supplier-company-name"
                                    name="company_name"
                                    data-test="supplier-company-name"
                                    placeholder="Acme Supplies AG"
                                    value={companyName}
                                    onChange={(event) =>
                                        setCompanyName(event.target.value)
                                    }
                                    required
                                />
                                <InputError message={errors.company_name} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="supplier-contact-email">
                                    {t('Contact email address')}
                                </Label>
                                <Input
                                    id="supplier-contact-email"
                                    name="contact_email"
                                    type="email"
                                    data-test="supplier-contact-email"
                                    placeholder="compliance@acme.example"
                                    required
                                />
                                <InputError message={errors.contact_email} />
                            </div>

                            <div className="flex items-start gap-3">
                                <Checkbox
                                    id="supplier-send-invitation"
                                    data-test="supplier-send-invitation"
                                    checked={sendInvitation}
                                    onCheckedChange={(value) =>
                                        setSendInvitation(value === true)
                                    }
                                    className="mt-0.5"
                                />
                                <input
                                    type="hidden"
                                    name="send_invitation"
                                    value={sendInvitation ? '1' : '0'}
                                />
                                <div className="grid gap-1">
                                    <Label
                                        htmlFor="supplier-send-invitation"
                                        className="font-normal"
                                    >
                                        {t('Send the invitation now')}
                                    </Label>
                                    <p className="text-muted-foreground text-sm">
                                        {sendInvitation
                                            ? t(
                                                  'We will email them a link to set up their company.',
                                              )
                                            : t(
                                                  'Nothing is sent. Invite them from the suppliers page whenever you are ready.',
                                              )}
                                    </p>
                                </div>
                            </div>

                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button variant="secondary">
                                        {t('Cancel')}
                                    </Button>
                                </DialogClose>

                                <Button
                                    type="submit"
                                    data-test="invite-supplier-submit"
                                    disabled={processing}
                                >
                                    {sendInvitation
                                        ? t('Add and invite')
                                        : t('Add supplier')}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
