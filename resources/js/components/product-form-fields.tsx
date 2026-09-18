import { useState } from 'react';
import InputError from '@/components/input-error';
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
    CountryOfOrigin,
    CountryOption,
    OrganizationType,
    Product,
    ProductCategoryOption,
    SupplierConnectionOption,
} from '@/types';

type FieldName =
    | 'name'
    | 'brand'
    | 'product_category_id'
    | 'ean'
    | 'internal_article_number'
    | 'supplier_article_number'
    | 'order_number'
    | 'country_of_origin'
    | 'supplier_connection_id';

type Props = {
    errors: Partial<Record<FieldName, string>>;
    availableCountries: CountryOption[];
    availableCategories: ProductCategoryOption[];
    availableConnections: SupplierConnectionOption[];
    viewerType: OrganizationType;
    product?: Product;
    disabled?: boolean;
    idPrefix?: string;
};

/**
 * Everything but the name is optional, so the marker is worth factoring out
 * rather than repeating on every label.
 */
function Optional() {
    return (
        <span className="text-muted-foreground font-normal">(optional)</span>
    );
}

export default function ProductFormFields({
    errors,
    availableCountries,
    availableCategories,
    availableConnections,
    viewerType,
    product,
    disabled = false,
    idPrefix = 'product',
}: Props) {
    const [country, setCountry] = useState<CountryOfOrigin | undefined>(
        product?.country_of_origin ?? undefined,
    );

    const [categoryId, setCategoryId] = useState<string | undefined>(
        product?.product_category_id
            ? String(product.product_category_id)
            : undefined,
    );

    /**
     * Only the distributor that owns a product chooses its supplier. The field
     * is not rendered for a supplier, and the server does not accept it from
     * them either.
     */
    const canAssignSupplier = viewerType === 'distributor';

    const [connectionId, setConnectionId] = useState<string | undefined>(
        product?.supplier_connection_id
            ? String(product.supplier_connection_id)
            : undefined,
    );

    return (
        <div className="grid gap-4">
            <div className="grid gap-2">
                <Label htmlFor={`${idPrefix}-name`}>Product name</Label>
                <Input
                    id={`${idPrefix}-name`}
                    name="name"
                    data-test="product-name"
                    defaultValue={product?.name ?? ''}
                    placeholder="Organic oat milk 1L"
                    disabled={disabled}
                    required
                />
                <InputError message={errors.name} />
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
                <div className="grid content-start gap-2">
                    <Label htmlFor={`${idPrefix}-brand`}>
                        Brand <Optional />
                    </Label>
                    <Input
                        id={`${idPrefix}-brand`}
                        name="brand"
                        data-test="product-brand"
                        defaultValue={product?.brand ?? ''}
                        placeholder="Magna-Tiles"
                        autoComplete="off"
                        disabled={disabled}
                    />
                    <InputError message={errors.brand} />
                </div>

                <div className="grid content-start gap-2">
                    <Label htmlFor={`${idPrefix}-category`}>
                        Category <Optional />
                    </Label>
                    <Select
                        value={categoryId}
                        onValueChange={setCategoryId}
                        disabled={disabled || availableCategories.length === 0}
                    >
                        <SelectTrigger
                            id={`${idPrefix}-category`}
                            data-test="product-category"
                            className="w-full"
                        >
                            <SelectValue placeholder="Select a category" />
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
                    <input
                        type="hidden"
                        name="product_category_id"
                        value={categoryId ?? ''}
                    />
                    <p className="text-muted-foreground text-xs">
                        {availableCategories.length === 0
                            ? 'No categories yet — add them under Categories.'
                            : 'Its legal family.'}
                    </p>
                    <InputError message={errors.product_category_id} />
                </div>
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
                <div className="grid content-start gap-2">
                    <Label htmlFor={`${idPrefix}-internal-article-number`}>
                        Internal article number <Optional />
                    </Label>
                    <Input
                        id={`${idPrefix}-internal-article-number`}
                        name="internal_article_number"
                        data-test="product-internal-article-number"
                        defaultValue={product?.internal_article_number ?? ''}
                        placeholder="ART-10294"
                        autoComplete="off"
                        disabled={disabled}
                    />
                    <p className="text-muted-foreground text-xs">
                        The distributor's own SKU.
                    </p>
                    <InputError message={errors.internal_article_number} />
                </div>

                <div className="grid content-start gap-2">
                    <Label htmlFor={`${idPrefix}-supplier-article-number`}>
                        Supplier article number <Optional />
                    </Label>
                    <Input
                        id={`${idPrefix}-supplier-article-number`}
                        name="supplier_article_number"
                        data-test="product-supplier-article-number"
                        defaultValue={product?.supplier_article_number ?? ''}
                        placeholder="MT-BLUE-32"
                        autoComplete="off"
                        disabled={disabled}
                    />
                    <p className="text-muted-foreground text-xs">
                        The manufacturer's SKU.
                    </p>
                    <InputError message={errors.supplier_article_number} />
                </div>
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
                <div className="grid content-start gap-2">
                    <Label htmlFor={`${idPrefix}-ean`}>
                        EAN / barcode <Optional />
                    </Label>
                    <Input
                        id={`${idPrefix}-ean`}
                        name="ean"
                        data-test="product-ean"
                        defaultValue={product?.ean ?? ''}
                        placeholder="4006381333931"
                        inputMode="numeric"
                        autoComplete="off"
                        disabled={disabled}
                    />
                    <InputError message={errors.ean} />
                </div>

                <div className="grid content-start gap-2">
                    <Label htmlFor={`${idPrefix}-order-number`}>
                        Order number <Optional />
                    </Label>
                    <Input
                        id={`${idPrefix}-order-number`}
                        name="order_number"
                        data-test="product-order-number"
                        defaultValue={product?.order_number ?? ''}
                        placeholder="PO-2026-0148"
                        autoComplete="off"
                        disabled={disabled}
                    />
                    <InputError message={errors.order_number} />
                </div>
            </div>

            <div className="grid gap-2">
                <Label htmlFor={`${idPrefix}-country-of-origin`}>
                    Country of origin <Optional />
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
                        className="w-full"
                    >
                        <SelectValue placeholder="Select a country" />
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

            {canAssignSupplier ? (
                <div className="grid gap-2">
                    <Label htmlFor={`${idPrefix}-supplier`}>Supplier</Label>
                    <Select
                        value={connectionId}
                        onValueChange={setConnectionId}
                        disabled={disabled || availableConnections.length === 0}
                    >
                        <SelectTrigger
                            id={`${idPrefix}-supplier`}
                            data-test="product-supplier"
                            className="w-full"
                        >
                            <SelectValue placeholder="Select a supplier" />
                        </SelectTrigger>
                        <SelectContent>
                            {availableConnections.map((connection) => (
                                <SelectItem
                                    key={connection.id}
                                    value={String(connection.id)}
                                >
                                    {connection.label}
                                    {connection.isPending
                                        ? ' (invitation pending)'
                                        : ''}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <input
                        type="hidden"
                        name="supplier_connection_id"
                        value={connectionId ?? ''}
                    />
                    {availableConnections.length === 0 ? (
                        <p className="text-muted-foreground text-xs">
                            Invite a supplier first — every product needs one.
                        </p>
                    ) : null}
                    <InputError message={errors.supplier_connection_id} />
                </div>
            ) : null}
        </div>
    );
}
