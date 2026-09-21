import { Form, Head, Link, usePage } from '@inertiajs/react';
import { ArrowLeft, Trash2 } from 'lucide-react';
import { useState } from 'react';
import DeleteProductModal from '@/components/delete-product-modal';
import Heading from '@/components/heading';
import ProductClassificationFields from '@/components/product-classification-fields';
import ProductComplianceFields from '@/components/product-compliance-fields';
import ProductDocumentsPanel from '@/components/product-documents-panel';
import ProductFormFields from '@/components/product-form-fields';
import ProductRequirementsPanel from '@/components/product-requirements-panel';
import { Button } from '@/components/ui/button';
import { SectionBadge, SectionNav } from '@/components/ui/section-nav';
import { useUnsavedChanges } from '@/hooks/use-unsaved-changes';
import { index, update } from '@/routes/products';
import type {
    BrandOption,
    CountryOption,
    OrganizationType,
    ProductCategoryOption,
    ProductCompleteness,
    ProductDetail,
    ProductDocumentTypeOption,
    ProductPermissions,
    ProductRequirementOption,
    ProductTemplateOption,
    SupplierConnectionOption,
} from '@/types';

type Props = {
    product: ProductDetail;
    permissions: ProductPermissions;
    availableCountries: CountryOption[];
    availableDocumentTypes: ProductDocumentTypeOption[];
    availableCategories: ProductCategoryOption[];
    availableTemplates: ProductTemplateOption[];
    availableBrands: BrandOption[];
    availableConnections: SupplierConnectionOption[];
    availableRequirements: ProductRequirementOption[];
    canCreateBrand: boolean;
    completeness: ProductCompleteness;
    viewerType: OrganizationType;
};

export default function ProductEdit({
    product,
    permissions,
    availableCountries,
    availableDocumentTypes,
    availableCategories,
    availableTemplates,
    availableBrands,
    availableConnections,
    canCreateBrand,
    completeness,
    viewerType,
}: Props) {
    const { currentOrganization } = usePage().props;
    const organizationSlug = currentOrganization?.slug ?? '';

    const [dirty, setDirty] = useState(false);
    const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);

    const [connectionId, setConnectionId] = useState<number | null>(
        product.supplier_connection_id,
    );
    const [categoryId, setCategoryId] = useState<number>(
        product.product_category_id,
    );
    const [templateId, setTemplateId] = useState<number | null>(
        product.product_template_id,
    );

    const template =
        availableTemplates.find((option) => option.id === templateId) ?? null;

    /**
     * The template only exists under a category, so moving the product to
     * another family leaves the old sheet dangling. Clearing it is what
     * makes the second select ask the question again rather than submit an
     * answer that no longer applies.
     */
    const chooseCategory = (nextCategoryId: number) => {
        setCategoryId(nextCategoryId);

        if (nextCategoryId !== product.product_category_id) {
            setTemplateId(null);

            return;
        }

        setTemplateId(product.product_template_id);
    };

    /**
     * The checklist comes from the server, so it describes the product as
     * saved. While an unsaved change to the template is pending, the marks
     * on the fields are already the new sheet's and the panel is still the
     * old one's -- so the panel says which sheet it is reading.
     */
    const savedTemplateLabel = product.template_label;

    const supplierLabel =
        availableConnections.find(
            (connection) => connection.id === connectionId,
        )?.label ??
        product.counterparty ??
        null;

    const outstandingDocuments = completeness.items.filter(
        (item) => item.group === 'document' && !item.satisfied,
    ).length;

    /**
     * Four sets of questions -- who supplies it, what it is, what it
     * claims, and the papers behind it -- stacked down one page in the
     * order a product is usually filled in. The links beside them move the
     * viewport; nothing is hidden, so everything typed anywhere on the
     * page is submitted together and find-in-page still finds it all.
     */
    const sections = [
        { id: 'product-classification', label: 'Classification' },
        { id: 'product-identification', label: 'Identification' },
        { id: 'product-compliance', label: 'Compliance' },
        {
            id: 'product-documents',
            label: 'Documents',
            badge:
                outstandingDocuments > 0 ? (
                    <SectionBadge tone="attention">
                        {outstandingDocuments}
                    </SectionBadge>
                ) : product.documents.length > 0 ? (
                    <SectionBadge>{product.documents.length}</SectionBadge>
                ) : undefined,
        },
    ];

    return (
        <>
            <Head title={product.name} />

            <div className="workspace-page">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div className="page-heading">
                        <Button
                            variant="ghost"
                            size="sm"
                            className="-ml-2"
                            asChild
                        >
                            <Link href={index(organizationSlug)}>
                                <ArrowLeft className="h-4 w-4" /> Products
                            </Link>
                        </Button>
                        <h1 className="page-title break-words">
                            {product.name}
                        </h1>
                        {product.counterparty ? (
                            <p className="text-muted-foreground text-sm">
                                {viewerType === 'supplier'
                                    ? 'Assigned by '
                                    : 'Supplied by '}
                                <span className="text-foreground font-medium">
                                    {product.counterparty}
                                </span>
                            </p>
                        ) : null}
                    </div>

                    {permissions.canDeleteProduct ? (
                        <Button
                            variant="outline"
                            data-test="product-delete-button"
                            onClick={() => setDeleteDialogOpen(true)}
                        >
                            <Trash2 className="h-4 w-4" /> Delete product
                        </Button>
                    ) : null}
                </div>

                <div className="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
                    <div className="grid min-w-0 gap-6">
                        <Form
                            {...update.form([organizationSlug, product.id])}
                            /**
                             * The component is kept across the save, so the
                             * place the person had scrolled to is still the
                             * place showing afterwards. Props are replaced
                             * either way, so the checklist and the score
                             * still come back fresh.
                             */
                            options={{
                                preserveScroll: true,
                                preserveState: true,
                            }}
                            className="space-y-6"
                            /**
                             * Typing raises input; the selects raise only
                             * change. Both are needed, or picking a supplier
                             * or a country and walking away would lose the
                             * edit without the guard ever asking.
                             */
                            onInput={() => setDirty(true)}
                            onChange={() => setDirty(true)}
                            onSuccess={() => setDirty(false)}
                        >
                            {({ errors, processing }) => (
                                <>
                                    <UnsavedChangesGuard
                                        dirty={dirty && !processing}
                                    />

                                    <section
                                        id="product-classification"
                                        tabIndex={-1}
                                        className="workspace-panel scroll-mt-6 space-y-6 p-6 outline-none"
                                    >
                                        <Heading
                                            variant="small"
                                            title="Classification"
                                            description="Who supplies the product, and which template it is held to."
                                        />

                                        <ProductClassificationFields
                                            errors={errors}
                                            availableCategories={
                                                availableCategories
                                            }
                                            availableTemplates={
                                                availableTemplates
                                            }
                                            availableConnections={
                                                availableConnections
                                            }
                                            viewerType={viewerType}
                                            categoryId={categoryId}
                                            onCategoryChange={chooseCategory}
                                            templateId={templateId}
                                            onTemplateChange={setTemplateId}
                                            connectionId={connectionId}
                                            onConnectionChange={setConnectionId}
                                            disabled={
                                                !permissions.canUpdateProduct
                                            }
                                            idPrefix="edit-product"
                                        />
                                    </section>

                                    <section
                                        id="product-identification"
                                        tabIndex={-1}
                                        className="workspace-panel scroll-mt-6 space-y-6 p-6 outline-none"
                                    >
                                        <Heading
                                            variant="small"
                                            title="Product details"
                                            description="What the product is, and the numbers each side of the trade knows it by."
                                        />

                                        <ProductFormFields
                                            errors={errors}
                                            availableCountries={
                                                availableCountries
                                            }
                                            availableBrands={availableBrands}
                                            supplierConnectionId={connectionId}
                                            supplierLabel={supplierLabel}
                                            organizationSlug={organizationSlug}
                                            canCreateBrand={canCreateBrand}
                                            requirements={
                                                template?.requirements ?? []
                                            }
                                            product={product}
                                            disabled={
                                                !permissions.canUpdateProduct
                                            }
                                            idPrefix="edit-product"
                                        />
                                    </section>

                                    <section
                                        id="product-compliance"
                                        tabIndex={-1}
                                        className="workspace-panel scroll-mt-6 space-y-6 p-6 outline-none"
                                    >
                                        <Heading
                                            variant="small"
                                            title="Compliance details"
                                            description="What the product claims about its own safety: the warnings it carries, who it is for, and how it may be used."
                                        />

                                        <ProductComplianceFields
                                            errors={errors}
                                            requirements={
                                                template?.requirements ?? []
                                            }
                                            product={product}
                                            disabled={
                                                !permissions.canUpdateProduct
                                            }
                                            idPrefix="edit-product"
                                        />
                                    </section>

                                    {/*
                                     * One button for the three sections
                                     * above, which are one form however far
                                     * down the page the person happens to be.
                                     */}
                                    {permissions.canUpdateProduct ? (
                                        <div className="flex items-center gap-3">
                                            <Button
                                                type="submit"
                                                data-test="update-product-submit"
                                                disabled={processing}
                                            >
                                                Save changes
                                            </Button>
                                        </div>
                                    ) : (
                                        <p className="text-muted-foreground text-sm">
                                            You do not have permission to edit
                                            this product.
                                        </p>
                                    )}
                                </>
                            )}
                        </Form>

                        {/*
                         * The documents panel posts its own multipart form,
                         * so it sits below the one above rather than inside
                         * it -- uploading a file never touches the fields.
                         */}
                        <section
                            id="product-documents"
                            tabIndex={-1}
                            className="scroll-mt-6 outline-none"
                        >
                            <ProductDocumentsPanel
                                organizationSlug={organizationSlug}
                                productId={product.id}
                                documents={product.documents}
                                availableDocumentTypes={availableDocumentTypes}
                                canUpload={permissions.canUpdateProduct}
                            />
                        </section>
                    </div>

                    <div className="grid gap-4 lg:sticky lg:top-6">
                        {/*
                         * Hidden on small screens, where the rail sits below
                         * the form: a link that scrolls backwards past
                         * everything it names is worse than a plain scroll.
                         */}
                        <SectionNav
                            sections={sections}
                            idPrefix="edit-product"
                            className="hidden lg:block"
                        />

                        <ProductRequirementsPanel
                            completeness={completeness}
                            templateLabel={savedTemplateLabel}
                        />
                    </div>
                </div>
            </div>

            <DeleteProductModal
                organizationSlug={organizationSlug}
                product={product}
                open={deleteDialogOpen}
                onOpenChange={setDeleteDialogOpen}
            />
        </>
    );
}

/**
 * Warn before leaving the form with edits that have not been saved.
 *
 * A component rather than a call in the page, because the dirty flag it
 * watches is only known inside the form's render prop.
 */
function UnsavedChangesGuard({ dirty }: { dirty: boolean }) {
    useUnsavedChanges(
        dirty,
        'This product has changes that have not been saved. Leave anyway?',
    );

    return null;
}
