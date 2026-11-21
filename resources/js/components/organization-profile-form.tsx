import { Form } from '@inertiajs/react';
import {
    Building2,
    ExternalLink,
    Factory,
    Globe,
    Mail,
    MapPin,
    Phone,
} from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import {
    CountryCombobox,
    CountryMultiSelect,
    countryFlag,
} from '@/components/country-combobox';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import LogoUploadField from '@/components/logo-upload-field';
import SaveButton from '@/components/save-button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { t } from '@/lib/i18n';
import {
    destroy as destroyLogo,
    store as storeLogo,
} from '@/routes/organizations/logo';
import { update as updateProfile } from '@/routes/organizations/profile';
import type { Organization, OrganizationProfile } from '@/types';
import type { CountryOption } from '@/types/products';

/** The most countries a supplier may list, matching the server's limit. */
const MAX_PRODUCTION_COUNTRIES = 30;

type Props = {
    organization: Organization;
    profile: OrganizationProfile;
    availableCountries: CountryOption[];
    canUpdate: boolean;
};

/**
 * The company behind the organization: logo, address, contact details and,
 * for a supplier, where it produces.
 *
 * A distributor's details are what a product's public page shows as the
 * company placing it on the market, so the section says so up front.
 */
export default function OrganizationProfileForm({
    organization,
    profile,
    availableCountries,
    canUpdate,
}: Props) {
    const isSupplier = organization.type === 'supplier';
    const [country, setCountry] = useState<string | null>(profile.country);
    const [productionCountries, setProductionCountries] = useState<string[]>(
        profile.productionCountries ?? [],
    );

    if (!canUpdate) {
        return (
            <div id="profile" className="scroll-mt-20 space-y-6">
                <Heading variant="small" title={t('Company profile')} />
                <ProfileSummary
                    organization={organization}
                    profile={profile}
                    availableCountries={availableCountries}
                />
            </div>
        );
    }

    return (
        <div id="profile" className="scroll-mt-20 space-y-6">
            <Heading variant="small" title={t('Company profile')} />

            <LogoField organization={organization} logoUrl={profile.logoUrl} />

            <Form
                {...updateProfile.form(organization.slug)}
                options={{ preserveScroll: true }}
                className="space-y-8"
                data-test="organization-profile-form"
            >
                {({ errors, processing, isDirty, recentlySuccessful }) => (
                    <>
                        <FieldGroup
                            icon={<MapPin className="size-4" />}
                            title={t('Address')}
                        >
                            <div className="grid gap-2">
                                <Label htmlFor="profile-street">
                                    {t('Street and number')}
                                </Label>
                                <Input
                                    id="profile-street"
                                    name="street"
                                    autoComplete="address-line1"
                                    defaultValue={profile.street ?? ''}
                                    data-test="profile-street"
                                />
                                <InputError message={errors.street} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="profile-address-line-2">
                                    {t('Address line 2')}
                                </Label>
                                <Input
                                    id="profile-address-line-2"
                                    name="address_line_2"
                                    autoComplete="address-line2"
                                    placeholder={t('Building, floor, P.O. box')}
                                    defaultValue={profile.addressLine2 ?? ''}
                                />
                                <InputError message={errors.address_line_2} />
                            </div>
                            <div className="grid gap-4 sm:grid-cols-[10rem_1fr]">
                                <div className="grid gap-2">
                                    <Label htmlFor="profile-postal-code">
                                        {t('Postal code')}
                                    </Label>
                                    <Input
                                        id="profile-postal-code"
                                        name="postal_code"
                                        autoComplete="postal-code"
                                        defaultValue={profile.postalCode ?? ''}
                                        data-test="profile-postal-code"
                                    />
                                    <InputError message={errors.postal_code} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="profile-city">
                                        {t('City')}
                                    </Label>
                                    <Input
                                        id="profile-city"
                                        name="city"
                                        autoComplete="address-level2"
                                        defaultValue={profile.city ?? ''}
                                        data-test="profile-city"
                                    />
                                    <InputError message={errors.city} />
                                </div>
                            </div>
                            <div className="grid gap-2 sm:max-w-sm">
                                <Label htmlFor="profile-country">
                                    {t('Country')}
                                </Label>
                                <CountryCombobox
                                    id="profile-country"
                                    name="country"
                                    options={availableCountries}
                                    value={country}
                                    onChange={setCountry}
                                    invalid={errors.country !== undefined}
                                    data-test="profile-country"
                                />
                                <InputError message={errors.country} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="profile-map-url">
                                    {t('Google Maps link')}
                                </Label>
                                <Input
                                    id="profile-map-url"
                                    name="map_url"
                                    inputMode="url"
                                    placeholder="https://maps.app.goo.gl/…"
                                    defaultValue={profile.mapUrl ?? ''}
                                    data-test="profile-map-url"
                                />
                                {profile.mapUrl && (
                                    <a
                                        href={profile.mapUrl}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="inline-flex items-center gap-1 text-xs underline underline-offset-4"
                                    >
                                        {t('Open the saved link')}
                                        <ExternalLink className="size-3" />
                                    </a>
                                )}
                                <InputError message={errors.map_url} />
                            </div>
                        </FieldGroup>

                        <FieldGroup
                            icon={<Phone className="size-4" />}
                            title={t('Contact')}
                        >
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="profile-phone">
                                        {t('Phone')}
                                    </Label>
                                    <Input
                                        id="profile-phone"
                                        name="phone"
                                        type="tel"
                                        autoComplete="tel"
                                        placeholder="+41 61 123 45 67"
                                        defaultValue={profile.phone ?? ''}
                                        data-test="profile-phone"
                                    />
                                    <InputError message={errors.phone} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="profile-email">
                                        {t('Email')}
                                    </Label>
                                    <Input
                                        id="profile-email"
                                        name="email"
                                        type="email"
                                        autoComplete="email"
                                        placeholder="info@example.com"
                                        defaultValue={profile.email ?? ''}
                                        data-test="profile-email"
                                    />
                                    <InputError message={errors.email} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="profile-website">
                                        {t('Website')}
                                    </Label>
                                    <Input
                                        id="profile-website"
                                        name="website"
                                        inputMode="url"
                                        autoComplete="url"
                                        placeholder="www.example.com"
                                        defaultValue={profile.website ?? ''}
                                        data-test="profile-website"
                                    />
                                    <InputError message={errors.website} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="profile-represented-by">
                                        {t('Represented by')}
                                    </Label>
                                    <Input
                                        id="profile-represented-by"
                                        name="represented_by"
                                        autoComplete="name"
                                        placeholder={t('Managing director')}
                                        defaultValue={
                                            profile.representedBy ?? ''
                                        }
                                        data-test="profile-represented-by"
                                    />
                                    <InputError
                                        message={errors.represented_by}
                                    />
                                </div>
                            </div>
                        </FieldGroup>

                        <FieldGroup
                            icon={<Building2 className="size-4" />}
                            title={t('Legal')}
                        >
                            <div className="grid gap-2 sm:max-w-sm">
                                <Label htmlFor="profile-vat-number">
                                    {t('VAT / UID number')}
                                </Label>
                                <Input
                                    id="profile-vat-number"
                                    name="vat_number"
                                    placeholder="CHE-123.456.789"
                                    defaultValue={profile.vatNumber ?? ''}
                                    data-test="profile-vat-number"
                                />
                                <InputError message={errors.vat_number} />
                            </div>
                        </FieldGroup>

                        {isSupplier && (
                            <FieldGroup
                                icon={<Factory className="size-4" />}
                                title={t('Where you produce')}
                            >
                                <CountryMultiSelect
                                    id="profile-production-countries"
                                    name="production_countries"
                                    options={availableCountries}
                                    value={productionCountries}
                                    onChange={setProductionCountries}
                                    max={MAX_PRODUCTION_COUNTRIES}
                                    data-test="profile-production-countries"
                                />
                                <InputError
                                    message={
                                        errors.production_countries ??
                                        Object.entries(errors).find(([key]) =>
                                            key.startsWith(
                                                'production_countries.',
                                            ),
                                        )?.[1]
                                    }
                                />
                            </FieldGroup>
                        )}

                        <SaveButton
                            processing={processing}
                            isDirty={isDirty}
                            recentlySuccessful={recentlySuccessful}
                            data-test="profile-save-button"
                        />
                    </>
                )}
            </Form>
        </div>
    );
}

function FieldGroup({
    icon,
    title,
    children,
}: {
    icon: ReactNode;
    title: string;
    children: ReactNode;
}) {
    return (
        <fieldset className="space-y-4">
            <legend className="text-muted-foreground mb-4 flex items-center gap-2 text-xs font-semibold tracking-wide uppercase">
                {icon}
                {title}
            </legend>
            {children}
        </fieldset>
    );
}

/**
 * The organization's logo, uploaded the moment it is chosen or dropped.
 */
function LogoField({
    organization,
    logoUrl,
}: {
    organization: Organization;
    logoUrl: string | null;
}) {
    return (
        <LogoUploadField
            logoUrl={logoUrl}
            alt={t(':name logo', { name: organization.name })}
            storeUrl={storeLogo.url(organization.slug)}
            destroyUrl={destroyLogo.url(organization.slug)}
            testIdPrefix="profile-logo"
        />
    );
}

/**
 * The profile as read by somebody who may not change it.
 */
function ProfileSummary({
    organization,
    profile,
    availableCountries,
}: {
    organization: Organization;
    profile: OrganizationProfile;
    availableCountries: CountryOption[];
}) {
    const address = [
        profile.street,
        profile.addressLine2,
        [profile.postalCode, profile.city].filter(Boolean).join(' '),
        profile.countryLabel,
    ].filter(Boolean);

    type Row = { icon: ReactNode; label: string; value: string };

    const candidates: (Row | false | null | '')[] = [
        address.length > 0 && {
            icon: <MapPin className="size-4" />,
            label: t('Address'),
            value: address.join(', '),
        },
        profile.mapUrl && {
            icon: <MapPin className="size-4" />,
            label: t('Google Maps link'),
            value: profile.mapUrl,
        },
        profile.phone && {
            icon: <Phone className="size-4" />,
            label: t('Phone'),
            value: profile.phone,
        },
        profile.email && {
            icon: <Mail className="size-4" />,
            label: t('Email'),
            value: profile.email,
        },
        profile.website && {
            icon: <Globe className="size-4" />,
            label: t('Website'),
            value: profile.website,
        },
        profile.vatNumber && {
            icon: <Building2 className="size-4" />,
            label: t('VAT / UID number'),
            value: profile.vatNumber,
        },
        profile.productionCountries &&
            profile.productionCountries.length > 0 && {
                icon: <Factory className="size-4" />,
                label: t('Where you produce'),
                value: profile.productionCountries
                    .map(
                        (code) =>
                            `${countryFlag(code)} ${availableCountries.find((option) => option.value === code)?.label ?? code}`,
                    )
                    .join(', '),
            },
    ];
    const rows = candidates.filter((row): row is Row => Boolean(row));

    return (
        <div
            className="flex flex-wrap items-start gap-5"
            data-test="organization-profile"
        >
            {profile.logoUrl && (
                <img
                    src={profile.logoUrl}
                    alt={t(':name logo', { name: organization.name })}
                    className="size-20 rounded-xl border bg-white object-contain p-2"
                />
            )}
            {rows.length === 0 ? (
                <p className="text-muted-foreground text-sm">
                    {t('No company details yet.')}
                </p>
            ) : (
                <dl className="grid flex-1 gap-3 text-sm">
                    {rows.map((row) => (
                        <div key={row.label} className="flex gap-3">
                            <dt
                                className="text-muted-foreground mt-0.5"
                                title={row.label}
                            >
                                {row.icon}
                                <span className="sr-only">{row.label}</span>
                            </dt>
                            <dd className="font-medium break-words">
                                {row.value}
                            </dd>
                        </div>
                    ))}
                </dl>
            )}
        </div>
    );
}
