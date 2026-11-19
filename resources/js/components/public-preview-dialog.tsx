import { Download, FileText, ImageIcon } from 'lucide-react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import type { PublicTranslate } from '@/lib/public-i18n';

/**
 * What the public page can show in its viewer: a picture of the article or
 * one of its released PDFs, both already watermarked by the server.
 */
export type PublicPreview = {
    kind: 'image' | 'pdf';
    name: string;
    url: string;
    /** Where the file is collected from; absent for a picture. */
    downloadUrl?: string;
};

type Props = {
    preview: PublicPreview | null;
    onClose: () => void;
    t: PublicTranslate;
};

/**
 * Show a picture or a PDF from the public page without leaving it.
 *
 * A reader holding the packet wants a glance at the certificate or a closer
 * look at the article, not a file in the downloads folder. The download
 * stays one tap away for the times the file itself is wanted.
 */
export default function PublicPreviewDialog({ preview, onClose, t }: Props) {
    return (
        <Dialog
            open={preview !== null}
            onOpenChange={(open) => (open ? undefined : onClose())}
        >
            {preview !== null ? (
                <DialogContent
                    className="flex max-h-[92dvh] flex-col gap-0 overflow-hidden border-0 bg-white p-0 text-gray-900 sm:max-w-3xl"
                    data-test="public-preview-dialog"
                >
                    <div className="flex items-center gap-3 border-b border-gray-100 p-4 pr-12">
                        <div className="flex size-9 shrink-0 items-center justify-center rounded-xl border border-sky-100 bg-sky-50">
                            {preview.kind === 'pdf' ? (
                                <FileText className="size-[18px] text-sky-700" />
                            ) : (
                                <ImageIcon className="size-[18px] text-sky-700" />
                            )}
                        </div>
                        <DialogTitle className="min-w-0 flex-1 truncate text-sm font-semibold">
                            {preview.name}
                        </DialogTitle>
                        <DialogDescription className="sr-only">
                            {t('previewOf', { name: preview.name })}
                        </DialogDescription>
                    </div>

                    {/*
                     * A fixed share of the window rather than the file's own
                     * shape: a tall certificate and a wide photo both have to
                     * fit without pushing the buttons off a phone's screen.
                     */}
                    <div className="flex h-[70dvh] items-center justify-center bg-gray-50">
                        {preview.kind === 'image' ? (
                            <img
                                src={preview.url}
                                alt={preview.name}
                                data-test="public-preview-image"
                                className="max-h-full max-w-full object-contain p-4"
                            />
                        ) : (
                            /*
                             * An object rather than a frame: a browser with no
                             * PDF viewer -- a phone, often -- shows the
                             * fallback inside it instead of an empty box.
                             */
                            <object
                                data={preview.url}
                                type="application/pdf"
                                aria-label={t('previewOf', {
                                    name: preview.name,
                                })}
                                data-test="public-preview-pdf"
                                className="size-full"
                            >
                                <div className="flex h-full flex-col items-center justify-center gap-3 p-6 text-center">
                                    <p className="text-sm text-gray-500">
                                        {t('pdfNotShown')}
                                    </p>
                                    <a
                                        href={preview.url}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="rounded-lg border border-sky-200 bg-white px-3 py-1.5 text-xs font-medium text-sky-700 shadow-sm hover:bg-sky-50"
                                    >
                                        {t('openInNewTab')}
                                    </a>
                                </div>
                            </object>
                        )}
                    </div>

                    <div className="flex justify-end gap-2 border-t border-gray-100 p-3">
                        <button
                            type="button"
                            onClick={onClose}
                            className="rounded-lg px-3 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-100"
                        >
                            {t('close')}
                        </button>
                        {preview.downloadUrl ? (
                            /*
                             * A plain anchor: the response is a file, and an
                             * XHR visit would choke on it.
                             */
                            <a
                                href={preview.downloadUrl}
                                data-test="public-preview-download"
                                className="inline-flex items-center gap-1.5 rounded-lg bg-sky-700 px-3 py-1.5 text-xs font-medium text-white shadow-sm hover:bg-sky-800"
                            >
                                <Download className="size-3.5" />
                                {t('download')}
                            </a>
                        ) : null}
                    </div>
                </DialogContent>
            ) : null}
        </Dialog>
    );
}
