import { router } from '@inertiajs/react';
import { useState } from 'react';
import type { ReactNode } from 'react';
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
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { t, tc } from '@/lib/i18n';
import { update as bulkUpdate } from '@/routes/products/bulk';
import type {
    BrandOption,
    CountryOption,
    Product,
    ProductCategoryOption,
    ProductTemplateOption,
    SupplierConnectionOption,
} from '@/types';

/**
 * The value a select uses for "clear the field", since a select item cannot
 * carry an empty string.
 */
const NONE = 'none';

type Field = 'is_active' | 'supplier' | 'brand' | 'category' | 'country';

type Props = {
    organizationSlug: string;
    products: Product[];
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onSaved: () => void;
    /** A supplier may only change the active flag and the country. */
    canReassign: boolean;
    availableConnections: SupplierConnectionOption[];
    availableBrands: BrandOption[];
    availableCategories: ProductCategoryOption[];
    availableTemplates: ProductTemplateOption[];
    availableCountries: CountryOption[];
};

/**
 * One change applied to every selected product.
 *
 * Each field is opted into on its own: a field left unticked is left as
 * every product has it, which keeps "leave it alone" apart from "clear it"
 * and lets one dialog serve a change of one field or of five.
 */
export default function BulkEditProductsModal({
    organizationSlug,
    products,
    open,
    onOpenChange,
    onSaved,
    canReassign,
    availableConnections,
    availableBrands,
    availableCategories,
    availableTemplates,
    availableCountries,
}: Props) {
    const [enabled, setEnabled] = useState<Set<Field>>(new Set());
    const [isActive, setIsActive] = useState('1');
    const [connectionId, setConnectionId] = useState<string>();
    const [brandId, setBrandId] = useState<string>(NONE);
    const [categoryId, setCategoryId] = useState<string>();
    const [templateId, setTemplateId] = useState<string>();
    const [country, setCountry] = useState<string>(NONE);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);

    const reset = () => {
        setEnabled(new Set());
        setIsActive('1');
        setConnectionId(undefined);
        setBrandId(NONE);
        setCategoryId(undefined);
        setTemplateId(undefined);
        setCountry(NONE);
        setErrors({});
    };

    const changeOpen = (next: boolean) => {
        if (!next) {
            reset();
        }

        onOpenChange(next);
    };

    const toggle = (field: Field, on: boolean) => {
        setEnabled((current) => {
            const next = new Set(current);

            if (on) {
                next.add(field);
            } else {
                next.delete(field);
            }

            return next;
        });
    };

    /**
     * The supplier whose brands are on offer: the one being moved to, or
     * the one every selected product already shares. Across several
     * suppliers there is no brand that fits them all.
     */
    const sharedConnections = new Set(
        products.map((product) => product.supplier_connection_id),
    );

    const brandConnectionId =
        enabled.has('supplier') && connectionId
            ? Number(connectionId)
            : sharedConnections.size === 1
              ? products[0]?.supplier_connection_id
              : undefined;

    const brands = availableBrands.filter(
        (brand) => brand.supplier_connection_id === brandConnectionId,
    );

    const templates = availableTemplates.filter(
        (template) => String(template.product_category_id) === categoryId,
    );

    const submit = () => {
        const data: Record<string, unknown> = {
            ids: products.map((product) => product.id),
        };

        if (enabled.has('is_active')) {
            data.is_active = isActive === '1';
        }

        if (enabled.has('supplier')) {
            data.supplier_connection_id = connectionId ?? null;
        }

        if (enabled.has('brand') && brandConnectionId !== undefined) {
            data.brand_id = brandId === NONE ? null : Number(brandId);
        }

        if (enabled.has('category')) {
            data.product_category_id = categoryId ?? null;
            data.product_template_id = templateId ?? null;
        }

        if (enabled.has('country')) {
            data.country_of_origin = country === NONE ? null : country;
        }

        router.patch(bulkUpdate(organizationSlug).url, data, {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
            onError: (next) => setErrors(next),
            onSuccess: () => {
                changeOpen(false);
                onSaved();
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={changeOpen}>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>
                        {tc(
                            'Edit 1 product|Edit :count products',
                            products.length,
                        )}
                    </DialogTitle>
                    <DialogDescription>
                        {t(
                            'Tick the fields to change. Everything else is left as each product has it.',
                        )}
                    </DialogDescription>
                </DialogHeader>

                <div className="grid gap-4">
                    <BulkField
                        field="is_active"
                        label={t('Status')}
                        enabled={enabled}
                        onToggle={toggle}
                    >
                        <Select value={isActive} onValueChange={setIsActive}>
                            <SelectTrigger
                                className="w-full"
                                data-test="bulk-is-active"
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="1">{t('Active')}</SelectItem>
                                <SelectItem value="0">
                                    {t('Inactive')}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError message={errors.is_active} />
                    </BulkField>

                    {canReassign ? (
                        <BulkField
                            field="supplier"
                            label={t('Supplier')}
                            enabled={enabled}
                            onToggle={toggle}
                        >
                            <Select
                                value={connectionId}
                                onValueChange={setConnectionId}
                            >
                                <SelectTrigger
                                    className="w-full"
                                    data-test="bulk-supplier"
                                >
                                    <SelectValue
                                        placeholder={t('Select a supplier')}
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    {availableConnections.map((connection) => (
                                        <SelectItem
                                            key={connection.id}
                                            value={String(connection.id)}
                                        >
                                            {connection.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <p className="text-muted-foreground text-xs">
                                {t(
                                    'A brand belongs to its supplier, so products moved to another supplier lose theirs unless you set one below.',
                                )}
                            </p>
                            <InputError
                                message={errors.supplier_connection_id}
                            />
                        </BulkField>
                    ) : null}

                    {canReassign ? (
                        <BulkField
                            field="brand"
                            label={t('Brand')}
                            enabled={enabled}
                            onToggle={toggle}
                            disabled={brandConnectionId === undefined}
                        >
                            <Select value={brandId} onValueChange={setBrandId}>
                                <SelectTrigger
                                    className="w-full"
                                    data-test="bulk-brand"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={NONE}>
                                        {t('No brand')}
                                    </SelectItem>
                                    {brands.map((brand) => (
                                        <SelectItem
                                            key={brand.id}
                                            value={String(brand.id)}
                                        >
                                            {brand.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {brandConnectionId === undefined ? (
                                <p className="text-muted-foreground text-xs">
                                    {t(
                                        'Select products from one supplier, or choose a supplier above, to set a brand.',
                                    )}
                                </p>
                            ) : null}
                            <InputError message={errors.brand_id} />
                        </BulkField>
                    ) : null}

                    {canReassign ? (
                        <BulkField
                            field="category"
                            label={t('Category and template')}
                            enabled={enabled}
                            onToggle={toggle}
                        >
                            <div className="grid gap-2 sm:grid-cols-2">
                                <Select
                                    value={categoryId}
                                    onValueChange={(value) => {
                                        setCategoryId(value);
                                        setTemplateId(undefined);
                                    }}
                                >
                                    <SelectTrigger
                                        className="w-full"
                                        data-test="bulk-category"
                                    >
                                        <SelectValue
                                            placeholder={t('Select a category')}
                                        />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {availableCategories.map((category) => (
                                            <SelectItem
                                                key={category.id}
                                                value={String(category.id)}
                                            >
                                                {category.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <Select
                                    value={templateId}
                                    onValueChange={setTemplateId}
                                    disabled={templates.length === 0}
                                >
                                    <SelectTrigger
                                        className="w-full"
                                        data-test="bulk-template"
                                    >
                                        <SelectValue
                                            placeholder={t('Select a template')}
                                        />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {templates.map((template) => (
                                            <SelectItem
                                                key={template.id}
                                                value={String(template.id)}
                                            >
                                                {template.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <p className="text-muted-foreground text-xs">
                                {t(
                                    'The template decides what each product is asked for, so its checklist changes with it.',
                                )}
                            </p>
                            <InputError message={errors.product_category_id} />
                            <InputError message={errors.product_template_id} />
                        </BulkField>
                    ) : null}

                    <BulkField
                        field="country"
                        label={t('Country of origin')}
                        enabled={enabled}
                        onToggle={toggle}
                    >
                        <Select value={country} onValueChange={setCountry}>
                            <SelectTrigger
                                className="w-full"
                                data-test="bulk-country"
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={NONE}>
                                    {t('Not set')}
                                </SelectItem>
                                {availableCountries.map((option) => (
                                    <SelectItem
                                        key={option.value}
                                        value={option.value}
                                    >
                                        {option.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={errors.country_of_origin} />
                    </BulkField>

                    <InputError message={errors.ids} />
                </div>

                <DialogFooter className="gap-2">
                    <DialogClose asChild>
                        <Button variant="secondary">{t('Cancel')}</Button>
                    </DialogClose>

                    <Button
                        data-test="bulk-edit-confirm"
                        disabled={processing || enabled.size === 0}
                        onClick={submit}
                    >
                        {tc(
                            'Update 1 product|Update :count products',
                            products.length,
                        )}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

/**
 * One field of the change: a tick to opt into it, and its control once
 * ticked.
 */
function BulkField({
    field,
    label,
    enabled,
    onToggle,
    disabled = false,
    children,
}: {
    field: Field;
    label: string;
    enabled: Set<Field>;
    onToggle: (field: Field, on: boolean) => void;
    disabled?: boolean;
    children: ReactNode;
}) {
    const id = `bulk-change-${field}`;
    const isEnabled = enabled.has(field) && !disabled;

    return (
        <div className="grid gap-2">
            <div className="flex items-center gap-2">
                <Checkbox
                    id={id}
                    checked={isEnabled}
                    disabled={disabled}
                    onCheckedChange={(checked) =>
                        onToggle(field, checked === true)
                    }
                    data-test={id}
                />
                <Label htmlFor={id}>{label}</Label>
            </div>
            {isEnabled ? (
                <div className="grid gap-2 pl-6">{children}</div>
            ) : null}
        </div>
    );
}
