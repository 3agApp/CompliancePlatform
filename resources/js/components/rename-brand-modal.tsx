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
import { update } from '@/routes/brands';
import type { Brand } from '@/types';

type Props = PropsWithChildren<{
    organizationSlug: string;
    brand: Brand | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}>;

/**
 * Rename a maker.
 *
 * Renaming only: a brand is named under one trade and never moves to
 * another, because the products already carrying it belong to that
 * supplier and moving the row would quietly move them too. Adding one is
 * CreateBrandModal, which asks which trade it goes under.
 */
export default function RenameBrandModal({
    organizationSlug,
    brand,
    open,
    onOpenChange,
    children,
}: Props) {
    if (brand === null) {
        return null;
    }

    const form = update.form([organizationSlug, brand.id]);

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            {children ? (
                <DialogTrigger asChild>{children}</DialogTrigger>
            ) : null}

            <DialogContent>
                <Form
                    key={`${brand.id}-${String(open)}`}
                    {...form}
                    className="space-y-6"
                    onSuccess={() => onOpenChange(false)}
                >
                    {({ errors, processing }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>Rename brand</DialogTitle>
                                <DialogDescription>
                                    The maker behind a product, such as
                                    Magna-Tiles or tigerbox. It stays with the
                                    supplier it is filed under.
                                </DialogDescription>
                            </DialogHeader>

                            <div className="grid gap-2">
                                <Label htmlFor="brand-name">Brand name</Label>
                                <Input
                                    id="brand-name"
                                    name="name"
                                    data-test="brand-name"
                                    defaultValue={brand.name}
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
                                    Save changes
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
