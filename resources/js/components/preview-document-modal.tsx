import { Download, FileText } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { t } from '@/lib/i18n';
import { preview, show } from '@/routes/products/documents';
import type { ProductDocument } from '@/types';

type Props = {
    organizationSlug: string;
    productId: number;
    document: ProductDocument | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

/**
 * Show the file behind a document without collecting it first.
 *
 * Checking a paper is mostly a glance -- is this the right standard, is the
 * certificate still in date, is the label the one on the box -- and a glance
 * should not cost a file in the downloads folder and a trip to another
 * application. The download stays where it was for the times it is the file
 * itself that is wanted.
 *
 * Only PDFs and images reach here. The panel offers no preview for anything
 * else, because a viewer that renders nothing is worse than a link.
 */
export default function PreviewDocumentModal({
    organizationSlug,
    productId,
    document,
    open,
    onOpenChange,
}: Props) {
    /*
     * Nothing is asked of the server until there is something to show. A
     * mounted dialog with no document would otherwise fetch a file to sit
     * behind a closed dialog.
     */
    if (document === null || document.preview_kind === null) {
        return null;
    }

    const previewUrl = preview.url([organizationSlug, productId, document.id]);
    const downloadUrl = show.url([organizationSlug, productId, document.id]);

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent
                className="sm:max-w-4xl"
                data-test="preview-document-modal"
            >
                <DialogHeader>
                    <DialogTitle className="flex items-start gap-2 text-left">
                        <FileText className="text-muted-foreground mt-0.5 size-4 shrink-0" />
                        <span className="break-all">{document.name}</span>
                    </DialogTitle>
                    <DialogDescription>{document.type_label}</DialogDescription>
                </DialogHeader>

                {/*
                 * A fixed share of the window rather than the file's own
                 * shape: a tall certificate and a wide label both have to sit
                 * in the same dialog without moving the buttons off the
                 * bottom of the screen.
                 */}
                <div className="bg-muted flex h-[65vh] items-center justify-center overflow-hidden rounded-xl border">
                    {document.preview_kind === 'image' ? (
                        <img
                            src={previewUrl}
                            alt={document.name}
                            data-test="document-preview-image"
                            className="max-h-full max-w-full object-contain"
                        />
                    ) : (
                        /*
                         * An object rather than a frame, for the sake of what
                         * is written inside it. Not every browser has a PDF
                         * viewer -- a phone usually does not -- and the ones
                         * that do not render a frame as an empty box and say
                         * nothing. The fallback below shows instead, and
                         * offers the two ways through.
                         */
                        <object
                            data={previewUrl}
                            type="application/pdf"
                            aria-label={t('Preview of :name', {
                                name: document.name,
                            })}
                            data-test="document-preview-frame"
                            className="h-full w-full"
                        >
                            <div className="flex h-full flex-col items-center justify-center gap-3 p-6 text-center">
                                <p className="text-muted-foreground text-sm">
                                    {t(
                                        'This browser cannot show PDFs. Open it in a new tab or download it instead.',
                                    )}
                                </p>

                                <Button variant="secondary" asChild>
                                    <a
                                        href={previewUrl}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        data-test="document-preview-new-tab"
                                    >
                                        {t('Open in a new tab')}
                                    </a>
                                </Button>
                            </div>
                        </object>
                    )}
                </div>

                <DialogFooter className="gap-2">
                    <DialogClose asChild>
                        <Button variant="secondary">{t('Close')}</Button>
                    </DialogClose>

                    {/*
                     * A plain anchor, not an Inertia link: the response is a
                     * file, and an XHR visit would choke on it.
                     */}
                    <Button asChild>
                        <a
                            href={downloadUrl}
                            data-test="document-preview-download"
                        >
                            <Download className="size-4" />
                            {t('Download')}
                        </a>
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
