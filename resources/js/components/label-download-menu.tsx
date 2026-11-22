import { FileText, Printer } from 'lucide-react';
import type { ReactNode } from 'react';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { t } from '@/lib/i18n';
import { pdf } from '@/routes/products/label-batches';

type Props = {
    organizationSlug: string;
    productId: number;
    batchId: number;
    /** The button that opens the menu. */
    children: ReactNode;
};

/**
 * Where a run's labels are to be printed: on a label printer's roll, or on
 * A4 sheets of die-cut HERMA 4357 labels in an ordinary office printer.
 *
 * Both are the same serials, so either can be fetched again at any time.
 */
export default function LabelDownloadMenu({
    organizationSlug,
    productId,
    batchId,
    children,
}: Props) {
    const route = [organizationSlug, productId, batchId] as const;

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>{children}</DropdownMenuTrigger>
            <DropdownMenuContent align="end">
                <DropdownMenuItem asChild>
                    <a href={pdf([...route]).url} data-test="label-pdf-roll">
                        <Printer className="size-4" />
                        {t('Label printer (roll)')}
                    </a>
                </DropdownMenuItem>
                <DropdownMenuItem asChild>
                    <a
                        href={
                            pdf([...route], {
                                query: { sheet: 'herma-4357' },
                            }).url
                        }
                        data-test="label-pdf-herma-4357"
                    >
                        <FileText className="size-4" />
                        {t('A4 sheet · HERMA 4357')}
                    </a>
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
