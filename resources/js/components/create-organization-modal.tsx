import { Form, usePage } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import OrganizationTypeField from '@/components/organization-type-field';
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
import { store } from '@/routes/organizations';

export default function CreateOrganizationModal({
    children,
}: PropsWithChildren) {
    const [open, setOpen] = useState(false);
    const { organizationTypes } = usePage().props;

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>{children}</DialogTrigger>
            <DialogContent>
                <Form
                    key={String(open)}
                    {...store.form()}
                    className="space-y-6"
                    onSuccess={() => setOpen(false)}
                >
                    {({ errors, processing }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>
                                    Create a new organization
                                </DialogTitle>
                                <DialogDescription>
                                    Create a new organization to collaborate
                                    with others.
                                </DialogDescription>
                            </DialogHeader>

                            <div className="grid gap-2">
                                <Label htmlFor="name">Organization name</Label>
                                <Input
                                    id="name"
                                    name="name"
                                    data-test="create-organization-name"
                                    placeholder="My organization"
                                    required
                                />
                                <InputError message={errors.name} />
                            </div>

                            <OrganizationTypeField
                                options={organizationTypes}
                                error={errors.type}
                                idPrefix="create-organization-type"
                            />

                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button variant="secondary">Cancel</Button>
                                </DialogClose>

                                <Button
                                    type="submit"
                                    data-test="create-organization-submit"
                                    disabled={processing}
                                >
                                    Create organization
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
