import { router, usePoll } from '@inertiajs/react';
import { Download, FileText, ImageOff, Trash2, Upload } from 'lucide-react';
import { useEffect, useState } from 'react';
import DeleteProductDocumentModal from '@/components/delete-product-document-modal';
import {
    AnalyzeDocumentButton,
    DocumentAnalysisResult,
} from '@/components/document-analysis';
import Heading from '@/components/heading';
import PreviewDocumentModal from '@/components/preview-document-modal';
import { unavailableNotes } from '@/components/product-assessment-panel';
import UploadDocumentsModal from '@/components/upload-documents-modal';
import { Button } from '@/components/ui/button';
import { formatDay, formatFileSize } from '@/lib/format';
import { t } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { preview, show, visibility } from '@/routes/products/documents';
import { visibility as imagesVisibility } from '@/routes/products/images';
import type {
    ProductAssessmentUnavailableReason,
    ProductDocument,
    ProductDocumentAnalysis,
    ProductDocumentType,
    ProductDocumentTypeOption,
} from '@/types';

/** The kinds shown as pictures rather than as rows. */
const GALLERY_TYPES: ProductDocumentType[] = ['product_image', 'safety_image'];

type Props = {
    organizationSlug: string;
    productId: number;
    documents: ProductDocument[];
    availableDocumentTypes: ProductDocumentTypeOption[];
    /**
     * The kinds the product's template asks for and has not been given. The
     * status strip says what is missing; this says it again where it is
     * answered, and takes the file that answers it.
     */
    outstandingTypes?: ProductDocumentTypeOption[];
    canUpload: boolean;
    /** Whether the viewer may release documents to the public page. */
    canPublish?: boolean;
    /** Whether the organization has an AI provider to ask for kinds. */
    canGuessKinds?: boolean;
    /**
     * The latest AI reading of each paper, keyed by its id. Null for anyone
     * who does not rule on the product: they get no Analyze button.
     */
    analyses?: Record<number, ProductDocumentAnalysis> | null;
    analysisUnavailableReason?: ProductAssessmentUnavailableReason | null;
};

/**
 * How often to look again while a paper is being read, in milliseconds.
 */
const POLL_INTERVAL = 4000;

/**
 * The file's extension, for the small mark beside its name.
 */
function extension(name: string): string {
    const dot = name.lastIndexOf('.');

    return dot === -1 ? '' : name.slice(dot + 1, dot + 5).toUpperCase();
}

/**
 * The papers filed against a product.
 *
 * Filing happens in a dialog rather than on the page. A folder from a test
 * house is a dozen files that each need a name, and that is a job with its
 * own beginning and end -- not a form sitting open above the list of what is
 * already filed.
 *
 * The kinds the template is still waiting for come first, each a slot a
 * file can be dropped on. Papers follow, gathered under their kinds in a
 * compact table -- a product collects several of the same kind, so the
 * list groups rather than replaces. Pictures are shown as pictures: a
 * photo is recognised at a glance and not at all by its file name.
 */
export default function ProductDocumentsPanel({
    organizationSlug,
    productId,
    documents,
    availableDocumentTypes,
    outstandingTypes = [],
    canUpload,
    canPublish = false,
    canGuessKinds = false,
    analyses = null,
    analysisUnavailableReason = null,
}: Props) {
    const [uploadOpen, setUploadOpen] = useState(false);
    const [presetType, setPresetType] = useState<ProductDocumentType | null>(
        null,
    );
    const [droppedFiles, setDroppedFiles] = useState<File[]>([]);
    const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);
    const [documentToDelete, setDocumentToDelete] =
        useState<ProductDocument | null>(null);
    const [previewDialogOpen, setPreviewDialogOpen] = useState(false);
    const [documentToPreview, setDocumentToPreview] =
        useState<ProductDocument | null>(null);

    const openUpload = (
        type: ProductDocumentType | null = null,
        files: File[] = [],
    ) => {
        setPresetType(type);
        setDroppedFiles(files);
        setUploadOpen(true);
    };

    /**
     * Why the last Analyze press failed, with the reading that was latest
     * at the time: once a newer reading lands, the error is old news.
     */
    const [analysisErrors, setAnalysisErrors] = useState<
        Record<number, { message: string; analysisId: number | null }>
    >({});

    /**
     * Look again while any paper is being read, and stop once none is.
     */
    const { start, stop } = usePoll(
        POLL_INTERVAL,
        { only: ['documentAnalyses'] },
        { autoStart: false },
    );

    const analysisPending = Object.values(analyses ?? {}).some(
        (analysis) => analysis.is_pending,
    );

    useEffect(() => {
        if (analysisPending) {
            start();
        } else {
            stop();
        }

        return stop;
    }, [analysisPending, start, stop]);

    const actions: DocumentActions = {
        organizationSlug,
        productId,
        canUpload,
        canPublish,
        analyses,
        analysisUnavailableNote:
            analysisUnavailableReason === null
                ? null
                : unavailableNotes()[analysisUnavailableReason],
        analysisErrors,
        onAnalysisError: (document, message) =>
            setAnalysisErrors((current) => {
                const next = { ...current };

                if (message === null) {
                    delete next[document.id];
                } else {
                    next[document.id] = {
                        message,
                        analysisId: analyses?.[document.id]?.id ?? null,
                    };
                }

                return next;
            }),
        onPreview: (document) => {
            setDocumentToPreview(document);
            setPreviewDialogOpen(true);
        },
        onDelete: (document) => {
            setDocumentToDelete(document);
            setDeleteDialogOpen(true);
        },
    };

    /**
     * Photos of the product and its safety marks are recognised by sight,
     * so they get a gallery. Any other paper stays under its kind even when
     * it was filed as a picture -- a scanned certificate is still looked
     * for among the certificates.
     */
    const isPicture = (document: ProductDocument) =>
        document.preview_kind === 'image' &&
        GALLERY_TYPES.includes(document.type);

    const images = documents.filter(isPicture);

    /** Only product photos are switched together; a safety image is evidence. */
    const productImages = documents.filter(
        (document) => document.type === 'product_image',
    );
    const allImagesPublic = productImages.every(
        (document) => document.is_public,
    );

    const groups = availableDocumentTypes
        .map((option) => ({
            ...option,
            documents: documents.filter(
                (document) =>
                    document.type === option.value && !isPicture(document),
            ),
        }))
        .filter((group) => group.documents.length > 0);

    return (
        <>
            <div className="grid gap-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <Heading variant="small" title={t('Documents')} />

                    {canUpload ? (
                        <Button
                            type="button"
                            data-test="upload-documents-button"
                            onClick={() => openUpload()}
                        >
                            <Upload className="size-4" />
                            {t('Upload')}
                        </Button>
                    ) : null}
                </div>

                {canUpload && outstandingTypes.length > 0 ? (
                    <div className="grid gap-3 md:grid-cols-2">
                        {outstandingTypes.map((option) => (
                            <MissingKindSlot
                                key={option.value}
                                option={option}
                                onChoose={() => openUpload(option.value)}
                                onDrop={(files) =>
                                    openUpload(option.value, files)
                                }
                            />
                        ))}
                    </div>
                ) : null}

                {groups.length > 0 ? (
                    <section
                        aria-label={t('Documents')}
                        className="workspace-panel overflow-hidden"
                    >
                        <div className="text-muted-foreground hidden grid-cols-[minmax(0,1fr)_10rem_7.5rem_7rem_7.5rem] gap-3 border-b px-5 py-2.5 text-xs md:grid">
                            <span>{t('File')}</span>
                            <span>{t('Uploaded by')}</span>
                            <span>{t('Date')}</span>
                            <span>{t('Public page')}</span>
                            <span className="sr-only">{t('Actions')}</span>
                        </div>

                        {groups.map((group) => (
                            <div key={group.value}>
                                <h3 className="text-muted-foreground flex items-center gap-2 px-5 pt-4 pb-1.5 text-xs font-medium tracking-[0.12em] uppercase">
                                    {group.label}
                                    <span className="bg-muted-foreground/15 rounded-full px-1.5 tracking-normal tabular-nums">
                                        {group.documents.length}
                                    </span>
                                </h3>

                                <ul>
                                    {group.documents.map((document) => (
                                        <DocumentRow
                                            key={document.id}
                                            document={document}
                                            actions={actions}
                                        />
                                    ))}
                                </ul>
                            </div>
                        ))}

                        <div className="h-2" />
                    </section>
                ) : null}

                {images.length > 0 ? (
                    <section
                        className="workspace-panel grid gap-4 p-5"
                        data-test="product-document-images"
                    >
                        <div className="flex flex-wrap items-center justify-between gap-2">
                            <h3 className="flex items-center gap-2 text-sm font-semibold">
                                {t('Images')}
                                <span className="bg-muted-foreground/15 text-muted-foreground rounded-full px-1.5 text-xs font-normal tabular-nums">
                                    {images.length}
                                </span>
                            </h3>

                            {canPublish && productImages.length > 1 ? (
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    data-test="product-images-visibility-all"
                                    onClick={() =>
                                        router.patch(
                                            imagesVisibility.url([
                                                organizationSlug,
                                                productId,
                                            ]),
                                            { is_public: !allImagesPublic },
                                            { preserveScroll: true },
                                        )
                                    }
                                >
                                    {allImagesPublic
                                        ? t('Hide all from public page')
                                        : t('Show all on public page')}
                                </Button>
                            ) : null}
                        </div>

                        <ul className="grid grid-cols-[repeat(auto-fill,minmax(11rem,1fr))] gap-4">
                            {images.map((document) => (
                                <ImageCard
                                    key={document.id}
                                    document={document}
                                    actions={actions}
                                />
                            ))}
                        </ul>
                    </section>
                ) : null}

                {documents.length === 0 ? (
                    <p
                        className="text-muted-foreground text-sm"
                        data-test="product-documents-empty"
                    >
                        {t('No documents yet.')}
                    </p>
                ) : null}
            </div>

            {canUpload ? (
                <UploadDocumentsModal
                    organizationSlug={organizationSlug}
                    productId={productId}
                    availableDocumentTypes={availableDocumentTypes}
                    canGuessKinds={canGuessKinds}
                    presetType={presetType}
                    initialFiles={droppedFiles}
                    open={uploadOpen}
                    onOpenChange={setUploadOpen}
                />
            ) : null}

            <DeleteProductDocumentModal
                organizationSlug={organizationSlug}
                productId={productId}
                document={documentToDelete}
                open={deleteDialogOpen}
                onOpenChange={setDeleteDialogOpen}
            />

            <PreviewDocumentModal
                organizationSlug={organizationSlug}
                productId={productId}
                document={documentToPreview}
                open={previewDialogOpen}
                onOpenChange={setPreviewDialogOpen}
            />
        </>
    );
}

type DocumentActions = {
    organizationSlug: string;
    productId: number;
    canUpload: boolean;
    canPublish: boolean;
    analyses: Record<number, ProductDocumentAnalysis> | null;
    analysisUnavailableNote: string | null;
    analysisErrors: Record<
        number,
        { message: string; analysisId: number | null }
    >;
    onAnalysisError: (
        document: ProductDocument,
        message: string | null,
    ) => void;
    onPreview: (document: ProductDocument) => void;
    onDelete: (document: ProductDocument) => void;
};

/**
 * One kind the template is waiting for, as a place to put it.
 *
 * Dropping a file on it, or choosing one, opens the upload with that kind
 * already filled in -- the person has said what the file is by where they
 * put it.
 */
function MissingKindSlot({
    option,
    onChoose,
    onDrop,
}: {
    option: ProductDocumentTypeOption;
    onChoose: () => void;
    onDrop: (files: File[]) => void;
}) {
    const [dragging, setDragging] = useState(false);

    return (
        <div
            data-test={`document-slot-${option.value}`}
            onDragOver={(event) => {
                event.preventDefault();
                setDragging(true);
            }}
            onDragLeave={(event) => {
                /*
                 * Moving onto the label or the button inside the slot
                 * leaves the slot too; only leaving it altogether ends the
                 * drag over it.
                 */
                if (
                    event.relatedTarget instanceof Node &&
                    event.currentTarget.contains(event.relatedTarget)
                ) {
                    return;
                }

                setDragging(false);
            }}
            onDrop={(event) => {
                event.preventDefault();
                setDragging(false);

                const files = Array.from(event.dataTransfer.files);

                if (files.length > 0) {
                    onDrop(files);
                }
            }}
            className={cn(
                'flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-dashed border-amber-500/50 bg-amber-500/5 px-5 py-4 transition-colors',
                dragging && 'border-amber-500 bg-amber-500/10',
            )}
        >
            <div className="grid gap-0.5">
                <span className="text-xs font-medium tracking-wide text-amber-700 uppercase dark:text-amber-400">
                    {t('Still needed')}
                </span>
                <span className="font-medium">{option.label}</span>
                <span className="text-muted-foreground text-xs">
                    {t('Drop a file here or choose one.')}
                </span>
            </div>

            <Button
                type="button"
                variant="outline"
                size="sm"
                data-test={`document-kind-${option.value}`}
                onClick={onChoose}
            >
                {t('Choose file')}
            </Button>
        </div>
    );
}

/**
 * The name of a document, which opens it where it can be opened and
 * fetches it where it cannot: clicking what a document is called should
 * show the document, and for a Word manual the only way to show it is to
 * hand it over.
 */
function DocumentName({
    document,
    actions,
    className,
}: {
    document: ProductDocument;
    actions: DocumentActions;
    className?: string;
}) {
    if (document.preview_kind !== null) {
        return (
            <button
                type="button"
                data-test="product-document-preview"
                onClick={() => actions.onPreview(document)}
                className={cn(
                    'text-left text-sm font-medium underline-offset-4 hover:underline',
                    className,
                )}
            >
                {document.name}
            </button>
        );
    }

    /*
     * A plain anchor, not an Inertia link: the response is a file, and an
     * XHR visit would choke on it.
     */
    return (
        <a
            href={show.url([
                actions.organizationSlug,
                actions.productId,
                document.id,
            ])}
            data-test="product-document-download"
            className={cn(
                'text-sm font-medium underline-offset-4 hover:underline',
                className,
            )}
        >
            {document.name}
        </a>
    );
}

/**
 * Whether the document is on the public page, and the switch that changes
 * it for those who may.
 */
function PublicSwitch({
    document,
    actions,
}: {
    document: ProductDocument;
    actions: DocumentActions;
}) {
    const label = document.is_public ? t('Public') : t('Hidden');

    /*
     * The public page watermarks what it hands out, and only a PDF or a
     * picture can carry one. A file released before that rule can still
     * be taken back.
     */
    const publishable = document.preview_kind !== null || document.is_public;

    if (!actions.canPublish) {
        return document.is_public ? (
            <span
                className="text-xs font-medium text-emerald-700 dark:text-emerald-400"
                data-test="product-document-public-badge"
            >
                {label}
            </span>
        ) : (
            <span className="text-muted-foreground text-xs">{label}</span>
        );
    }

    return (
        <button
            type="button"
            role="switch"
            aria-checked={document.is_public}
            aria-label={
                document.is_public
                    ? t('Hide :name from the public page', {
                          name: document.name,
                      })
                    : t('Show :name on the public page', {
                          name: document.name,
                      })
            }
            data-test="product-document-visibility-button"
            disabled={!publishable}
            title={
                publishable
                    ? undefined
                    : t('Only PDFs and images can be shown on the public page.')
            }
            onClick={() =>
                router.patch(
                    visibility.url([
                        actions.organizationSlug,
                        actions.productId,
                        document.id,
                    ]),
                    { is_public: !document.is_public },
                    { preserveScroll: true },
                )
            }
            className="focus-visible:ring-ring inline-flex items-center gap-2 rounded-full text-xs outline-none focus-visible:ring-2 disabled:cursor-not-allowed disabled:opacity-50"
        >
            <span
                className={cn(
                    'relative h-[18px] w-[30px] shrink-0 rounded-full transition-colors',
                    document.is_public
                        ? 'bg-emerald-600 dark:bg-emerald-500'
                        : 'bg-muted-foreground/30',
                )}
            >
                <span
                    className={cn(
                        'bg-background absolute top-[2px] size-[14px] rounded-full shadow-sm transition-all',
                        document.is_public ? 'left-[14px]' : 'left-[2px]',
                    )}
                />
            </span>
            {document.is_public ? (
                <span
                    className="font-medium text-emerald-700 dark:text-emerald-400"
                    data-test="product-document-public-badge"
                >
                    {label}
                </span>
            ) : (
                <span className="text-muted-foreground">{label}</span>
            )}
        </button>
    );
}

/**
 * Download, kept on every document -- a reader who cannot file papers
 * still collects them -- and delete, for those who can.
 */
function DocumentButtons({
    document,
    actions,
}: {
    document: ProductDocument;
    actions: DocumentActions;
}) {
    return (
        <div className="flex items-center justify-end">
            {actions.analyses !== null && document.offers_analysis ? (
                <AnalyzeDocumentButton
                    organizationSlug={actions.organizationSlug}
                    productId={actions.productId}
                    document={document}
                    analysis={actions.analyses[document.id]}
                    unavailableNote={actions.analysisUnavailableNote}
                    onError={(message) =>
                        actions.onAnalysisError(document, message)
                    }
                />
            ) : null}

            <Button variant="ghost" size="icon" className="size-8" asChild>
                <a
                    href={show.url([
                        actions.organizationSlug,
                        actions.productId,
                        document.id,
                    ])}
                    data-test="product-document-download-button"
                    title={t('Download :name', { name: document.name })}
                >
                    <Download className="h-4 w-4" />
                    <span className="sr-only">
                        {t('Download :name', { name: document.name })}
                    </span>
                </a>
            </Button>

            {actions.canUpload ? (
                <Button
                    variant="ghost"
                    size="icon"
                    className="size-8"
                    data-test="product-document-delete-button"
                    title={t('Delete :name', { name: document.name })}
                    onClick={() => actions.onDelete(document)}
                >
                    <Trash2 className="h-4 w-4" />
                    <span className="sr-only">
                        {t('Delete :name', { name: document.name })}
                    </span>
                </Button>
            ) : null}
        </div>
    );
}

function DocumentRow({
    document,
    actions,
}: {
    document: ProductDocument;
    actions: DocumentActions;
}) {
    const mark = extension(document.name);

    return (
        <li
            data-test="product-document-row"
            className="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-x-3 gap-y-1.5 border-t px-5 py-2.5 first:border-t-0 md:grid-cols-[minmax(0,1fr)_10rem_7.5rem_7rem_7.5rem]"
        >
            <div className="flex min-w-0 items-center gap-3">
                <span className="bg-muted text-muted-foreground flex size-8 shrink-0 items-center justify-center rounded-md border text-[10px] font-semibold">
                    {mark || <FileText className="size-4" />}
                </span>
                <div className="flex min-w-0 flex-col">
                    <DocumentName
                        document={document}
                        actions={actions}
                        className="truncate"
                    />
                    <span className="text-muted-foreground text-xs md:hidden">
                        {[
                            formatFileSize(document.size),
                            document.uploaded_by,
                            formatDay(document.created_at),
                        ]
                            .filter(Boolean)
                            .join(' · ')}
                    </span>
                    <span className="text-muted-foreground hidden text-xs md:inline">
                        {formatFileSize(document.size)}
                    </span>
                </div>
            </div>

            <span className="text-muted-foreground hidden truncate text-sm md:inline">
                {document.uploaded_by}
            </span>
            <span className="text-muted-foreground hidden text-sm md:inline">
                {formatDay(document.created_at)}
            </span>

            <div className="col-start-1 row-start-2 md:col-start-auto md:row-start-auto">
                <PublicSwitch document={document} actions={actions} />
            </div>

            <div className="col-start-2 row-span-2 row-start-1 md:col-start-auto md:row-span-1 md:row-start-auto">
                <DocumentButtons document={document} actions={actions} />
            </div>

            <AnalysisLine document={document} actions={actions} />
        </li>
    );
}

/**
 * A picture, with the same switch and buttons as a row in the table.
 */
function ImageCard({
    document,
    actions,
}: {
    document: ProductDocument;
    actions: DocumentActions;
}) {
    /** A picture the server cannot hand back still gets a card, not a hole. */
    const [broken, setBroken] = useState(false);

    return (
        <li
            data-test="product-document-row"
            className="bg-card flex flex-col overflow-hidden rounded-xl border"
        >
            <button
                type="button"
                onClick={() => actions.onPreview(document)}
                className="bg-muted focus-visible:ring-ring block aspect-[4/3] w-full outline-none focus-visible:ring-2 focus-visible:ring-inset"
                tabIndex={-1}
                aria-hidden="true"
            >
                {broken ? (
                    <span className="text-muted-foreground flex size-full items-center justify-center">
                        <ImageOff className="size-6" />
                    </span>
                ) : (
                    <img
                        src={preview.url([
                            actions.organizationSlug,
                            actions.productId,
                            document.id,
                        ])}
                        alt=""
                        loading="lazy"
                        onError={() => setBroken(true)}
                        className="size-full object-cover"
                    />
                )}
            </button>

            <div className="grid gap-2 p-3">
                <div className="grid min-w-0">
                    <DocumentName
                        document={document}
                        actions={actions}
                        className="truncate"
                    />
                    <span className="text-muted-foreground truncate text-xs">
                        {[
                            document.type_label,
                            formatFileSize(document.size),
                        ].join(' · ')}
                    </span>
                </div>

                <div className="flex items-center justify-between gap-2">
                    <PublicSwitch document={document} actions={actions} />
                    <DocumentButtons document={document} actions={actions} />
                </div>

                <AnalysisLine document={document} actions={actions} />
            </div>
        </li>
    );
}

/**
 * The latest AI reading of a paper, under the paper, for those who may
 * ask for one. Spans the whole row so the details have room to open.
 */
function AnalysisLine({
    document,
    actions,
}: {
    document: ProductDocument;
    actions: DocumentActions;
}) {
    if (actions.analyses === null || !document.offers_analysis) {
        return null;
    }

    const analysis = actions.analyses[document.id];
    const stored = actions.analysisErrors[document.id];
    const error =
        stored !== undefined && stored.analysisId === (analysis?.id ?? null)
            ? stored.message
            : null;

    if (analysis === undefined && error === null) {
        return null;
    }

    return (
        <div className="col-span-full min-w-0">
            <DocumentAnalysisResult analysis={analysis} error={error} />
        </div>
    );
}
