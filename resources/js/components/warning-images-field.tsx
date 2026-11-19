import { router } from '@inertiajs/react';
import { Eye, EyeOff, ImagePlus, X } from 'lucide-react';
import { useState } from 'react';
import DeleteProductDocumentModal from '@/components/delete-product-document-modal';
import { FieldMarker } from '@/components/product-form-fields';
import UploadDocumentsModal from '@/components/upload-documents-modal';
import { Label } from '@/components/ui/label';
import { t } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { preview, visibility } from '@/routes/products/documents';
import type { ProductDocument, ProductDocumentTypeOption } from '@/types';

type Props = {
    organizationSlug: string;
    productId: number;
    /** The product's warning images: its `safety_image` documents. */
    images: ProductDocument[];
    availableDocumentTypes: ProductDocumentTypeOption[];
    canUpload: boolean;
    canPublish: boolean;
    canGuessKinds: boolean;
    /** Asked for by the template. */
    required?: boolean;
    /** Asked for by the template and still missing. */
    missing?: boolean;
};

/**
 * The warning symbols printed on the packaging, kept beside the warning
 * text they go with.
 *
 * Each one is filed as a document, but it is managed here rather than in
 * the documents tab: the image is part of the warning, and the person
 * filling in the warning should not have to go looking for it elsewhere.
 *
 * Adding, removing and releasing an image each happen at once, on their
 * own, so they never wait for the rest of the form to be saved -- and the
 * events they raise stop here, so the product form around them is not
 * marked as having unsaved changes.
 */
export default function WarningImagesField({
    organizationSlug,
    productId,
    images,
    availableDocumentTypes,
    canUpload,
    canPublish,
    canGuessKinds,
    required = false,
    missing = false,
}: Props) {
    const [uploadOpen, setUploadOpen] = useState(false);
    const [deleteOpen, setDeleteOpen] = useState(false);
    const [toDelete, setToDelete] = useState<ProductDocument | null>(null);

    const setVisibility = (image: ProductDocument) =>
        router.patch(
            visibility.url([organizationSlug, productId, image.id]),
            { is_public: !image.is_public },
            { preserveScroll: true, preserveState: true },
        );

    if (!canUpload && images.length === 0) {
        return null;
    }

    return (
        <div
            className="grid gap-2"
            data-test="warning-images"
            onInput={(event) => event.stopPropagation()}
            onChange={(event) => event.stopPropagation()}
        >
            <Label>
                {t('Warning images')}{' '}
                <FieldMarker required={required} missing={missing} />
            </Label>

            <ul className="flex flex-wrap gap-3">
                {images.map((image) => (
                    <li
                        key={image.id}
                        className="group relative"
                        data-test="warning-image"
                    >
                        <div
                            className={cn(
                                'bg-background flex size-20 items-center justify-center rounded-lg border p-1.5',
                                !image.is_public && 'border-dashed',
                            )}
                        >
                            <img
                                src={preview.url([
                                    organizationSlug,
                                    productId,
                                    image.id,
                                ])}
                                alt={image.name}
                                title={image.name}
                                className={cn(
                                    'max-h-full max-w-full object-contain',
                                    !image.is_public && 'opacity-60',
                                )}
                            />
                        </div>

                        {canUpload ? (
                            <button
                                type="button"
                                onClick={() => {
                                    setToDelete(image);
                                    setDeleteOpen(true);
                                }}
                                className="bg-background text-muted-foreground hover:text-destructive absolute -top-2 -right-2 rounded-full border p-0.5 shadow-sm"
                                aria-label={t('Delete :name', {
                                    name: image.name,
                                })}
                                data-test="warning-image-delete"
                            >
                                <X className="size-3.5" />
                            </button>
                        ) : null}

                        {canPublish ? (
                            <button
                                type="button"
                                role="switch"
                                aria-checked={image.is_public}
                                onClick={() => setVisibility(image)}
                                className={cn(
                                    'mt-1 flex w-20 items-center justify-center gap-1 text-[11px]',
                                    image.is_public
                                        ? 'font-medium text-emerald-700 dark:text-emerald-400'
                                        : 'text-muted-foreground',
                                )}
                                aria-label={
                                    image.is_public
                                        ? t('Hide :name from the public page', {
                                              name: image.name,
                                          })
                                        : t('Show :name on the public page', {
                                              name: image.name,
                                          })
                                }
                                data-test="warning-image-visibility"
                            >
                                {image.is_public ? (
                                    <Eye className="size-3" />
                                ) : (
                                    <EyeOff className="size-3" />
                                )}
                                {image.is_public ? t('Public') : t('Hidden')}
                            </button>
                        ) : (
                            <p className="text-muted-foreground mt-1 w-20 text-center text-[11px]">
                                {image.is_public ? t('Public') : t('Hidden')}
                            </p>
                        )}
                    </li>
                ))}

                {canUpload ? (
                    <li>
                        <button
                            type="button"
                            onClick={() => setUploadOpen(true)}
                            className={cn(
                                'text-muted-foreground hover:text-foreground hover:border-foreground/40 flex size-20 flex-col items-center justify-center gap-1 rounded-lg border border-dashed text-[11px] transition-colors',
                                missing && 'border-amber-500/60',
                            )}
                            data-test="warning-image-add"
                        >
                            <ImagePlus className="size-5" />
                            {t('Add')}
                        </button>
                    </li>
                ) : null}
            </ul>

            {canUpload ? (
                <UploadDocumentsModal
                    organizationSlug={organizationSlug}
                    productId={productId}
                    availableDocumentTypes={availableDocumentTypes}
                    canGuessKinds={canGuessKinds}
                    presetType="safety_image"
                    open={uploadOpen}
                    onOpenChange={setUploadOpen}
                />
            ) : null}

            <DeleteProductDocumentModal
                organizationSlug={organizationSlug}
                productId={productId}
                document={toDelete}
                open={deleteOpen}
                onOpenChange={setDeleteOpen}
            />
        </div>
    );
}
