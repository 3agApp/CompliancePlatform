import { Form } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import { useState } from 'react';
import ProductFormFields from '@/components/product-form-fields';
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
import { store } from '@/routes/products';
import type { CountryOption, SupplierConnectionOption } from '@/types';

type Props = PropsWithChildren<{
    organizationSlug: string;
    availableCountries: CountryOption[];
    availableConnections: SupplierConnectionOption[];
}>;

export default function CreateProductModal({
    organizationSlug,
    availableCountries,
    availableConnections,
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
                                <DialogTitle>Add a product</DialogTitle>
                                <DialogDescription>
                                    Products belong to the organization you are
                                    currently working in.
                                </DialogDescription>
                            </DialogHeader>

                            <ProductFormFields
                                errors={errors}
                                availableCountries={availableCountries}
                                availableConnections={availableConnections}
                                viewerType="distributor"
                                idPrefix="create-product"
                            />

                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button variant="secondary">Cancel</Button>
                                </DialogClose>

                                <Button
                                    type="submit"
                                    data-test="create-product-submit"
                                    disabled={processing}
                                >
                                    Add product
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
