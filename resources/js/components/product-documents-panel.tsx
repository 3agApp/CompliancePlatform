import { Form } from '@inertiajs/react';
import { FileText, Trash2, Upload, X } from 'lucide-react';
import { useRef, useState } from 'react';
import DeleteProductDocumentModal from '@/components/delete-product-document-modal';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { show, store } from '@/routes/products/documents';
import type {
    ProductDocument,
    ProductDocumentType,
    ProductDocumentTypeOption,
} from '@/types';

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
 * A product collects several of the same kind — a test report per component,
 * a certificate per standard — so the list groups rather than replaces, and
 * uploading one never disturbs the ones already there.
 */
export default function ProductDocumentsPanel({
    organizationSlug,
    productId,
    documents,
    availableDocumentTypes,
    outstandingTypes = [],
    canUpload,
}: Props) {
    const [type, setType] = useState<ProductDocumentType | undefined>(
        undefined,
    );

    /**
     * Bumped after every upload so the file picker and the kind both come
     * back empty, the way the new product dialog resets itself.
     */
    const [uploadCount, setUploadCount] = useState(0);

    const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);
    const [documentToDelete, setDocumentToDelete] =
        useState<ProductDocument | null>(null);

    /**
     * The file the person has chosen, read back off the input rather than
     * held instead of it: the input is what the form posts, so it stays
     * the source of truth and this only mirrors it for the page to show.
     */
    const [file, setFile] = useState<File | null>(null);
    const [dragging, setDragging] = useState(false);
    const fileInput = useRef<HTMLInputElement>(null);

    const takeFile = (chosen: File | null) => setFile(chosen);

    /**
     * A dropped file has to be put into the input by hand -- dropping on a
     * label does not reach it -- or the form would post nothing at all.
     */
    const dropFile = (event: React.DragEvent) => {
        event.preventDefault();
        setDragging(false);

        const dropped = event.dataTransfer.files[0];
        const input = fileInput.current;

        if (!dropped || input === null) {
            return;
        }

        const transfer = new DataTransfer();
        transfer.items.add(dropped);
        input.files = transfer.files;

        takeFile(dropped);
    };

    const clearFile = () => {
        if (fileInput.current !== null) {
            fileInput.current.value = '';
        }

        takeFile(null);
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
                    description="Test reports, declarations of conformity, manuals, certificates and images. A product can carry several of the same kind."
                />

                {canUpload ? (
                    <Form
                        key={uploadCount}
                        {...store.form([organizationSlug, productId])}
                        options={{ preserveScroll: true, preserveState: true }}
                        onSuccess={() => {
                            setType(undefined);
                            setFile(null);
                            setUploadCount((count) => count + 1);
                        }}
                        className="grid gap-4"
                    >
                        {({ errors, processing }) => (
                            <>
                                {/*
                                 * The kinds the template is still waiting
                                 * for, each one click away. Without them a
                                 * person reads the checklist in the rail,
                                 * remembers a name, and finds it again in a
                                 * list of eight.
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
                                                    setType(option.value)
                                                }
                                                aria-pressed={
                                                    type === option.value
                                                }
                                                className={cn(
                                                    'focus-visible:ring-ring rounded-full border px-2.5 py-1 text-xs font-medium transition-colors outline-none focus-visible:ring-2',
                                                    type === option.value
                                                        ? 'border-foreground bg-foreground text-background'
                                                        : 'text-muted-foreground hover:text-foreground hover:border-foreground/30',
                                                )}
                                            >
                                                {option.label}
                                            </button>
                                        ))}
                                    </div>
                                ) : null}

                                {/*
                                 * A label rather than a button, so the
                                 * input it hides is what opens the picker
                                 * and what the keyboard reaches -- and the
                                 * same input is what the form posts,
                                 * dropped file and all.
                                 */}
                                <label
                                    htmlFor="document-file"
                                    data-test="document-dropzone"
                                    onDragOver={(event) => {
                                        event.preventDefault();
                                        setDragging(true);
                                    }}
                                    onDragLeave={() => setDragging(false)}
                                    onDrop={dropFile}
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
                                        name="file"
                                        type="file"
                                        data-test="document-file"
                                        accept=".pdf,.png,.jpg,.jpeg,.webp,.doc,.docx,.xls,.xlsx"
                                        className="sr-only"
                                        onChange={(event) =>
                                            takeFile(
                                                event.target.files?.[0] ?? null,
                                            )
                                        }
                                    />

                                    <Upload className="text-muted-foreground size-5" />
                                    <span className="text-sm font-medium">
                                        Drag a file here, or{' '}
                                        <span className="underline underline-offset-4">
                                            browse
                                        </span>
                                    </span>
                                    <span className="text-muted-foreground text-xs">
                                        PDF, image, Word or Excel, up to 10 MB.
                                    </span>
                                </label>

                                {file !== null ? (
                                    <div
                                        data-test="document-chosen-file"
                                        className="bg-muted/40 flex items-center gap-3 rounded-xl border px-4 py-2.5"
                                    >
                                        <FileText className="text-muted-foreground size-4 shrink-0" />
                                        <div className="min-w-0 flex-1">
                                            <p className="truncate text-sm font-medium">
                                                {file.name}
                                            </p>
                                            <p className="text-muted-foreground text-xs">
                                                {humanSize(file.size)}
                                            </p>
                                        </div>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            data-test="document-clear-file"
                                            onClick={clearFile}
                                        >
                                            <X className="size-4" />
                                            <span className="sr-only">
                                                Choose a different file
                                            </span>
                                        </Button>
                                    </div>
                                ) : null}

                                <InputError message={errors.file} />

                                <div className="grid gap-4 sm:grid-cols-[minmax(0,20rem)_auto] sm:items-end sm:justify-start">
                                    <div className="grid content-start gap-2">
                                        <Label htmlFor="document-type">
                                            Kind
                                        </Label>
                                        <Select
                                            value={type}
                                            onValueChange={(value) =>
                                                setType(
                                                    value as ProductDocumentType,
                                                )
                                            }
                                        >
                                            <SelectTrigger
                                                id="document-type"
                                                data-test="document-type"
                                                className="w-full"
                                            >
                                                <SelectValue placeholder="Select a kind" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {availableDocumentTypes.map(
                                                    (option) => (
                                                        <SelectItem
                                                            key={option.value}
                                                            value={option.value}
                                                        >
                                                            {option.label}
                                                        </SelectItem>
                                                    ),
                                                )}
                                            </SelectContent>
                                        </Select>
                                        <input
                                            type="hidden"
                                            name="type"
                                            value={type ?? ''}
                                        />
                                        <InputError message={errors.type} />
                                    </div>

                                    {/*
                                     * Held shut until there is something to
                                     * file and a name to file it under: the
                                     * server refuses either way, and a round
                                     * trip to be told so is a round trip
                                     * wasted.
                                     */}
                                    <Button
                                        type="submit"
                                        variant="outline"
                                        data-test="upload-document-submit"
                                        disabled={
                                            processing ||
                                            file === null ||
                                            type === undefined
                                        }
                                    >
                                        <Upload className="h-4 w-4" />
                                        {processing ? 'Uploading…' : 'Upload'}
                                    </Button>
                                </div>
                            </>
                        )}
                    </Form>
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
                            ? 'No documents yet — upload the first one above.'
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
