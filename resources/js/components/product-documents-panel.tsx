import { FileText, Trash2, Upload } from 'lucide-react';
import { useState } from 'react';
import DeleteProductDocumentModal from '@/components/delete-product-document-modal';
import Heading from '@/components/heading';
import UploadDocumentsModal from '@/components/upload-documents-modal';
import { Button } from '@/components/ui/button';
import { show } from '@/routes/products/documents';
import type { ProductDocument, ProductDocumentTypeOption } from '@/types';

type Props = {
    organizationSlug: string;
    productId: number;
    documents: ProductDocument[];
    availableDocumentTypes: ProductDocumentTypeOption[];
    /**
     * The kinds the product's template asks for and has not been given. The
     * checklist in the rail says what is missing; this says it again where
     * it is answered, and offers the dialog that answers it.
     */
    outstandingTypes?: ProductDocumentTypeOption[];
    canUpload: boolean;
    /** Whether the organization has an AI provider to ask for kinds. */
    canGuessKinds?: boolean;
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
 * Filing happens in a dialog rather than on the page. A folder from a test
 * house is a dozen files that each need a name, and that is a job with its
 * own beginning and end -- not a form sitting open above the list of what is
 * already filed.
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
    canGuessKinds = false,
}: Props) {
    const [uploadOpen, setUploadOpen] = useState(false);
    const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);
    const [documentToDelete, setDocumentToDelete] =
        useState<ProductDocument | null>(null);

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
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <Heading
                        variant="small"
                        title="Documents"
                        description="Test reports, declarations of conformity, manuals, certificates and images. A product can carry several of the same kind."
                    />

                    {canUpload ? (
                        <Button
                            type="button"
                            data-test="upload-documents-button"
                            onClick={() => setUploadOpen(true)}
                        >
                            <Upload className="size-4" />
                            Upload
                        </Button>
                    ) : null}
                </div>

                {/*
                 * The kinds the template is still waiting for, each one a way
                 * into the dialog. Without them a person reads the checklist
                 * in the rail, remembers a name, and finds it again in a list
                 * of eight.
                 */}
                {canUpload && outstandingTypes.length > 0 ? (
                    <div className="flex flex-wrap items-center gap-2">
                        <span className="text-muted-foreground text-xs">
                            Still needed
                        </span>
                        {outstandingTypes.map((option) => (
                            <button
                                key={option.value}
                                type="button"
                                data-test={`document-kind-${option.value}`}
                                onClick={() => setUploadOpen(true)}
                                className="focus-visible:ring-ring text-muted-foreground hover:text-foreground hover:border-foreground/30 rounded-full border px-2.5 py-1 text-xs font-medium transition-colors outline-none focus-visible:ring-2"
                            >
                                {option.label}
                            </button>
                        ))}
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

            {canUpload ? (
                <UploadDocumentsModal
                    organizationSlug={organizationSlug}
                    productId={productId}
                    availableDocumentTypes={availableDocumentTypes}
                    canGuessKinds={canGuessKinds}
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
        </>
    );
}
