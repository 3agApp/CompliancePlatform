import { Form } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
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
import { store, update } from '@/routes/brands';
import type { Brand } from '@/types';

type Props = PropsWithChildren<{
    organizationSlug: string;
    brand?: Brand;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}>;

/**
 * One dialog for adding and for renaming. A brand is a name and nothing
 * else, so the two forms would otherwise be the same markup twice.
 */
export default function SaveBrandModal({
    organizationSlug,
    brand,
    open,
    onOpenChange,
    children,
}: Props) {
    const isEditing = brand !== undefined;

    const form = isEditing
        ? update.form([organizationSlug, brand.id])
        : store.form(organizationSlug);

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            {children ? (
                <DialogTrigger asChild>{children}</DialogTrigger>
            ) : null}

            <DialogContent>
                <Form
                    key={`${brand?.id ?? 'new'}-${String(open)}`}
                    {...form}
                    className="space-y-6"
                    onSuccess={() => onOpenChange(false)}
                >
                    {({ errors, processing }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>
                                    {isEditing ? 'Rename brand' : 'Add a brand'}
                                </DialogTitle>
                                <DialogDescription>
                                    A brand is the legal family a product is
                                    regulated under, such as a toy or a magnetic
                                    toy.
                                </DialogDescription>
                            </DialogHeader>

                            <div className="grid gap-2">
                                <Label htmlFor="brand-name">Brand name</Label>
                                <Input
                                    id="brand-name"
                                    name="name"
                                    data-test="brand-name"
                                    defaultValue={brand?.name ?? ''}
                                    placeholder="Magna-Tiles"
                                    autoComplete="off"
                                    required
                                />
                                <InputError message={errors.name} />
                            </div>

                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button variant="secondary">Cancel</Button>
                                </DialogClose>

                                <Button
                                    type="submit"
                                    data-test="save-brand-submit"
                                    disabled={processing}
                                >
                                    {isEditing ? 'Save changes' : 'Add brand'}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
