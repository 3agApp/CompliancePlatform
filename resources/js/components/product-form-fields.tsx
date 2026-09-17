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
import type { CountryOfOrigin, CountryOption, Product } from '@/types';

type Props = {
    errors: Partial<Record<'name' | 'ean' | 'country_of_origin', string>>;
    availableCountries: CountryOption[];
    product?: Product;
    disabled?: boolean;
    idPrefix?: string;
};

export default function ProductFormFields({
    errors,
    availableCountries,
    product,
    disabled = false,
    idPrefix = 'product',
}: Props) {
    const [country, setCountry] = useState<CountryOfOrigin | undefined>(
        product?.country_of_origin ?? undefined,
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
        </div>
    );
}
