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
import { countryOptions } from '@/lib/countries';
import { currentLocale, t } from '@/lib/i18n';
import type { CompanyProfile } from '@/types';

type Props = {
    profile: CompanyProfile;
    countries: string[];
    errors: Partial<Record<keyof CompanyProfile, string>>;
};

type TextField = {
    name: Exclude<keyof CompanyProfile, 'country'>;
    label: string;
    type?: string;
    autoComplete?: string;
    className?: string;
};

/**
 * The company details an organization keeps about itself, as named inputs
 * for an Inertia <Form> to submit alongside its other fields.
 */
export default function CompanyProfileFields({
    profile,
    countries,
    errors,
}: Props) {
    const locale = currentLocale();
    const [country, setCountry] = useState(profile.country ?? '');
    const options = countryOptions(countries, locale);

    const address: TextField[] = [
        {
            name: 'street',
            label: t('Street and number'),
            autoComplete: 'street-address',
            className: 'sm:col-span-2',
        },
        {
            name: 'postal_code',
            label: t('Postal code'),
            autoComplete: 'postal-code',
        },
        { name: 'city', label: t('City'), autoComplete: 'address-level2' },
    ];

    const contact: TextField[] = [
        { name: 'phone', label: t('Phone'), type: 'tel', autoComplete: 'tel' },
        {
            name: 'email',
            label: t('Email'),
            type: 'email',
            autoComplete: 'email',
        },
        {
            name: 'website',
            label: t('Website'),
            type: 'url',
            autoComplete: 'url',
        },
        { name: 'vat_number', label: t('VAT / UID number') },
        { name: 'represented_by', label: t('Represented by') },
    ];

    const renderField = (field: TextField) => (
        <div key={field.name} className={`grid gap-2 ${field.className ?? ''}`}>
            <Label htmlFor={`company-${field.name}`}>{field.label}</Label>
            <Input
                id={`company-${field.name}`}
                name={field.name}
                type={field.type ?? 'text'}
                autoComplete={field.autoComplete}
                defaultValue={profile[field.name] ?? ''}
                data-test={`company-${field.name}`}
            />
            <InputError message={errors[field.name]} />
        </div>
    );

    return (
        <div className="grid gap-4 sm:grid-cols-2" data-test="company-profile">
            {address.map(renderField)}

            <div className="grid gap-2">
                <Label htmlFor="company-country">{t('Country')}</Label>
                {/*
                 * Named on the select itself, so choosing a country raises a
                 * change event the form hears and Save turns on.
                 */}
                <Select
                    name="country"
                    value={country}
                    onValueChange={setCountry}
                >
                    <SelectTrigger
                        id="company-country"
                        data-test="company-country"
                    >
                        <SelectValue placeholder={t('Choose a country')} />
                    </SelectTrigger>
                    <SelectContent>
                        {options.map((option) => (
                            <SelectItem key={option.value} value={option.value}>
                                {option.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <InputError message={errors.country} />
            </div>

            {contact.map(renderField)}
        </div>
    );
}
