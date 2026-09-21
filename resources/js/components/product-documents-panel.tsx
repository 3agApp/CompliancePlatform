import { router, useHttp } from '@inertiajs/react';
import { FileText, Sparkles, Trash2, Upload, X } from 'lucide-react';
import { useRef, useState } from 'react';
import DeleteProductDocumentModal from '@/components/delete-product-document-modal';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';
import { show, store, suggest } from '@/routes/products/documents';
import type {
    ProductDocument,
    ProductDocumentType,
    ProductDocumentTypeOption,
} from '@/types';

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
    /** The AI had no answer, or was not sure of the one it gave. */
    unsure: boolean;
};

type Props = {
    organizationSlug: string;
    productId: number;
    documents: ProductDocument[];
    availableDocumentTypes: ProductDocumentTypeOption[];
    /**
     * The kinds the product's template asks for and has not been given,
     * offered as one click each: the checklist in the rail says what is
     * missing, and this is where it is answered.
     */
    outstandingTypes?: ProductDocumentTypeOption[];
    canUpload: boolean;
};

/**
 * What to say when the guessing did not happen.
 */
const UNAVAILABLE_NOTES: Record<string, string> = {
    not_configured:
        'Kinds are not guessed for this organization — an owner or admin can connect an AI provider in organization settings.',
    rejected:
        'The AI provider refused this organization’s key. Pick the kinds yourself below.',
    unavailable:
        'Couldn’t reach the AI provider just now. Pick the kinds yourself below.',
};

/**
 * When a paper was filed, to the day.
 *
 * Evidence is read against dates -- a certificate issued before a standard
 * changed is not the same certificate -- so the day it arrived belongs on
 * the row. The time it arrived does not.
 */
function filedOn(timestamp: string | null): string {
    if (timestamp === null) {
        return '';
    }

    return new Date(timestamp).toLocaleDateString(undefined, {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
}

/**
 * Show a file size the way the person who picked the file thinks of it.
 */
function humanSize(bytes: number): string {
    if (bytes < 1024) {
        return `${bytes} B`;
    }

    const kilobytes = bytes / 1024;

    return kilobytes < 1024
        ? `${Math.round(kilobytes)} KB`
        : `${(kilobytes / 1024).toFixed(1)} MB`;
}

/**
 * The papers filed against a product, gathered under their kinds.
 *
 * A folder from a test house arrives as a folder, so the whole of it goes in
 * at once and the AI proposes a kind for each name. Every proposal is a
 * proposal: the table is a list of things to confirm or correct, and nothing
 * is filed until it is.
 *
 * A product collects several of the same kind — a test report per component,
 * a certificate per standard — so the list groups rather than replaces, and
 * uploading never disturbs what is already there.
 */
export default function ProductDocumentsPanel({
    organizationSlug,
    productId,
    documents,
    availableDocumentTypes,
    outstandingTypes = [],
    canUpload,
}: Props) {
    const [pending, setPending] = useState<PendingFile[]>([]);
    const [dragging, setDragging] = useState(false);
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [notice, setNotice] = useState<string | null>(null);

    const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);
    const [documentToDelete, setDocumentToDelete] =
        useState<ProductDocument | null>(null);

    const fileInput = useRef<HTMLInputElement>(null);

    /**
     * The suggestion call carries names, types and sizes as JSON -- no file
     * ever goes with it -- so useHttp is right here: a plain request that
     * does not touch the page lifecycle, and that can be cancelled when
     * somebody would rather not wait.
     */
    const suggestions = useHttp<
        { files: CandidateMetadata[] },
        SuggestionResponse
    >({ files: [] as CandidateMetadata[] });

    /**
     * Ask what the newly added files look like.
     *
     * The guesses are applied by matching the rows this call was made about,
     * so files added while it was in flight are left alone rather than
     * taking somebody else's answer.
     */
    const askForKinds = (added: PendingFile[]) => {
        setNotice(null);

        suggestions.setData(
            'files',
            added.map(({ file }) => ({
                name: file.name,
                mime_type: file.type || 'application/octet-stream',
                size: file.size,
            })),
        );

        suggestions.post(suggest.url([organizationSlug, productId]), {
            onSuccess: (response: SuggestionResponse) => {
                if (response.unavailable !== null) {
                    setNotice(
                        UNAVAILABLE_NOTES[response.unavailable] ??
                            UNAVAILABLE_NOTES.unavailable,
                    );
                }

                setPending((rows) =>
                    rows.map((row) => {
                        const position = added.findIndex(
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
             * the table is already there and the kinds can be picked by hand.
             */
            onError: () => setNotice(UNAVAILABLE_NOTES.unavailable),
        });
    };

    const takeFiles = (chosen: FileList | null) => {
        if (chosen === null || chosen.length === 0) {
            return;
        }

        const room = MAX_FILES - pending.length;

        if (room <= 0) {
            setNotice(`Upload up to ${MAX_FILES} files at a time.`);

            return;
        }

        const accepted = Array.from(chosen).slice(0, room);
        const tooBig = accepted.filter((file) => file.size > MAX_BYTES);
        const added = accepted
            .filter((file) => file.size <= MAX_BYTES)
            .map((file) => ({
                id: crypto.randomUUID(),
                file,
                guessed: false,
                unsure: false,
            }));

        setErrors({});

        if (added.length > 0) {
            setPending((rows) => [...rows, ...added]);
            askForKinds(added);
        }

        if (tooBig.length > 0) {
            setNotice(
                `${tooBig.map((file) => file.name).join(', ')} — each file must be no larger than 10 MB.`,
            );
        } else if (accepted.length < chosen.length) {
            setNotice(`Upload up to ${MAX_FILES} files at a time.`);
        }

        /**
         * The input is only a picker here; the rows are the truth. Clearing
         * it lets the same file be chosen again after it was removed.
         */
        if (fileInput.current !== null) {
            fileInput.current.value = '';
        }
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

    /**
     * Fill the first row still without a kind, so the chips in "Still
     * needed" stay one click each rather than becoming a legend.
     */
    const applyOutstanding = (type: ProductDocumentType) => {
        const next = pending.find((row) => row.type === undefined);

        if (next !== undefined) {
            setRowType(next.id, type);
        }
    };

    const ready =
        pending.length > 0 && pending.every((row) => row.type !== undefined);

    const upload = () => {
        if (! ready) {
            return;
        }

        router.post(
            store.url([organizationSlug, productId]),
            {
                documents: pending.map(({ file, type }) => ({ file, type })),
            },
            {
                forceFormData: true,
                /**
                 * The default bracket format would post documents[][file]
                 * and documents[][type], and PHP starts a new element at
                 * every [] -- so the files and the kinds would arrive as
                 * separate half-filled rows. Indices keep each pair together.
                 */
                queryStringArrayFormat: 'indices',
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onError: (received) =>
                    setErrors(received as Record<string, string>),
                onSuccess: () => {
                    setPending([]);
                    setErrors({});
                    setNotice(null);
                },
                onFinish: () => setProcessing(false),
            },
        );
    };

    const confirmDelete = (document: ProductDocument) => {
        setDocumentToDelete(document);
        setDeleteDialogOpen(true);
    };

    const groups = availableDocumentTypes
        .map((option) => ({
            ...option,
            documents: documents.filter(
                (document) => document.type === option.value,
            ),
        }))
        .filter((group) => group.documents.length > 0);

    return (
        <>
            <div className="workspace-panel space-y-6 p-6">
                <Heading
                    variant="small"
                    title="Documents"
                    description="Test reports, declarations of conformity, manuals, certificates and images. Drop the whole folder in — each file's kind is proposed for you to confirm."
                />

                {canUpload ? (
                    <div className="grid gap-4">
                        {/*
                         * The kinds the template is still waiting for, each
                         * one click away. Without them a person reads the
                         * checklist in the rail, remembers a name, and finds
                         * it again in a list of eight.
                         */}
                        {outstandingTypes.length > 0 ? (
                            <div className="flex flex-wrap items-center gap-2">
                                <span className="text-muted-foreground text-xs">
                                    Still needed
                                </span>
                                {outstandingTypes.map((option) => (
                                    <button
                                        key={option.value}
                                        type="button"
                                        data-test={`document-kind-${option.value}`}
                                        onClick={() =>
                                            applyOutstanding(option.value)
                                        }
                                        disabled={pending.length === 0}
                                        className={cn(
                                            'focus-visible:ring-ring rounded-full border px-2.5 py-1 text-xs font-medium transition-colors outline-none focus-visible:ring-2',
                                            'text-muted-foreground hover:text-foreground hover:border-foreground/30',
                                            'disabled:pointer-events-none disabled:opacity-60',
                                        )}
                                    >
                                        {option.label}
                                    </button>
                                ))}
                            </div>
                        ) : null}

                        {/*
                         * A label rather than a button, so the input it
                         * hides is what opens the picker and what the
                         * keyboard reaches.
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
                                onChange={(event) =>
                                    takeFiles(event.target.files)
                                }
                            />

                            <Upload className="text-muted-foreground size-5" />
                            <span className="text-sm font-medium">
                                Drag files here, or{' '}
                                <span className="underline underline-offset-4">
                                    browse
                                </span>
                            </span>
                            <span className="text-muted-foreground text-xs">
                                PDF, image, Word or Excel, up to 10 MB each.
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
                            <div
                                className="divide-y rounded-xl border"
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
                                        className="grid grid-cols-[minmax(0,1fr)_auto] gap-x-3 gap-y-2 px-4 py-3 sm:grid-cols-[minmax(0,1fr)_minmax(0,16rem)_auto] sm:items-center sm:gap-3"
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
                                                    {humanSize(row.file.size)}
                                                </p>
                                            </div>
                                        </div>

                                        <div className="col-span-2 row-start-2 grid gap-1 sm:col-span-1 sm:col-start-2 sm:row-start-1">
                                            {suggestions.processing &&
                                            row.type === undefined &&
                                            ! row.unsure ? (
                                                <span
                                                    className="text-muted-foreground flex h-9 items-center gap-2 text-xs"
                                                    data-test={`pending-document-guessing-${index}`}
                                                >
                                                    <Spinner className="size-3" />
                                                    Working out the kind…
                                                </span>
                                            ) : (
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
                                                        <SelectValue placeholder="Select a kind" />
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
                                                                    {
                                                                        option.label
                                                                    }
                                                                </SelectItem>
                                                            ),
                                                        )}
                                                    </SelectContent>
                                                </Select>
                                            )}

                                            {row.guessed ? (
                                                <span
                                                    className="text-muted-foreground flex items-center gap-1 text-xs"
                                                    data-test={`pending-document-guessed-${index}`}
                                                >
                                                    <Sparkles className="size-3" />
                                                    Proposed — check it
                                                </span>
                                            ) : null}

                                            {row.unsure &&
                                            row.type === undefined ? (
                                                <span
                                                    className="text-muted-foreground text-xs"
                                                    data-test={`pending-document-unsure-${index}`}
                                                >
                                                    Not sure — pick the kind
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
                                                Remove {row.file.name}
                                            </span>
                                        </Button>
                                    </div>
                                ))}
                            </div>
                        ) : null}

                        <div className="flex justify-start">
                            {/*
                             * Held shut until every row has a kind: the
                             * server refuses the batch either way, and a
                             * round trip to be told so is a round trip
                             * wasted.
                             */}
                            <Button
                                type="button"
                                variant="outline"
                                data-test="upload-document-submit"
                                onClick={upload}
                                disabled={processing || ! ready}
                            >
                                <Upload className="h-4 w-4" />
                                {processing
                                    ? 'Uploading…'
                                    : pending.length > 1
                                      ? `Upload ${pending.length} documents`
                                      : 'Upload'}
                            </Button>
                        </div>
                    </div>
                ) : null}

                {groups.length > 0 ? (
                    <div className="space-y-5">
                        {groups.map((group) => (
                            <div key={group.value} className="space-y-2">
                                <h3 className="text-muted-foreground text-xs font-medium tracking-[0.16em] uppercase">
                                    {group.label}
                                </h3>

                                <ul className="divide-y rounded-xl border">
                                    {group.documents.map((document) => (
                                        <li
                                            key={document.id}
                                            data-test="product-document-row"
                                            className="flex flex-wrap items-center gap-3 px-4 py-3"
                                        >
                                            <FileText className="text-muted-foreground h-4 w-4 shrink-0" />

                                            <div className="min-w-0 flex-1">
                                                {/*
                                                 * A plain anchor, not an
                                                 * Inertia link: the response
                                                 * is a file, and an XHR visit
                                                 * would choke on it.
                                                 */}
                                                <a
                                                    href={show.url([
                                                        organizationSlug,
                                                        productId,
                                                        document.id,
                                                    ])}
                                                    data-test="product-document-download"
                                                    className="text-sm font-medium break-all underline-offset-4 hover:underline"
                                                >
                                                    {document.name}
                                                </a>
                                                <p className="text-muted-foreground text-xs">
                                                    {[
                                                        humanSize(
                                                            document.size,
                                                        ),
                                                        document.uploaded_by,
                                                        filedOn(
                                                            document.created_at,
                                                        ),
                                                    ]
                                                        .filter(Boolean)
                                                        .join(' · ')}
                                                </p>
                                            </div>

                                            {canUpload ? (
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    data-test="product-document-delete-button"
                                                    onClick={() =>
                                                        confirmDelete(document)
                                                    }
                                                >
                                                    <Trash2 className="h-4 w-4" />
                                                    <span className="sr-only">
                                                        Delete {document.name}
                                                    </span>
                                                </Button>
                                            ) : null}
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        ))}
                    </div>
                ) : (
                    <p
                        className="text-muted-foreground text-sm"
                        data-test="product-documents-empty"
                    >
                        {canUpload
                            ? 'No documents yet — upload the first ones above.'
                            : 'No documents have been filed against this product yet.'}
                    </p>
                )}
            </div>

            <DeleteProductDocumentModal
                organizationSlug={organizationSlug}
                productId={productId}
                document={documentToDelete}
                open={deleteDialogOpen}
                onOpenChange={setDeleteDialogOpen}
            />
        </>
    );
}
