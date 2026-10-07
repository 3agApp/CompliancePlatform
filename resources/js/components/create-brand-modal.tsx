import { Form } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { t } from '@/lib/i18n';
import { store } from '@/routes/brands';
import type { BrandOption } from '@/types';

type Props = {
    organizationSlug: string;
    /** The trade the maker is named under. */
    supplierConnectionId: number;
    supplierLabel: string;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /**
     * The props to ask back for. Only what the calling page reads brands
     * through, so nothing else on it is disturbed by the visit.
     */
    reloadOnly?: string[];
    /** Handed the new brand once the page props have caught up. */
    onCreated?: (brand: BrandOption) => void;
};

/**
 * Name a maker without leaving the product form.
 *
 * Radix renders a dialog in a portal at the end of the body, so the form
 * inside this one is not nested in the product form even though it reads
 * that way in the source -- which is the only reason this can be a form at
 * all.
 *
 * The visit keeps the page state and asks only for the brands back, so
 * everything already typed into the product form behind the dialog is
 * still there afterwards.
 */
export default function CreateBrandModal({
    organizationSlug,
    supplierConnectionId,
    supplierLabel,
    open,
    onOpenChange,
    reloadOnly = ['availableBrands'],
    onCreated,
}: Props) {
    const [name, setName] = useState('');

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <Form
                    key={String(open)}
                    {...store.form(organizationSlug)}
                    options={{
                        preserveState: true,
                        preserveScroll: true,
                        only: reloadOnly,
                    }}
                    className="space-y-6"
                    onSuccess={(page) => {
                        const brands = (page.props.availableBrands ??
                            []) as BrandOption[];

                        /**
                         * A name is unique within a trade, so the row just
                         * written is the one matching both. Looking it up
                         * beats having the server flash an id back.
                         */
                        const created = brands.find(
                            (brand) =>
                                brand.supplier_connection_id ===
                                    supplierConnectionId &&
                                brand.label.toLowerCase() ===
                                    name.trim().toLowerCase(),
                        );

                        if (created) {
                            onCreated?.(created);
                        }

                        setName('');
                        onOpenChange(false);
                    }}
                >
                    {({ errors, processing }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>{t('Add a brand')}</DialogTitle>
                                <DialogDescription>
                                    {t(
                                        'The maker behind the product. :supplier carries it, so the brand is filed under them and shows up on every product they supply.',
                                        { supplier: supplierLabel },
                                    )}
                                </DialogDescription>
                            </DialogHeader>

                            <input
                                type="hidden"
                                name="supplier_connection_id"
                                value={supplierConnectionId}
                            />

                            <div className="grid gap-2">
                                <Label htmlFor="inline-brand-name">
                                    {t('Brand name')}
                                </Label>
                                <Input
                                    id="inline-brand-name"
                                    name="name"
                                    data-test="inline-brand-name"
                                    value={name}
                                    onChange={(event) =>
                                        setName(event.target.value)
                                    }
                                    placeholder="Magna-Tiles"
                                    autoComplete="off"
                                    required
                                />
                                <InputError message={errors.name} />
                                <InputError
                                    message={errors.supplier_connection_id}
                                />
                            </div>

                            <DialogFooter className="gap-2">
                                <Button
                                    type="button"
                                    variant="secondary"
                                    onClick={() => onOpenChange(false)}
                                >
                                    {t('Cancel')}
                                </Button>

                                <Button
                                    type="submit"
                                    data-test="inline-brand-submit"
                                    disabled={processing}
                                >
                                    {t('Add brand')}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
