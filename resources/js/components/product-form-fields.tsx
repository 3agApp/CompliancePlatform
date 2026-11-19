import { Link } from '@inertiajs/react';
import { Factory, Plus } from 'lucide-react';
import { useState } from 'react';
import { CountryCombobox, countryFlag } from '@/components/country-combobox';
import CreateBrandModal from '@/components/create-brand-modal';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import type {
    BrandOption,
    CountryOfOrigin,
    CountryOption,
    CountryRestriction,
    ProductDetail,
    ProductRequirementKey,
} from '@/types';
import { t } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { edit as editOrganization } from '@/routes/organizations';

type FieldName =
    | 'name'
    | 'brand_id'
    | 'ean'
    | 'internal_article_number'
    | 'supplier_article_number'
    | 'order_number'
    | 'customs_tariff_number'
    | 'country_of_origin'
    | 'is_active'
    | 'short_description'
    | 'long_description';

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
    product?: ProductDetail;
    disabled?: boolean;
    idPrefix?: string;
    /**
     * Each trade's own list of countries its supplier produces in, keyed by
     * connection, for the trades that keep one. Looked up by the supplier
     * picked on the form, so changing it changes what is offered.
     */
    countryRestrictions?: Record<number, CountryRestriction>;
    /** Whether the person looking is the supplier, who can change the list. */
    viewerIsSupplier?: boolean;
};

/**
 * Up to this many countries are offered as a plain select; past it, as a
 * searchable list.
 */
const SHORT_COUNTRY_LIST = 8;

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

    return required ? <RequiredByTemplate /> : null;
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
    countryRestrictions = {},
    viewerIsSupplier = false,
}: Props) {
    const countryRestriction =
        countryRestrictions[supplierConnectionId] ?? null;
    const offeredCountries = countryRestriction
        ? availableCountries.filter(
              (option) =>
                  countryRestriction.countries.includes(option.value) ||
                  option.value === product?.country_of_origin,
          )
        : availableCountries;
    /**
     * A supplier that produces in one country only has answered the
     * question already, so an empty field starts on that country and a save
     * keeps it.
     */
    const onlyCountry =
        countryRestriction?.countries.length === 1
            ? countryRestriction.countries[0]
            : undefined;
    const preselected =
        !product?.country_of_origin && onlyCountry !== undefined;
    const [country, setCountry] = useState<CountryOfOrigin | undefined>(
        product?.country_of_origin ?? onlyCountry,
    );

    /**
     * Picking another supplier re-asks the question its list answers: a
     * country the new supplier does not produce in is dropped, and a
     * supplier with only one fills it in.
     */
    const [countriesFor, setCountriesFor] = useState(supplierConnectionId);

    if (countriesFor !== supplierConnectionId) {
        setCountriesFor(supplierConnectionId);

        if (
            countryRestriction &&
            (country === undefined ||
                !countryRestriction.countries.includes(country))
        ) {
            setCountry(onlyCountry);
        }
    }

    const [brandId, setBrandId] = useState<string | undefined>(
        product?.brand_id ? String(product.brand_id) : undefined,
    );

    const [brandDialogOpen, setBrandDialogOpen] = useState(false);
    const [isActive, setIsActive] = useState(product?.is_active ?? true);

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
                    {brands.length === 0 ? (
                        <p className="text-muted-foreground text-xs">
                            {supplierLabel
                                ? t(':supplier has no brands yet.', {
                                      supplier: supplierLabel,
                                  })
                                : t('This supplier has no brands yet.')}
                        </p>
                    ) : null}
                    <InputError message={errors.brand_id} />

                    <CreateBrandModal
                        organizationSlug={organizationSlug}
                        supplierConnectionId={supplierConnectionId}
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
                {offeredCountries.length > SHORT_COUNTRY_LIST ? (
                    <CountryCombobox
                        id={`${idPrefix}-country-of-origin`}
                        name="country_of_origin"
                        options={offeredCountries}
                        value={country ?? null}
                        onChange={(value) => setCountry(value ?? undefined)}
                        disabled={disabled}
                        invalid={errors.country_of_origin !== undefined}
                        triggerClassName={neededControlClass(
                            owes('requires_country_of_origin'),
                        )}
                        data-test="product-country-of-origin"
                    />
                ) : (
                    <>
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
                                <SelectValue
                                    placeholder={t('Select a country')}
                                />
                            </SelectTrigger>
                            <SelectContent>
                                {offeredCountries.map((availableCountry) => (
                                    <SelectItem
                                        key={availableCountry.value}
                                        value={availableCountry.value}
                                        data-test={`country-option-${availableCountry.value}`}
                                    >
                                        <span aria-hidden="true">
                                            {countryFlag(
                                                availableCountry.value,
                                            )}
                                        </span>
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
                    </>
                )}
                {countryRestriction && (
                    <CountryRestrictionHint
                        restriction={countryRestriction}
                        preselected={
                            preselected ||
                            (country === onlyCountry &&
                                product?.country_of_origin !== onlyCountry)
                        }
                        settingsUrl={
                            viewerIsSupplier
                                ? `${editOrganization.url(organizationSlug)}#profile`
                                : null
                        }
                    />
                )}
                <InputError message={errors.country_of_origin} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor={`${idPrefix}-short-description`}>
                    {t('Short description')}
                </Label>
                <Textarea
                    id={`${idPrefix}-short-description`}
                    name="short_description"
                    data-test="product-short-description"
                    defaultValue={product?.short_description ?? ''}
                    rows={3}
                    disabled={disabled}
                />
                <InputError message={errors.short_description} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor={`${idPrefix}-long-description`}>
                    {t('Long description')}
                </Label>
                <Textarea
                    id={`${idPrefix}-long-description`}
                    name="long_description"
                    data-test="product-long-description"
                    defaultValue={product?.long_description ?? ''}
                    rows={6}
                    disabled={disabled}
                />
                <InputError message={errors.long_description} />
            </div>

            <div className="flex flex-wrap items-center gap-3">
                <Checkbox
                    id={`${idPrefix}-is-active`}
                    data-test="product-is-active"
                    checked={isActive}
                    onCheckedChange={(value) => setIsActive(value === true)}
                    disabled={disabled}
                />
                <input
                    type="hidden"
                    name="is_active"
                    value={isActive ? '1' : '0'}
                />
                <Label
                    htmlFor={`${idPrefix}-is-active`}
                    className="font-normal"
                >
                    {t('Active — still sold in the webshop')}
                </Label>
                <InputError message={errors.is_active} />
            </div>
        </div>
    );
}

/**
 * Why the country list is short, and where to change that.
 */
function CountryRestrictionHint({
    restriction,
    preselected,
    settingsUrl,
}: {
    restriction: CountryRestriction;
    preselected: boolean;
    settingsUrl: string | null;
}) {
    const message =
        restriction.countries.length === 1
            ? preselected
                ? t(
                      ':supplier produces only in this country, so it is filled in. Save to keep it.',
                      {
                          supplier: restriction.supplier,
                      },
                  )
                : t(':supplier produces only in this country.', {
                      supplier: restriction.supplier,
                  })
            : t('Limited to the countries :supplier produces in.', {
                  supplier: restriction.supplier,
              });

    return (
        <p
            className="text-muted-foreground flex items-start gap-1.5 text-xs"
            data-test="product-country-restriction"
        >
            <Factory className="mt-px size-3.5 shrink-0" />
            <span>
                {message}{' '}
                {settingsUrl && (
                    <Link
                        href={settingsUrl}
                        className="text-foreground underline underline-offset-4"
                    >
                        {t('Change your countries')}
                    </Link>
                )}
            </span>
        </p>
    );
}
