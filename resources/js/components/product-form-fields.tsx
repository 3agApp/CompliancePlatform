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
    SupplierConnectionOption,
} from '@/types';

type Props = {
    errors: Partial<
        Record<
            'name' | 'ean' | 'country_of_origin' | 'supplier_connection_id',
            string
        >
    >;
    availableCountries: CountryOption[];
    availableConnections: SupplierConnectionOption[];
    viewerType: OrganizationType;
    product?: Product;
    disabled?: boolean;
    idPrefix?: string;
};

export default function ProductFormFields({
    errors,
    availableCountries,
    availableConnections,
    viewerType,
    product,
    disabled = false,
    idPrefix = 'product',
}: Props) {
    const [country, setCountry] = useState<CountryOfOrigin | undefined>(
        product?.country_of_origin ?? undefined,
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

            <div className="grid gap-2">
                <Label htmlFor={`${idPrefix}-ean`}>
                    EAN / barcode{' '}
                    <span className="text-muted-foreground font-normal">
                        (optional)
                    </span>
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

            <div className="grid gap-2">
                <Label htmlFor={`${idPrefix}-country-of-origin`}>
                    Country of origin{' '}
                    <span className="text-muted-foreground font-normal">
                        (optional)
                    </span>
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
