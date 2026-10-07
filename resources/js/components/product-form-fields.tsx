import { Plus } from 'lucide-react';
import { useState } from 'react';
import CreateBrandModal from '@/components/create-brand-modal';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type {
    BrandOption,
    CountryOfOrigin,
    CountryOption,
    Product,
    ProductRequirementKey,
} from '@/types';
import { t } from '@/lib/i18n';
import { cn } from '@/lib/utils';

type FieldName =
    | 'name'
    | 'brand_id'
    | 'ean'
    | 'internal_article_number'
    | 'supplier_article_number'
    | 'order_number'
    | 'customs_tariff_number'
    | 'country_of_origin';

type Props = {
    errors: Partial<Record<FieldName, string>>;
    availableCountries: CountryOption[];
    availableBrands: BrandOption[];
    /**
     * The trade the product sits on. A maker is named under one supplier,
     * so this is what narrows the brands on offer -- and what a new one
     * would be filed under. Every product answers to a supplier, so there
     * is always one.
     */
    supplierConnectionId: number;
    supplierLabel: string | null;
    organizationSlug: string;
    canCreateBrand?: boolean;
    /** What the product's template asks for, which is what marks the labels. */
    requirements?: ProductRequirementKey[];
    /**
     * What the template asks for and the saved product does not have yet,
     * which turns the marker on those fields into a call to fill them in.
     */
    outstanding?: ProductRequirementKey[];
    product?: Product;
    disabled?: boolean;
    idPrefix?: string;
};

/**
 * Everything but the name is optional, so the marker is worth factoring out
 * rather than repeating on every label. Shared with the compliance fields,
 * where every single one is optional too.
 */
export function Optional() {
    return (
        <span className="text-muted-foreground font-normal">
            {t('(optional)')}
        </span>
    );
}

/**
 * A field the product's template asks for.
 *
 * Deliberately not a `required` attribute, and deliberately not a warning
 * colour. A template is homework, not a gate: the product saves with the
 * field empty, and the marker says the template asks for it whether or not
 * it has been answered yet. Amber here would read as a fault on a field
 * that is already filled in -- so amber is kept for the fields that are
 * not, below.
 */
export function RequiredByTemplate() {
    return (
        <span className="text-foreground font-normal">{t('(required)')}</span>
    );
}

/**
 * A field the template asks for that the saved product leaves empty.
 *
 * Read from the saved product, like the checklist, so a field typed into
 * but not yet saved still says so -- which is the truth until it is.
 */
export function NeededByTemplate() {
    return (
        <span
            className="ml-1 rounded-full border border-amber-500/40 bg-amber-500/10 px-2 py-px text-[11px] font-semibold text-amber-700 dark:text-amber-400"
            data-test="field-needed"
        >
            {t('Needed')}
        </span>
    );
}

/**
 * The marker on one label, given what the template asks for and whether
 * the saved product answers it.
 */
export function FieldMarker({
    required,
    missing = false,
}: {
    required: boolean;
    missing?: boolean;
}) {
    if (required && missing) {
        return <NeededByTemplate />;
    }

    return required ? <RequiredByTemplate /> : <Optional />;
}

/**
 * The outline on a control the template is still waiting for, matching
 * its label's marker.
 */
export function neededControlClass(missing: boolean): string | undefined {
    return missing
        ? 'border-amber-500/60 bg-amber-500/5 dark:bg-amber-500/5'
        : undefined;
}

export default function ProductFormFields({
    errors,
    availableCountries,
    availableBrands,
    supplierConnectionId,
    supplierLabel,
    organizationSlug,
    canCreateBrand = false,
    requirements = [],
    outstanding = [],
    product,
    disabled = false,
    idPrefix = 'product',
}: Props) {
    const [country, setCountry] = useState<CountryOfOrigin | undefined>(
        product?.country_of_origin ?? undefined,
    );

    const [brandId, setBrandId] = useState<string | undefined>(
        product?.brand_id ? String(product.brand_id) : undefined,
    );

    const [brandDialogOpen, setBrandDialogOpen] = useState(false);

    const needs = (requirement: ProductRequirementKey) =>
        requirements.includes(requirement);

    /** Asked for by the template on screen and still empty when saved. */
    const owes = (requirement: ProductRequirementKey) =>
        needs(requirement) && outstanding.includes(requirement);

    const brands = availableBrands.filter(
        (brand) => brand.supplier_connection_id === supplierConnectionId,
    );

    /**
     * Derived rather than cleared on a change of supplier: a brand that is
     * not one of this supplier's is simply not a selection, and switching
     * back brings it into view again without anything having been thrown
     * away in between.
     */
    const selectedBrandId = brands.some((brand) => String(brand.id) === brandId)
        ? brandId
        : undefined;

    return (
        <div className="grid gap-4">
            <div className="grid gap-2">
                <Label htmlFor={`${idPrefix}-name`}>{t('Product name')}</Label>
                <Input
                    id={`${idPrefix}-name`}
                    name="name"
                    data-test="product-name"
                    defaultValue={product?.name ?? ''}
                    placeholder={t('Organic oat milk 1L')}
                    disabled={disabled}
                    required
                />
                <InputError message={errors.name} />
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
                <div className="grid content-start gap-2">
                    <Label htmlFor={`${idPrefix}-brand`}>
                        {t('Brand')}{' '}
                        <FieldMarker
                            required={needs('requires_brand')}
                            missing={owes('requires_brand')}
                        />
                    </Label>
                    <div className="flex gap-2">
                        <Select
                            value={selectedBrandId}
                            onValueChange={setBrandId}
                            disabled={disabled || brands.length === 0}
                        >
                            <SelectTrigger
                                id={`${idPrefix}-brand`}
                                data-test="product-brand"
                                className={cn(
                                    'w-full',
                                    neededControlClass(owes('requires_brand')),
                                )}
                            >
                                <SelectValue
                                    placeholder={t('Select a brand')}
                                />
                            </SelectTrigger>
                            <SelectContent>
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

                        {canCreateBrand && !disabled ? (
                            <Button
                                type="button"
                                variant="outline"
                                size="icon"
                                data-test="product-add-brand"
                                aria-label={t('Add a brand')}
                                onClick={() => setBrandDialogOpen(true)}
                            >
                                <Plus className="size-4" />
                            </Button>
                        ) : null}
                    </div>
                    <input
                        type="hidden"
                        name="brand_id"
                        value={selectedBrandId ?? ''}
                    />
                    <p className="text-muted-foreground text-xs">
                        {brands.length === 0
                            ? supplierLabel
                                ? t(':supplier has no brands yet.', {
                                      supplier: supplierLabel,
                                  })
                                : t('This supplier has no brands yet.')
                            : t('The maker of the product.')}
                    </p>
                    <InputError message={errors.brand_id} />

                    <CreateBrandModal
                        organizationSlug={organizationSlug}
                        supplierConnectionId={supplierConnectionId}
                        supplierLabel={supplierLabel ?? t('This supplier')}
                        open={brandDialogOpen}
                        onOpenChange={setBrandDialogOpen}
                        onCreated={(brand) => setBrandId(String(brand.id))}
                    />
                </div>

                <div className="grid content-start gap-2">
                    <Label htmlFor={`${idPrefix}-ean`}>
                        {t('EAN / barcode')}{' '}
                        <FieldMarker
                            required={needs('requires_ean')}
                            missing={owes('requires_ean')}
                        />
                    </Label>
                    <Input
                        id={`${idPrefix}-ean`}
                        name="ean"
                        data-test="product-ean"
                        className={neededControlClass(owes('requires_ean'))}
                        defaultValue={product?.ean ?? ''}
                        placeholder="4006381333931"
                        inputMode="numeric"
                        autoComplete="off"
                        disabled={disabled}
                    />
                    <InputError message={errors.ean} />
                </div>
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
                <div className="grid content-start gap-2">
                    <Label htmlFor={`${idPrefix}-internal-article-number`}>
                        {t('Internal article number')}{' '}
                        <FieldMarker
                            required={needs('requires_internal_article_number')}
                            missing={owes('requires_internal_article_number')}
                        />
                    </Label>
                    <Input
                        id={`${idPrefix}-internal-article-number`}
                        name="internal_article_number"
                        data-test="product-internal-article-number"
                        className={neededControlClass(
                            owes('requires_internal_article_number'),
                        )}
                        defaultValue={product?.internal_article_number ?? ''}
                        placeholder="ART-10294"
                        autoComplete="off"
                        disabled={disabled}
                    />
                    <p className="text-muted-foreground text-xs">
                        {t("The distributor's own SKU.")}
                    </p>
                    <InputError message={errors.internal_article_number} />
                </div>

                <div className="grid content-start gap-2">
                    <Label htmlFor={`${idPrefix}-supplier-article-number`}>
                        {t('Supplier article number')}{' '}
                        <FieldMarker
                            required={needs('requires_supplier_article_number')}
                            missing={owes('requires_supplier_article_number')}
                        />
                    </Label>
                    <Input
                        id={`${idPrefix}-supplier-article-number`}
                        name="supplier_article_number"
                        data-test="product-supplier-article-number"
                        className={neededControlClass(
                            owes('requires_supplier_article_number'),
                        )}
                        defaultValue={product?.supplier_article_number ?? ''}
                        placeholder="MT-BLUE-32"
                        autoComplete="off"
                        disabled={disabled}
                    />
                    <p className="text-muted-foreground text-xs">
                        {t("The manufacturer's SKU.")}
                    </p>
                    <InputError message={errors.supplier_article_number} />
                </div>
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
                <div className="grid content-start gap-2">
                    <Label htmlFor={`${idPrefix}-order-number`}>
                        {t('Order number')}{' '}
                        <FieldMarker
                            required={needs('requires_order_number')}
                            missing={owes('requires_order_number')}
                        />
                    </Label>
                    <Input
                        id={`${idPrefix}-order-number`}
                        name="order_number"
                        data-test="product-order-number"
                        className={neededControlClass(
                            owes('requires_order_number'),
                        )}
                        defaultValue={product?.order_number ?? ''}
                        placeholder="PO-2026-0148"
                        autoComplete="off"
                        disabled={disabled}
                    />
                    <InputError message={errors.order_number} />
                </div>

                <div className="grid content-start gap-2">
                    <Label htmlFor={`${idPrefix}-customs-tariff-number`}>
                        {t('Customs tariff number')}{' '}
                        <FieldMarker
                            required={needs('requires_customs_tariff_number')}
                            missing={owes('requires_customs_tariff_number')}
                        />
                    </Label>
                    <Input
                        id={`${idPrefix}-customs-tariff-number`}
                        name="customs_tariff_number"
                        data-test="product-customs-tariff-number"
                        className={neededControlClass(
                            owes('requires_customs_tariff_number'),
                        )}
                        defaultValue={product?.customs_tariff_number ?? ''}
                        placeholder="9503.00.75"
                        inputMode="numeric"
                        autoComplete="off"
                        disabled={disabled}
                    />
                    <p className="text-muted-foreground text-xs">
                        {t('Its HS code.')}
                    </p>
                    <InputError message={errors.customs_tariff_number} />
                </div>
            </div>

            <div className="grid gap-2 sm:max-w-[calc(50%-0.5rem)]">
                <Label htmlFor={`${idPrefix}-country-of-origin`}>
                    {t('Country of origin')}{' '}
                    <FieldMarker
                        required={needs('requires_country_of_origin')}
                        missing={owes('requires_country_of_origin')}
                    />
                </Label>
                <Select
                    value={country}
                    onValueChange={(value) =>
                        setCountry(value as CountryOfOrigin)
                    }
                    disabled={disabled}
                >
                    <SelectTrigger
                        id={`${idPrefix}-country-of-origin`}
                        data-test="product-country-of-origin"
                        className={cn(
                            'w-full',
                            neededControlClass(
                                owes('requires_country_of_origin'),
                            ),
                        )}
                    >
                        <SelectValue placeholder={t('Select a country')} />
                    </SelectTrigger>
                    <SelectContent>
                        {availableCountries.map((availableCountry) => (
                            <SelectItem
                                key={availableCountry.value}
                                value={availableCountry.value}
                            >
                                {availableCountry.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <input
                    type="hidden"
                    name="country_of_origin"
                    value={country ?? ''}
                />
                <InputError message={errors.country_of_origin} />
            </div>
        </div>
    );
}
