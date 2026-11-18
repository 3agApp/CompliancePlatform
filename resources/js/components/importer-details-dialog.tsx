import { Building2 } from 'lucide-react';
import type { ReactNode } from 'react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { countryName } from '@/lib/countries';
import type { PublicLocale, PublicTranslate } from '@/lib/public-i18n';
import type { PublicImporter } from '@/types';

type Props = {
    importer: PublicImporter;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    locale: PublicLocale;
    t: PublicTranslate;
};

/**
 * The imprint of the company that places the product on the market, as it
 * keeps it about itself: where it is based, who answers for it and how to
 * reach it. Fields it has not filled in are left out.
 */
export default function ImporterDetailsDialog({
    importer,
    open,
    onOpenChange,
    locale,
    t,
}: Props) {
    const { profile } = importer;
    const address = [
        profile.street,
        [profile.postal_code, profile.city].filter(Boolean).join(' '),
        countryName(profile.country, locale === 'de' ? 'de-CH' : 'en-GB'),
    ].filter(Boolean);

    const rows: { key: string; label: string; value: ReactNode }[] = [];

    if (address.length > 0) {
        rows.push({
            key: 'address',
            label: t('importerAddress'),
            value: address.map((line) => (
                <span key={line} className="block">
                    {line}
                </span>
            )),
        });
    }

    if (profile.represented_by) {
        rows.push({
            key: 'represented-by',
            label: t('importerRepresentedBy'),
            value: profile.represented_by,
        });
    }

    if (profile.phone) {
        rows.push({
            key: 'phone',
            label: t('importerPhone'),
            value: (
                <a
                    href={`tel:${profile.phone}`}
                    className="text-emerald-700 hover:underline"
                >
                    {profile.phone}
                </a>
            ),
        });
    }

    if (profile.email) {
        rows.push({
            key: 'email',
            label: t('importerEmail'),
            value: (
                <a
                    href={`mailto:${profile.email}`}
                    className="break-all text-emerald-700 hover:underline"
                >
                    {profile.email}
                </a>
            ),
        });
    }

    if (profile.website) {
        rows.push({
            key: 'website',
            label: t('importerWebsite'),
            value: (
                <a
                    href={profile.website}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="break-all text-emerald-700 hover:underline"
                >
                    {profile.website.replace(/^https?:\/\//, '')}
                </a>
            ),
        });
    }

    if (profile.vat_number) {
        rows.push({
            key: 'vat',
            label: t('importerVat'),
            value: profile.vat_number,
        });
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent
                className="bg-white text-gray-900"
                data-test="importer-details"
            >
                <DialogHeader>
                    <div className="mb-2 flex size-12 items-center justify-center rounded-2xl bg-emerald-600 text-white shadow-md">
                        <Building2 className="size-6" />
                    </div>
                    <DialogTitle className="text-left">
                        {importer.name}
                    </DialogTitle>
                    <DialogDescription className="text-left text-gray-500">
                        {t('importerDetails')}
                    </DialogDescription>
                </DialogHeader>

                <dl className="divide-y divide-gray-100 text-sm">
                    {rows.map((row) => (
                        <div
                            key={row.key}
                            className="grid grid-cols-[8rem_1fr] gap-3 py-2.5"
                            data-test={`importer-${row.key}`}
                        >
                            <dt className="text-gray-500">{row.label}</dt>
                            <dd className="font-medium break-words text-gray-900">
                                {row.value}
                            </dd>
                        </div>
                    ))}
                </dl>
            </DialogContent>
        </Dialog>
    );
}
