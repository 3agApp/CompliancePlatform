import { Loader2 } from 'lucide-react';
import { useEffect, useState } from 'react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type { PublicTranslate } from '@/lib/public-i18n';
import type { PublicProductDocument } from '@/types';

type Props = {
    document: PublicProductDocument | null;
    onClose: () => void;
    t: PublicTranslate;
};

type Pages =
    | { state: 'loading' }
    | { state: 'failed' }
    | { state: 'ready'; pages: string[] };

/**
 * Read a released document page by page, without being handed the file.
 *
 * The server sends the addresses of the pages, each a picture with the
 * importer's name stamped across it, and they load as the reader scrolls.
 * Saving one by hand is still possible -- anything a browser shows can be
 * kept -- but what is kept is a marked picture, not a clean PDF.
 */
export default function DocumentViewerDialog({ document, onClose, t }: Props) {
    const [pages, setPages] = useState<Pages>({ state: 'loading' });

    useEffect(() => {
        if (document === null) {
            return;
        }

        const controller = new AbortController();
        setPages({ state: 'loading' });

        fetch(document.url, {
            headers: { Accept: 'application/json' },
            signal: controller.signal,
        })
            .then((response) =>
                response.ok
                    ? (response.json() as Promise<{ pages: string[] }>)
                    : Promise.reject(new Error(String(response.status))),
            )
            .then((body) => setPages({ state: 'ready', pages: body.pages }))
            .catch((error: unknown) => {
                if (!controller.signal.aborted) {
                    console.error(error);
                    setPages({ state: 'failed' });
                }
            });

        return () => controller.abort();
    }, [document]);

    return (
        <Dialog
            open={document !== null}
            onOpenChange={(open) => (open ? null : onClose())}
        >
            <DialogContent
                className="flex h-[92dvh] max-w-[calc(100%-1rem)] flex-col gap-3 bg-white p-3 text-gray-900 sm:max-w-3xl sm:p-5"
                data-test="document-viewer"
                onContextMenu={(event) => event.preventDefault()}
            >
                <DialogHeader className="pr-8 text-left">
                    <DialogTitle className="truncate text-base">
                        {document?.name}
                    </DialogTitle>
                    <DialogDescription className="text-xs text-gray-500">
                        {t('viewerNotice')}
                    </DialogDescription>
                </DialogHeader>

                <div className="-mx-3 flex-1 overflow-y-auto bg-gray-100 px-3 py-3 sm:-mx-5 sm:px-5">
                    {pages.state === 'loading' ? (
                        <p className="flex items-center justify-center gap-2 py-16 text-sm text-gray-500">
                            <Loader2 className="size-4 animate-spin" />
                            {t('viewerLoading')}
                        </p>
                    ) : null}

                    {pages.state === 'failed' ? (
                        <p
                            className="py-16 text-center text-sm text-gray-500"
                            data-test="document-viewer-failed"
                        >
                            {t('viewerFailed')}
                        </p>
                    ) : null}

                    {pages.state === 'ready' ? (
                        <ol className="mx-auto flex max-w-2xl flex-col gap-3">
                            {pages.pages.map((url, index) => (
                                <li key={url} data-test="document-viewer-page">
                                    <img
                                        src={url}
                                        alt={t('viewerPage', {
                                            page: String(index + 1),
                                            count: String(pages.pages.length),
                                        })}
                                        loading="lazy"
                                        draggable={false}
                                        className="aspect-[1/1.414] w-full rounded bg-white object-contain shadow-sm select-none"
                                    />
                                    <p className="mt-1 text-center text-[11px] text-gray-400">
                                        {t('viewerPage', {
                                            page: String(index + 1),
                                            count: String(pages.pages.length),
                                        })}
                                    </p>
                                </li>
                            ))}
                        </ol>
                    ) : null}
                </div>
            </DialogContent>
        </Dialog>
    );
}
