import { Form } from '@inertiajs/react';
import { FileText, Trash2, Upload } from 'lucide-react';
import { useState } from 'react';
import DeleteProductDocumentModal from '@/components/delete-product-document-modal';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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
    canUpload: boolean;
};

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
            <div className="workspace-panel max-w-2xl space-y-6 p-6">
                <Heading
                    variant="small"
                    title="Documents"
                    description="Test reports, declarations of conformity, manuals, certificates and images. A product can carry several of the same kind."
                />

                {canUpload ? (
                    <Form
                        key={uploadCount}
                        {...store.form([organizationSlug, productId])}
                        options={{ preserveScroll: true }}
                        onSuccess={() => {
                            setType(undefined);
                            setUploadCount((count) => count + 1);
                        }}
                        className="grid gap-4 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto] sm:items-start"
                    >
                        {({ errors, processing }) => (
                            <>
                                <div className="grid content-start gap-2">
                                    <Label htmlFor="document-type">Kind</Label>
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

                                <div className="grid content-start gap-2">
                                    <Label htmlFor="document-file">File</Label>
                                    <Input
                                        id="document-file"
                                        name="file"
                                        type="file"
                                        data-test="document-file"
                                        accept=".pdf,.png,.jpg,.jpeg,.webp,.doc,.docx,.xls,.xlsx"
                                    />
                                    <p className="text-muted-foreground text-xs">
                                        PDF, image, Word or Excel, up to 10 MB.
                                    </p>
                                    <InputError message={errors.file} />
                                </div>

                                <Button
                                    type="submit"
                                    variant="outline"
                                    data-test="upload-document-submit"
                                    disabled={processing}
                                    className="sm:mt-6"
                                >
                                    <Upload className="h-4 w-4" /> Upload
                                </Button>
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
                                                    {humanSize(document.size)}
                                                    {document.uploaded_by
                                                        ? ` · ${document.uploaded_by}`
                                                        : ''}
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
