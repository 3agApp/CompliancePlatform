import { countryName } from '@/lib/countries';
import { currentLocale, t } from '@/lib/i18n';
import type { CompanyProfile } from '@/types';

/**
 * Determine whether anybody has filled in any of the company details.
 */
export function hasCompanyProfile(profile: CompanyProfile | null): boolean {
    return (
        profile !== null &&
        Object.values(profile).some((value) => value !== null && value !== '')
    );
}

/**
 * The company details an organization keeps about itself, read-only.
 * Fields nobody filled in are left out rather than shown as dashes.
 */
export default function CompanyProfileDetails({
    profile,
}: {
    profile: CompanyProfile;
}) {
    const locale = currentLocale();
    const cityLine = [profile.postal_code, profile.city]
        .filter(Boolean)
        .join(' ');
    const address = [
        profile.street,
        cityLine,
        countryName(profile.country, locale),
    ].filter(Boolean);

    const rows: { label: string; value: React.ReactNode; test: string }[] = [];

    if (address.length > 0) {
        rows.push({
            label: t('Address'),
            test: 'address',
            value: address.map((line) => (
                <span key={line} className="block">
                    {line}
                </span>
            )),
        });
    }

    if (profile.represented_by) {
        rows.push({
            label: t('Represented by'),
            test: 'represented-by',
            value: profile.represented_by,
        });
    }

    if (profile.phone) {
        rows.push({
            label: t('Phone'),
            test: 'phone',
            value: (
                <a
                    href={`tel:${profile.phone}`}
                    className="underline-offset-4 hover:underline"
                >
                    {profile.phone}
                </a>
            ),
        });
    }

    if (profile.email) {
        rows.push({
            label: t('Email'),
            test: 'email',
            value: (
                <a
                    href={`mailto:${profile.email}`}
                    className="underline-offset-4 hover:underline"
                >
                    {profile.email}
                </a>
            ),
        });
    }

    if (profile.website) {
        rows.push({
            label: t('Website'),
            test: 'website',
            value: (
                <a
                    href={profile.website}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="break-all underline-offset-4 hover:underline"
                >
                    {profile.website}
                </a>
            ),
        });
    }

    if (profile.vat_number) {
        rows.push({
            label: t('VAT / UID number'),
            test: 'vat-number',
            value: profile.vat_number,
        });
    }

    return (
        <dl
            className="grid gap-4 text-sm sm:grid-cols-2"
            data-test="company-profile-details"
        >
            {rows.map((row) => (
                <div key={row.test} data-test={`company-profile-${row.test}`}>
                    <dt className="text-muted-foreground">{row.label}</dt>
                    <dd className="font-medium">{row.value}</dd>
                </div>
            ))}
        </dl>
    );
}
