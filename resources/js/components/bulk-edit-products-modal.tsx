import type { RequestPayload } from '@inertiajs/core';
import { router } from '@inertiajs/react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { CountryCombobox } from '@/components/country-combobox';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { t, tc } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { update as bulkUpdate } from '@/routes/products/bulk';
import type {
    BrandOption,
    CountryOption,
    CountryRestriction,
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

type Field =
    | 'is_active'
    | 'supplier'
    | 'brand'
    | 'category'
    | 'country'
    | 'safety';

/**
 * The compliance details a series usually shares, filled in together.
 * Mirrors BulkUpdateProductsRequest::COMPLIANCE_FIELDS, plus the customs
 * tariff number.
 */
type SafetyKey =
    | 'age_grading'
    | 'warning_text'
    | 'safety_notice'
    | 'material_information'
    | 'safety_instructions'
    | 'usage_restrictions'
    | 'customs_tariff_number';

function safetyFields(): {
    key: SafetyKey;
    label: string;
    multiline: boolean;
}[] {
    return [
        { key: 'age_grading', label: t('Age grading'), multiline: false },
        {
            key: 'customs_tariff_number',
            label: t('Customs tariff number'),
            multiline: false,
        },
        { key: 'warning_text', label: t('Warning text'), multiline: true },
        { key: 'safety_notice', label: t('Safety notice'), multiline: true },
        {
            key: 'material_information',
            label: t('Material information'),
            multiline: true,
        },
        {
            key: 'safety_instructions',
            label: t('Safety instructions'),
            multiline: true,
        },
        {
            key: 'usage_restrictions',
            label: t('Usage restrictions'),
            multiline: true,
        },
    ];
}

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
    /** Each trade's own list of countries, keyed by connection, where one is kept. */
    countryRestrictions: Record<number, CountryRestriction>;
    /** The fields ticked when the dialog opens, e.g. from the dashboard. */
    initialFields?: Field[];
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
    countryRestrictions,
    initialFields = [],
}: Props) {
    const [enabled, setEnabled] = useState<Set<Field>>(
        () => new Set(initialFields),
    );
    const [safety, setSafety] = useState<Partial<Record<SafetyKey, string>>>(
        {},
    );
    const [onlyEmpty, setOnlyEmpty] = useState(true);
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
        setSafety({});
        setOnlyEmpty(true);
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

    /**
     * The countries every product in the change may be given: those each
     * restricted supplier produces in, taken together. The supplier picked
     * in the same change decides for all of them; otherwise each product's
     * own does. Null when no supplier involved keeps a list.
     */
    const countryConnections =
        enabled.has('supplier') && connectionId
            ? [Number(connectionId)]
            : [...sharedConnections];
    const countryLists = countryConnections
        .map((id) => countryRestrictions[id]?.countries)
        .filter((list): list is string[] => list !== undefined);
    const allowedCountries =
        countryLists.length === 0
            ? null
            : countryLists.reduce((shared, list) =>
                  shared.filter((code) => list.includes(code)),
              );
    const offeredCountries =
        allowedCountries === null
            ? availableCountries
            : availableCountries.filter((option) =>
                  allowedCountries.includes(option.value),
              );

    const brands = availableBrands.filter(
        (brand) => brand.supplier_connection_id === brandConnectionId,
    );

    const templates = availableTemplates.filter(
        (template) => String(template.product_category_id) === categoryId,
    );

    const submit = () => {
        const data: RequestPayload = {
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

        /*
         * A blank box means "leave it", not "clear it": a series answer is
         * for filling in, and clearing is done product by product.
         */
        if (enabled.has('safety')) {
            for (const { key } of safetyFields()) {
                const value = safety[key]?.trim() ?? '';

                if (value !== '') {
                    data[key] = value;
                }
            }
        }

        if (enabled.has('safety') || enabled.has('country')) {
            data.only_empty = onlyEmpty;
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
            <DialogContent
                aria-describedby={undefined}
                className="max-h-[90vh] overflow-y-auto sm:max-w-lg"
            >
                <DialogHeader>
                    <DialogTitle>
                        {tc(
                            'Edit 1 product|Edit :count products',
                            products.length,
                        )}
                    </DialogTitle>
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
                        <CountryCombobox
                            options={offeredCountries}
                            value={country === NONE ? null : country}
                            onChange={(value) => setCountry(value ?? NONE)}
                            placeholder={t('Not set')}
                            invalid={errors.country_of_origin !== undefined}
                            data-test="bulk-country"
                        />
                        {allowedCountries?.length === 0 && (
                            <p
                                className="text-muted-foreground text-xs"
                                data-test="bulk-country-restriction"
                            >
                                {t(
                                    'The suppliers of these products have no country of production in common.',
                                )}
                            </p>
                        )}
                        <InputError message={errors.country_of_origin} />
                    </BulkField>

                    <BulkField
                        field="safety"
                        label={t('Safety details')}
                        enabled={enabled}
                        onToggle={toggle}
                    >
                        <div className="grid gap-3 sm:grid-cols-2">
                            {safetyFields().map((field) => {
                                const id = `bulk-${field.key}`;
                                const Control = field.multiline
                                    ? Textarea
                                    : Input;

                                return (
                                    <div
                                        key={field.key}
                                        className={cn(
                                            'grid content-start gap-1.5',
                                            field.multiline && 'sm:col-span-2',
                                        )}
                                    >
                                        <Label htmlFor={id}>
                                            {field.label}
                                        </Label>
                                        <Control
                                            id={id}
                                            data-test={id}
                                            value={safety[field.key] ?? ''}
                                            rows={
                                                field.multiline ? 2 : undefined
                                            }
                                            onChange={(
                                                event: React.ChangeEvent<
                                                    | HTMLInputElement
                                                    | HTMLTextAreaElement
                                                >,
                                            ) =>
                                                setSafety((current) => ({
                                                    ...current,
                                                    [field.key]:
                                                        event.target.value,
                                                }))
                                            }
                                        />
                                        <InputError
                                            message={errors[field.key]}
                                        />
                                    </div>
                                );
                            })}
                        </div>
                    </BulkField>

                    {enabled.has('safety') || enabled.has('country') ? (
                        <div className="flex items-center gap-2">
                            <Checkbox
                                id="bulk-only-empty"
                                checked={onlyEmpty}
                                onCheckedChange={(checked) =>
                                    setOnlyEmpty(checked === true)
                                }
                                data-test="bulk-only-empty"
                            />
                            <Label
                                htmlFor="bulk-only-empty"
                                className="text-muted-foreground font-normal"
                            >
                                {t(
                                    'Only fill products where this is still empty',
                                )}
                            </Label>
                        </div>
                    ) : null}

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
