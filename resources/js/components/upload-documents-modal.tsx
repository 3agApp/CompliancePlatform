import { router, useHttp } from '@inertiajs/react';
import { FileText, Sparkles, Upload, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { formatFileSize } from '@/lib/format';
import { t, tc, tn } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { store, suggest } from '@/routes/products/documents';
import type { ProductDocumentType, ProductDocumentTypeOption } from '@/types';

/**
 * The most files the endpoint accepts in one go, mirroring
 * SaveProductDocumentRequest::MAX_FILES. Held on the client as well so an
 * oversized batch is refused before it is built: past PHP's post_max_size
 * the body is discarded along with the CSRF token, and the person would be
 * told their session expired rather than anything about files.
 */
const MAX_FILES = 20;

/** Mirrors SaveProductDocumentRequest::MAX_KILOBYTES. */
const MAX_BYTES = 10240 * 1024;

type Confidence = 'high' | 'low';

type Suggestion = {
    type: ProductDocumentType | null;
    confidence: Confidence;
};

type SuggestionResponse = {
    guesses: Suggestion[];
    unavailable: 'not_configured' | 'rejected' | 'unavailable' | null;
};

/** What the suggestion call sends: metadata, never a file. */
type CandidateMetadata = {
    name: string;
    mime_type: string;
    size: number;
};

/**
 * A file chosen but not yet filed.
 *
 * The File itself lives here rather than in a native input, because one
 * input cannot hold a kind per file. Nothing leaves the browser until the
 * kinds are settled and Upload is pressed.
 */
type PendingFile = {
    id: string;
    file: File;
    type?: ProductDocumentType;
    /** The kind came from the AI and nobody has touched it since. */
    guessed: boolean;
    /** The AI was asked and had no answer, or was not sure of the one it gave. */
    unsure: boolean;
};

type Props = {
    organizationSlug: string;
    productId: number;
    availableDocumentTypes: ProductDocumentTypeOption[];
    /** Whether the organization has a provider to ask for kinds. */
    canGuessKinds: boolean;
    /**
     * The kind every file chosen in this sitting is filed as, when the
     * dialog was opened from the slot of one kind the template is waiting
     * for. Each row can still be changed.
     */
    presetType?: ProductDocumentType | null;
    /** Files dropped on the page before the dialog opened, taken at once. */
    initialFiles?: File[];
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

/**
 * What to say when the guessing did not happen. A function rather than a
 * constant, so the strings are looked up after the translations load.
 */
function unavailableNotes(): Record<string, string> {
    return {
        not_configured: t(
            'No AI provider is connected for this organization, so the kinds are yours to pick.',
        ),
        rejected: t(
            'The AI provider refused this organization’s key. Pick the kinds yourself.',
        ),
        unavailable: t(
            'Couldn’t reach the AI provider just now. Pick the kinds yourself.',
        ),
    };
}

/**
 * File a folder of papers in one go.
 *
 * Nothing is guessed until it is asked for. A folder from a test house
 * arrives as a folder, so the whole of it goes in at once with no kind on
 * any row; asking the AI to name them is a button, because it spends the
 * organization's own credit and because a proposal nobody asked for is a
 * proposal nobody reads.
 *
 * Every kind is confirmed by a person either way. The upload is held shut
 * until each row has one.
 */
export default function UploadDocumentsModal({
    organizationSlug,
    productId,
    availableDocumentTypes,
    canGuessKinds,
    presetType = null,
    initialFiles = [],
    open,
    onOpenChange,
}: Props) {
    const [pending, setPending] = useState<PendingFile[]>([]);
    const [dragging, setDragging] = useState(false);
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [notice, setNotice] = useState<string | null>(null);
    const [asked, setAsked] = useState(false);

    const fileInput = useRef<HTMLInputElement>(null);

    /**
     * The dialog is mounted by the page, so a closed one keeps whatever was
     * in it. Clearing on close means opening it again is a fresh start
     * rather than somebody else's abandoned batch.
     */
    useEffect(() => {
        if (open) {
            return;
        }

        setPending([]);
        setErrors({});
        setNotice(null);
        setAsked(false);
        setDragging(false);
    }, [open]);

    /**
     * The suggestion call carries names, types and sizes as JSON -- no file
     * ever goes with it -- so useHttp is right here: a plain request that
     * does not touch the page lifecycle.
     */
    const suggestions = useHttp<
        { files: CandidateMetadata[] },
        SuggestionResponse
    >({ files: [] as CandidateMetadata[] });

    const takeFiles = (chosen: FileList | File[] | null) => {
        if (chosen === null || chosen.length === 0) {
            return;
        }

        const room = MAX_FILES - pending.length;

        if (room <= 0) {
            setNotice(
                t('Upload up to :count files at a time.', { count: MAX_FILES }),
            );

            return;
        }

        const accepted = Array.from(chosen).slice(0, room);
        const tooBig = accepted.filter((file) => file.size > MAX_BYTES);
        const added = accepted
            .filter((file) => file.size <= MAX_BYTES)
            .map((file) => ({
                id: crypto.randomUUID(),
                file,
                type: presetType ?? undefined,
                guessed: false,
                unsure: false,
            }));

        setErrors({});

        if (added.length > 0) {
            setPending((rows) => [...rows, ...added]);
        }

        if (tooBig.length > 0) {
            setNotice(
                t(':files — each file must be no larger than 10 MB.', {
                    files: tooBig.map((file) => file.name).join(', '),
                }),
            );
        } else if (accepted.length < chosen.length) {
            setNotice(
                t('Upload up to :count files at a time.', { count: MAX_FILES }),
            );
        } else {
            setNotice(null);
        }

        /**
         * The input is only a picker here; the rows are the truth. Clearing
         * it lets the same file be chosen again after it was removed.
         */
        if (fileInput.current !== null) {
            fileInput.current.value = '';
        }
    };

    /**
     * Files dropped on a slot before the dialog opened are the first rows,
     * so the person lands on a batch ready to file rather than a picker.
     */
    useEffect(() => {
        if (open && initialFiles.length > 0) {
            takeFiles(initialFiles);
        }
        // Only on opening: the files are the ones dropped to open it.
    }, [open]);

    /**
     * Ask what the files look like.
     *
     * Only the rows still without a kind are asked about, so a second press
     * after correcting one by hand does not overwrite the correction.
     */
    const guessKinds = () => {
        const asking = pending.filter((row) => row.type === undefined);

        if (asking.length === 0) {
            return;
        }

        setNotice(null);
        setAsked(true);

        suggestions.setData(
            'files',
            asking.map(({ file }) => ({
                name: file.name,
                mime_type: file.type || 'application/octet-stream',
                size: file.size,
            })),
        );

        /*
         * The call is fired and its outcome handled in the callbacks below,
         * so the promise itself is deliberately not awaited -- the dialog
         * stays usable while it is in flight.
         */
        void suggestions.post(suggest.url([organizationSlug, productId]), {
            onSuccess: (response: SuggestionResponse) => {
                if (response.unavailable !== null) {
                    setNotice(
                        unavailableNotes()[response.unavailable] ??
                            unavailableNotes().unavailable,
                    );
                }

                setPending((rows) =>
                    rows.map((row) => {
                        const position = asking.findIndex(
                            (candidate) => candidate.id === row.id,
                        );
                        const guess = response.guesses[position];

                        if (position === -1 || guess === undefined) {
                            return row;
                        }

                        return {
                            ...row,
                            type: guess.type ?? undefined,
                            guessed: guess.type !== null,
                            unsure:
                                guess.type === null ||
                                guess.confidence === 'low',
                        };
                    }),
                );
            },
            /**
             * A request that never lands is not a reason to block an upload:
             * the rows are already there and the kinds can be picked by hand.
             */
            onError: () => setNotice(unavailableNotes().unavailable),
        });
    };

    const setRowType = (id: string, type: ProductDocumentType) => {
        setPending((rows) =>
            rows.map((row) =>
                row.id === id
                    ? { ...row, type, guessed: false, unsure: false }
                    : row,
            ),
        );
    };

    const removeRow = (id: string) => {
        setPending((rows) => rows.filter((row) => row.id !== id));
    };

    const ready =
        pending.length > 0 && pending.every((row) => row.type !== undefined);

    const upload = () => {
        if (!ready) {
            return;
        }

        router.post(
            store.url([organizationSlug, productId]),
            { documents: pending.map(({ file, type }) => ({ file, type })) },
            {
                forceFormData: true,
                /**
                 * The default bracket format would post documents[][file]
                 * and documents[][type], and PHP starts a new element at
                 * every [] -- so the files and the kinds would arrive as
                 * separate half-filled rows. Indices keep each pair
                 * together.
                 */
                queryStringArrayFormat: 'indices',
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onError: (received) =>
                    setErrors(received as Record<string, string>),
                onSuccess: () => onOpenChange(false),
                onFinish: () => setProcessing(false),
            },
        );
    };

    const unnamed = pending.filter((row) => row.type === undefined).length;

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent
                className="sm:max-w-2xl"
                data-test="upload-documents-modal"
            >
                <DialogHeader>
                    <DialogTitle>{t('Upload documents')}</DialogTitle>
                    <DialogDescription>
                        {t(
                            'Add as many files as you like, then say what each one is. Only file names, types and sizes are ever sent to the AI — never the contents of a document.',
                        )}
                    </DialogDescription>
                </DialogHeader>

                <div className="grid gap-4">
                    {/*
                     * A label rather than a button, so the input it hides is
                     * what opens the picker and what the keyboard reaches.
                     */}
                    <label
                        htmlFor="document-file"
                        data-test="document-dropzone"
                        onDragOver={(event) => {
                            event.preventDefault();
                            setDragging(true);
                        }}
                        onDragLeave={() => setDragging(false)}
                        onDrop={(event) => {
                            event.preventDefault();
                            setDragging(false);
                            takeFiles(event.dataTransfer.files);
                        }}
                        className={cn(
                            'grid cursor-pointer place-items-center gap-1 rounded-xl border border-dashed px-4 py-6 text-center transition-colors',
                            'has-[:focus-visible]:ring-ring has-[:focus-visible]:ring-2',
                            dragging
                                ? 'border-foreground bg-muted/60'
                                : 'hover:border-foreground/30 hover:bg-muted/30',
                        )}
                    >
                        <input
                            ref={fileInput}
                            id="document-file"
                            type="file"
                            multiple
                            data-test="document-file"
                            accept=".pdf,.png,.jpg,.jpeg,.webp,.doc,.docx,.xls,.xlsx"
                            className="sr-only"
                            onChange={(event) => takeFiles(event.target.files)}
                        />

                        <Upload className="text-muted-foreground size-5" />
                        <span className="text-sm font-medium">
                            {tn('Drag files here, or :browse', {
                                browse: (
                                    <span className="underline underline-offset-4">
                                        {t('browse')}
                                    </span>
                                ),
                            })}
                        </span>
                        <span className="text-muted-foreground text-xs">
                            {t('PDF, image, Word or Excel, up to 10 MB each.')}
                        </span>
                    </label>

                    {notice !== null ? (
                        <p
                            className="text-muted-foreground text-xs"
                            data-test="document-upload-notice"
                        >
                            {notice}
                        </p>
                    ) : null}

                    <InputError message={errors.documents} />

                    {pending.length > 0 ? (
                        <>
                            {canGuessKinds && unnamed > 0 ? (
                                <div className="flex flex-wrap items-center gap-3">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        data-test="guess-document-kinds-button"
                                        onClick={guessKinds}
                                        disabled={suggestions.processing}
                                    >
                                        {suggestions.processing ? (
                                            <Spinner className="size-4" />
                                        ) : (
                                            <Sparkles className="size-4" />
                                        )}
                                        {suggestions.processing
                                            ? t('Working out the kinds…')
                                            : asked
                                              ? t('Guess the rest')
                                              : t('Guess the kinds')}
                                    </Button>
                                    <span className="text-muted-foreground text-xs">
                                        {tc(
                                            '1 file still needs a kind|:count files still need a kind',
                                            unnamed,
                                        )}
                                    </span>
                                </div>
                            ) : null}

                            <div
                                className="max-h-80 divide-y overflow-y-auto rounded-xl border"
                                data-test="document-review-table"
                            >
                                {pending.map((row, index) => (
                                    /*
                                     * On a phone the name and the remove
                                     * button share the first line and the
                                     * kind takes the one below, so a row
                                     * reads as a card. The button is placed
                                     * rather than repeated: two copies would
                                     * mean two of the same test hook, and a
                                     * second thing for a screen reader to
                                     * read out.
                                     */
                                    <div
                                        key={row.id}
                                        data-test="pending-document-row"
                                        className="grid grid-cols-[minmax(0,1fr)_auto] gap-x-3 gap-y-2 px-4 py-3 sm:grid-cols-[minmax(0,1fr)_minmax(0,14rem)_auto] sm:items-center sm:gap-3"
                                    >
                                        <div className="col-start-1 row-start-1 flex min-w-0 items-start gap-3 sm:items-center">
                                            <FileText className="text-muted-foreground mt-0.5 size-4 shrink-0 sm:mt-0" />
                                            <div className="min-w-0">
                                                {/*
                                                 * Wrapped rather than
                                                 * truncated on a phone: a
                                                 * compliance filename carries
                                                 * the standard and the lab,
                                                 * and an ellipsis at 250px
                                                 * hides exactly the part that
                                                 * says which paper this is.
                                                 */}
                                                <p className="text-sm font-medium break-all sm:truncate">
                                                    {row.file.name}
                                                </p>
                                                <p className="text-muted-foreground text-xs">
                                                    {formatFileSize(
                                                        row.file.size,
                                                    )}
                                                </p>
                                            </div>
                                        </div>

                                        <div className="col-span-2 row-start-2 grid gap-1 sm:col-span-1 sm:col-start-2 sm:row-start-1">
                                            <Select
                                                value={row.type}
                                                onValueChange={(value) =>
                                                    setRowType(
                                                        row.id,
                                                        value as ProductDocumentType,
                                                    )
                                                }
                                            >
                                                <SelectTrigger
                                                    data-test={`pending-document-type-${index}`}
                                                    className="w-full"
                                                >
                                                    <SelectValue
                                                        placeholder={t(
                                                            'Select a kind',
                                                        )}
                                                    />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {availableDocumentTypes.map(
                                                        (option) => (
                                                            <SelectItem
                                                                key={
                                                                    option.value
                                                                }
                                                                value={
                                                                    option.value
                                                                }
                                                            >
                                                                {option.label}
                                                            </SelectItem>
                                                        ),
                                                    )}
                                                </SelectContent>
                                            </Select>

                                            {row.guessed ? (
                                                <span
                                                    className="text-muted-foreground flex items-center gap-1 text-xs"
                                                    data-test={`pending-document-guessed-${index}`}
                                                >
                                                    <Sparkles className="size-3" />
                                                    {t('Proposed — check it')}
                                                </span>
                                            ) : null}

                                            {row.unsure &&
                                            row.type === undefined ? (
                                                <span
                                                    className="text-muted-foreground text-xs"
                                                    data-test={`pending-document-unsure-${index}`}
                                                >
                                                    {t(
                                                        'Not sure — pick the kind',
                                                    )}
                                                </span>
                                            ) : null}

                                            <InputError
                                                message={
                                                    errors[
                                                        `documents.${index}.type`
                                                    ] ??
                                                    errors[
                                                        `documents.${index}.file`
                                                    ]
                                                }
                                            />
                                        </div>

                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            className="col-start-2 row-start-1 sm:col-start-3"
                                            data-test={`pending-document-remove-${index}`}
                                            onClick={() => removeRow(row.id)}
                                        >
                                            <X className="size-4" />
                                            <span className="sr-only">
                                                {t('Remove :name', {
                                                    name: row.file.name,
                                                })}
                                            </span>
                                        </Button>
                                    </div>
                                ))}
                            </div>
                        </>
                    ) : null}
                </div>

                <DialogFooter className="gap-2">
                    <Button
                        type="button"
                        variant="ghost"
                        data-test="upload-documents-cancel"
                        onClick={() => onOpenChange(false)}
                    >
                        {t('Cancel')}
                    </Button>

                    {/*
                     * Held shut until every row has a kind: the server
                     * refuses the batch either way, and a round trip to be
                     * told so is a round trip wasted.
                     */}
                    <Button
                        type="button"
                        data-test="upload-document-submit"
                        onClick={upload}
                        disabled={processing || !ready}
                    >
                        <Upload className="size-4" />
                        {processing
                            ? t('Uploading…')
                            : pending.length > 1
                              ? t('Upload :count documents', {
                                    count: pending.length,
                                })
                              : t('Upload')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
