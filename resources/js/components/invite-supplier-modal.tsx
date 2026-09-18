import { Form } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
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
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { store } from '@/routes/suppliers';

type Props = PropsWithChildren<{
    organizationSlug: string;
}>;

export default function InviteSupplierModal({
    organizationSlug,
    children,
}: Props) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>{children}</DialogTrigger>
            <DialogContent>
                <Form
                    key={String(open)}
                    {...store.form(organizationSlug)}
                    className="space-y-6"
                    onSuccess={() => setOpen(false)}
                >
                    {({ errors, processing }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>Invite a supplier</DialogTitle>
                                <DialogDescription>
                                    We will email them a link to set up their
                                    company. You can assign products to them
                                    straight away, before they accept.
                                </DialogDescription>
                            </DialogHeader>

                            <div className="grid gap-2">
                                <Label htmlFor="supplier-company-name">
                                    Company name
                                </Label>
                                <Input
                                    id="supplier-company-name"
                                    name="company_name"
                                    data-test="supplier-company-name"
                                    placeholder="Acme Supplies AG"
                                    required
                                />
                                <InputError message={errors.company_name} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="supplier-contact-email">
                                    Contact email address
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

                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button variant="secondary">Cancel</Button>
                                </DialogClose>

                                <Button
                                    type="submit"
                                    data-test="invite-supplier-submit"
                                    disabled={processing}
                                >
                                    Send invitation
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
