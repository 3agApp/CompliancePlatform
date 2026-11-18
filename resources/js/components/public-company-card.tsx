import {
    Building2,
    ChevronRight,
    ExternalLink,
    Globe,
    Landmark,
    Mail,
    MapPin,
    Phone,
    UserRound,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import type { PublicLocale, PublicTranslate } from '@/lib/public-i18n';
import type { PublicCompany } from '@/types';

/**
 * The country's name in the language the page is read in now, which may
 * not be the one it was first served in.
 */
function countryName(
    company: PublicCompany,
    locale: PublicLocale,
): string | null {
    if (company.countryCode === null) {
        return company.country;
    }

    try {
        return (
            new Intl.DisplayNames([locale], { type: 'region' }).of(
                company.countryCode,
            ) ?? company.country
        );
    } catch {
        return company.country;
    }
}

/**
 * A website as people say it: no scheme, no trailing slash.
 */
function displayWebsite(url: string): string {
    return url.replace(/^https?:\/\//i, '').replace(/\/$/, '');
}

function hasDetails(company: PublicCompany): boolean {
    return [
        company.street,
        company.city,
        company.countryCode,
        company.phone,
        company.email,
        company.website,
        company.representedBy,
        company.vatNumber,
        company.mapUrl,
    ].some((value) => value !== null && value !== '');
}

function CompanyMark({
    company,
    size,
}: {
    company: PublicCompany;
    size: 'sm' | 'lg';
}) {
    const box = size === 'lg' ? 'size-16 rounded-2xl' : 'size-14 rounded-2xl';

    if (company.logoUrl) {
        return (
            <div
                className={`${box} flex shrink-0 items-center justify-center overflow-hidden border border-gray-100 bg-white p-1.5 shadow-sm`}
            >
                <img
                    src={company.logoUrl}
                    alt=""
                    className="size-full object-contain"
                    data-test="product-importer-logo"
                />
            </div>
        );
    }

    return (
        <div
            className={`${box} flex shrink-0 items-center justify-center bg-emerald-600 text-xl font-bold text-white shadow-md`}
        >
            {company.name.charAt(0).toUpperCase() || (
                <Building2 className="size-6" />
            )}
        </div>
    );
}

function DetailRow({
    icon: Icon,
    label,
    children,
}: {
    icon: LucideIcon;
    label: string;
    children: ReactNode;
}) {
    return (
        <div className="flex gap-3 py-3">
            <div className="flex size-9 shrink-0 items-center justify-center rounded-xl bg-gray-50 text-gray-500">
                <Icon className="size-4" />
            </div>
            <div className="min-w-0">
                <dt className="text-[10px] font-bold tracking-widest text-gray-400 uppercase">
                    {label}
                </dt>
                <dd className="mt-0.5 text-sm break-words text-gray-900">
                    {children}
                </dd>
            </div>
        </div>
    );
}

/**
 * Who places the product on the market, with their published company
 * details a tap away.
 *
 * The card names the company and where it is; the dialog behind it holds
 * the imprint -- address, contact, VAT number and a map. A company that
 * published nothing beyond its name gets the plain card it always had.
 */
export default function PublicCompanyCard({
    company,
    locale,
    t,
}: {
    company: PublicCompany;
    locale: PublicLocale;
    t: PublicTranslate;
}) {
    const country = countryName(company, locale);
    const place = [company.city, country].filter(Boolean).join(', ');
    const summary = (
        <div className="flex items-center gap-4">
            <CompanyMark company={company} size="sm" />
            <div className="min-w-0 flex-1">
                <p className="text-base font-bold text-gray-900">
                    {company.name}
                </p>
                {place && (
                    <p className="mt-0.5 flex items-start gap-1 text-sm text-gray-500">
                        <MapPin className="mt-0.5 size-3.5 shrink-0" />
                        {place}
                    </p>
                )}
            </div>
        </div>
    );

    const heading = (
        <p className="mb-4 text-[10px] font-bold tracking-widest text-gray-400 uppercase">
            {t('importedBy')}
        </p>
    );

    if (!hasDetails(company)) {
        return (
            <section
                className="overflow-hidden rounded-2xl border border-gray-100/80 bg-white shadow-sm"
                data-test="product-importer"
            >
                <div className="p-5">
                    {heading}
                    {summary}
                </div>
            </section>
        );
    }

    const address = [
        company.street,
        company.addressLine2,
        [company.postalCode, company.city].filter(Boolean).join(' '),
        country,
    ].filter(Boolean);

    return (
        <Dialog>
            <section
                className="overflow-hidden rounded-2xl border border-gray-100/80 bg-white shadow-sm"
                data-test="product-importer"
            >
                <DialogTrigger
                    className="block w-full p-5 text-left transition-colors hover:bg-gray-50 focus-visible:bg-gray-50 focus-visible:outline-none"
                    data-test="product-importer-details"
                >
                    {heading}
                    <div className="flex items-center gap-3">
                        <div className="min-w-0 flex-1">{summary}</div>
                        <span className="flex size-8 shrink-0 items-center justify-center rounded-full bg-emerald-50 text-emerald-700">
                            <span className="sr-only">
                                {t('companyDetails')}
                            </span>
                            <ChevronRight className="size-4" />
                        </span>
                    </div>
                </DialogTrigger>
            </section>

            <DialogContent
                className="max-h-[90dvh] gap-0 overflow-y-auto border-0 bg-white p-0 text-gray-900 sm:max-w-md"
                data-test="product-importer-dialog"
            >
                <div className="flex items-center gap-4 border-b border-gray-100 p-5 pr-12">
                    <CompanyMark company={company} size="lg" />
                    <div className="min-w-0">
                        <p className="text-[10px] font-bold tracking-widest text-gray-400 uppercase">
                            {t('importedBy')}
                        </p>
                        <DialogTitle className="mt-1 text-lg leading-tight font-bold">
                            {company.name}
                        </DialogTitle>
                        <DialogDescription className="sr-only">
                            {t('companyDetailsFor', { name: company.name })}
                        </DialogDescription>
                    </div>
                </div>

                <dl className="divide-y divide-gray-100 px-5">
                    {address.length > 0 && (
                        <DetailRow icon={MapPin} label={t('companyAddress')}>
                            <address className="not-italic">
                                {address.map((line) => (
                                    <span key={line} className="block">
                                        {line}
                                    </span>
                                ))}
                            </address>
                        </DetailRow>
                    )}
                    {company.representedBy && (
                        <DetailRow
                            icon={UserRound}
                            label={t('companyRepresentedBy')}
                        >
                            {company.representedBy}
                        </DetailRow>
                    )}
                    {company.phone && (
                        <DetailRow icon={Phone} label={t('companyPhone')}>
                            <a
                                href={`tel:${company.phone.replace(/[^\d+]/g, '')}`}
                                className="font-medium text-emerald-700 hover:underline"
                            >
                                {company.phone}
                            </a>
                        </DetailRow>
                    )}
                    {company.email && (
                        <DetailRow icon={Mail} label={t('companyEmail')}>
                            <a
                                href={`mailto:${company.email}`}
                                className="font-medium text-emerald-700 hover:underline"
                            >
                                {company.email}
                            </a>
                        </DetailRow>
                    )}
                    {company.website && (
                        <DetailRow icon={Globe} label={t('companyWebsite')}>
                            <a
                                href={company.website}
                                target="_blank"
                                rel="noopener noreferrer nofollow"
                                className="font-medium text-emerald-700 hover:underline"
                            >
                                {displayWebsite(company.website)}
                            </a>
                        </DetailRow>
                    )}
                    {company.vatNumber && (
                        <DetailRow
                            icon={Landmark}
                            label={t('companyVatNumber')}
                        >
                            <span className="font-mono">
                                {company.vatNumber}
                            </span>
                        </DetailRow>
                    )}
                </dl>

                {company.mapUrl && (
                    <div className="p-5 pt-2">
                        <a
                            href={company.mapUrl}
                            target="_blank"
                            rel="noopener noreferrer nofollow"
                            className="flex w-full items-center justify-center gap-2 rounded-xl bg-emerald-600 px-4 py-3 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-emerald-700"
                            data-test="product-importer-map"
                        >
                            <MapPin className="size-4" />
                            {t('openInGoogleMaps')}
                            <ExternalLink className="size-3.5 opacity-80" />
                        </a>
                    </div>
                )}
            </DialogContent>
        </Dialog>
    );
}
