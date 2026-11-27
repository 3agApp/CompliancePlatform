import { Head, usePage } from '@inertiajs/react';
import SealStickersCard from '@/components/seal-stickers-card';
import { t } from '@/lib/i18n';

type Props = {
    perSheet: number;
    maxSheets: number;
};

/**
 * Sheets of the round seal sticker, printed in bulk -- for stock rather than
 * for any one product.
 */
export default function SealStickers({ perSheet, maxSheets }: Props) {
    const { currentOrganization } = usePage().props;
    const organizationSlug = currentOrganization?.slug ?? '';

    return (
        <>
            <Head title={t('Seal stickers')} />

            <div className="workspace-page">
                <h1 className="page-title">{t('Seal stickers')}</h1>

                <SealStickersCard
                    organizationSlug={organizationSlug}
                    perSheet={perSheet}
                    maxSheets={maxSheets}
                    heading={false}
                />
            </div>
        </>
    );
}
