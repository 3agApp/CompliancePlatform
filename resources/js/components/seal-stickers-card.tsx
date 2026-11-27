import { useState } from 'react';
import PrintPdfButton from '@/components/print-pdf-button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { t, tc } from '@/lib/i18n';
import { pdf } from '@/routes/seal-stickers';

type Props = {
    organizationSlug: string;
    /** How many seals one sheet carries. */
    perSheet: number;
    /** The most sheets one print may ask for. */
    maxSheets: number;
    /** Leave the panel's own heading off, where the page already has one. */
    heading?: boolean;
};

/**
 * Print sheets of the round seal sticker: the seal alone, the same for
 * every product, on HERMA's round 40 mm labels.
 */
export default function SealStickersCard({
    organizationSlug,
    perSheet,
    maxSheets,
    heading = true,
}: Props) {
    const [sheets, setSheets] = useState('1');

    const count = Number(sheets);
    const valid = Number.isInteger(count) && count >= 1 && count <= maxSheets;

    return (
        <div
            className="workspace-panel flex flex-col gap-5 p-5 sm:flex-row sm:items-center"
            data-test="seal-stickers-card"
        >
            <img
                src="/brand/siegel_farbig_hell.png"
                alt={t('Swiss Product Seal')}
                className="size-24 shrink-0 self-center"
            />

            <div className="min-w-0 flex-1 space-y-3">
                <div className="space-y-1">
                    {heading ? (
                        <h2 className="text-sm font-medium">
                            {t('Seal stickers')}
                        </h2>
                    ) : null}
                    <p className="text-muted-foreground text-sm">
                        {t(
                            'The round seal on HERMA round 40 mm labels, :count to a sheet. Print at actual size.',
                            { count: perSheet },
                        )}
                    </p>
                </div>

                <div className="flex flex-wrap items-end gap-3">
                    <div className="space-y-1.5">
                        <Label htmlFor="seal-sheets">{t('Sheets')}</Label>
                        <Input
                            id="seal-sheets"
                            type="number"
                            min={1}
                            max={maxSheets}
                            value={sheets}
                            onChange={(event) => setSheets(event.target.value)}
                            aria-invalid={!valid}
                            className="w-24"
                            data-test="seal-sheets"
                        />
                    </div>

                    <PrintPdfButton
                        url={
                            pdf(organizationSlug, {
                                query: { sheets: valid ? count : 1 },
                            }).url
                        }
                        size="sm"
                        disabled={!valid}
                        data-test="seal-stickers-print"
                    >
                        {t('Print seals')}
                    </PrintPdfButton>

                    {valid ? (
                        <span
                            className="text-muted-foreground pb-2 text-sm"
                            data-test="seal-stickers-count"
                        >
                            {tc('1 seal|:count seals', count * perSheet)}
                        </span>
                    ) : (
                        <span className="text-destructive pb-2 text-sm">
                            {t('Between 1 and :max sheets.', {
                                max: maxSheets,
                            })}
                        </span>
                    )}
                </div>
            </div>
        </div>
    );
}
