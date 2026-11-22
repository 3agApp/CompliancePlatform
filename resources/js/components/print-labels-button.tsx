import { Printer } from 'lucide-react';
import { useState } from 'react';
import type { ComponentProps, ReactNode } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { t } from '@/lib/i18n';
import { pdf } from '@/routes/products/label-batches';

type Props = {
    organizationSlug: string;
    productId: number;
    batchId: number;
    /** Text beside the icon; leave it out for an icon-only button. */
    children?: ReactNode;
} & Pick<ComponentProps<typeof Button>, 'variant' | 'size' | 'aria-label'>;

const FRAME_ID = 'label-print-frame';

/**
 * Browsers that cannot print a PDF from a hidden frame -- Safari prints the
 * frame's page instead, and phones have no print dialog for it -- are given
 * the sheet in a tab of its own, where their own Print or Share works.
 */
function printsFromFrame(): boolean {
    const agent = navigator.userAgent;

    const isSafari =
        /^((?!chrome|chromium|android|crios|fxios|edg).)*safari/i.test(agent);
    const isPhoneOrTablet = /android|iphone|ipad|ipod|mobile/i.test(agent);

    return !isSafari && !isPhoneOrTablet;
}

/**
 * Load the sheet into a hidden frame and open the print dialog on it.
 *
 * The sheet is fetched first rather than pointed at, so a failure -- a run
 * withdrawn in another tab, a lost session -- is reported instead of the
 * error page being printed onto a sheet of stickers.
 */
async function printSheet(url: string): Promise<void> {
    const response = await fetch(url, { credentials: 'same-origin' });

    if (
        !response.ok ||
        !response.headers.get('Content-Type')?.includes('application/pdf')
    ) {
        throw new Error(
            `The label sheet could not be loaded (${response.status}).`,
        );
    }

    const blobUrl = URL.createObjectURL(await response.blob());

    const previous = document.getElementById(
        FRAME_ID,
    ) as HTMLIFrameElement | null;

    if (previous) {
        URL.revokeObjectURL(previous.src);
        previous.remove();
    }

    const frame = document.createElement('iframe');
    frame.id = FRAME_ID;
    frame.dataset.test = FRAME_ID;
    frame.title = 'Label sheet';
    /* Not display: none -- a frame that is not drawn is not printed either. */
    frame.style.cssText =
        'position: fixed; right: 0; bottom: 0; width: 0; height: 0; border: 0; opacity: 0;';

    await new Promise<void>((resolve) => {
        frame.addEventListener('load', () => resolve(), { once: true });
        frame.src = blobUrl;
        document.body.appendChild(frame);
    });

    frame.contentWindow?.focus();
    frame.contentWindow?.print();
}

/**
 * Send a run's labels to the printer: the A4 sticker sheets, at actual size,
 * straight into the browser's print dialog.
 */
export default function PrintLabelsButton({
    organizationSlug,
    productId,
    batchId,
    children,
    ...buttonProps
}: Props) {
    const [preparing, setPreparing] = useState(false);

    const url = pdf([organizationSlug, productId, batchId]).url;

    const print = () => {
        if (!printsFromFrame()) {
            window.open(url, '_blank', 'noopener');

            return;
        }

        setPreparing(true);

        printSheet(url)
            .catch(() =>
                toast.error(
                    t(
                        'The labels could not be prepared for printing. Please try again.',
                    ),
                ),
            )
            .finally(() => setPreparing(false));
    };

    return (
        <Button
            {...buttonProps}
            onClick={print}
            disabled={preparing}
            data-test="label-batch-pdf"
        >
            {preparing ? (
                <Spinner className="h-4 w-4" />
            ) : (
                <Printer className="h-4 w-4" />
            )}
            {children}
        </Button>
    );
}
