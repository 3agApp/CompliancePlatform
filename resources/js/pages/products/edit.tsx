import { Deferred, Form, Head, Link, usePage } from '@inertiajs/react';
import { ArrowLeft, Trash2 } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import DeleteProductModal from '@/components/delete-product-modal';
import Heading from '@/components/heading';
import ProductClassificationFields from '@/components/product-classification-fields';
import ProductComplianceFields from '@/components/product-compliance-fields';
import ProductDocumentsPanel from '@/components/product-documents-panel';
import ProductFormFields from '@/components/product-form-fields';
import ProductHistoryPanel, {
    ProductHistorySkeleton,
} from '@/components/product-history-panel';
import ProductPublicPanel from '@/components/product-public-panel';
import ProductRequirementsPanel from '@/components/product-requirements-panel';
import ProductReviewPanel from '@/components/product-review-panel';
import ProductReviewStatusBadge from '@/components/product-review-status-badge';
import { Button } from '@/components/ui/button';
import { SectionBadge, SectionNav } from '@/components/ui/section-nav';
import { useUnsavedChanges } from '@/hooks/use-unsaved-changes';
import { cn } from '@/lib/utils';
import { index, update } from '@/routes/products';
import type {
    BrandOption,
    CountryOption,
    OrganizationType,
    ProductCategoryOption,
    ProductCompleteness,
    ProductDetail,
    ProductDocumentTypeOption,
    ProductEvent,
    ProductPermissions,
    ProductRequirementKey,
    ProductRequirementOption,
    ProductSeal,
    ProductSealOption,
    ProductSealOverride,
    ProductTemplateOption,
    SupplierConnectionOption,
} from '@/types';

/**
 * The requirements answered in the compliance section. Everything else a
 * template can ask a person to type sits in the identification section,
 * and everything it asks them to upload is a document -- so this one list
 * is enough to sort the checklist into the page.
 */
const COMPLIANCE_REQUIREMENTS: ProductRequirementKey[] = [
    'requires_age_grading',
    'requires_safety_notice',
    'requires_warning_text',
    'requires_material_information',
    'requires_usage_restrictions',
    'requires_safety_instructions',
    'requires_additional_notes',
];

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
    canGuessDocumentKinds: boolean;
    completeness: ProductCompleteness;
    reviewNote: string | null;
    seal: ProductSeal;
    sealOverride: ProductSealOverride | null;
    availableSeals: ProductSealOption[];
    publicUrl: string;
    /**
     * Deferred: the one thing on this page that grows without bound, and
     * the only one nobody reads before everything above it.
     */
    history?: ProductEvent[];
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
    canGuessDocumentKinds,
    completeness,
    reviewNote,
    seal,
    sealOverride,
    availableSeals,
    publicUrl,
    viewerType,
}: Props) {
    const { currentOrganization } = usePage().props;
    const organizationSlug = currentOrganization?.slug ?? '';

    const [dirty, setDirty] = useState(false);
    const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);

    /**
     * Whether the form's own save button is on screen. While it is, it is
     * the only one the page needs.
     */
    const [saveRowOnScreen, setSaveRowOnScreen] = useState(true);

    const [connectionId, setConnectionId] = useState<number>(
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

    /**
     * How much of the template's homework is still owed, section by
     * section, so the links say where the work is rather than only where
     * the headings are. Counted from the saved product, like the checklist
     * itself -- a field filled in but not yet saved still reads as
     * outstanding, which is the truthful answer until it is submitted.
     */
    const outstanding = completeness.items.reduce<Record<string, number>>(
        (counts, item) => {
            if (item.satisfied) {
                return counts;
            }

            const section =
                item.group === 'document'
                    ? 'product-documents'
                    : COMPLIANCE_REQUIREMENTS.includes(item.requirement)
                      ? 'product-compliance'
                      : 'product-identification';

            return { ...counts, [section]: (counts[section] ?? 0) + 1 };
        },
        {},
    );

    /**
     * The kinds of paper the template asks for and has not been given, in
     * the order the checklist lists them, so the panel that files them can
     * offer them directly.
     */
    const outstandingDocumentTypes = completeness.items.flatMap((item) => {
        if (item.satisfied || item.document_type === null) {
            return [];
        }

        const type = item.document_type;

        return availableDocumentTypes.filter((option) => option.value === type);
    });

    /**
     * Four sets of questions -- who supplies it, what it is, what it
     * claims, and the papers behind it -- stacked down one page in the
     * order a product is usually filled in. The links beside them move the
     * viewport; nothing is hidden, so everything typed anywhere on the
     * page is submitted together and find-in-page still finds it all.
     */
    const sections = [
        { id: 'product-classification', label: 'Classification' },
        {
            id: 'product-identification',
            label: 'Identification',
            badge: (
                <OutstandingBadge
                    section="product-identification"
                    count={outstanding['product-identification']}
                />
            ),
        },
        {
            id: 'product-compliance',
            label: 'Compliance',
            badge: (
                <OutstandingBadge
                    section="product-compliance"
                    count={outstanding['product-compliance']}
                />
            ),
        },
        {
            id: 'product-documents',
            label: 'Documents',
            badge: outstanding['product-documents'] ? (
                <OutstandingBadge
                    section="product-documents"
                    count={outstanding['product-documents']}
                />
            ) : product.documents.length > 0 ? (
                <SectionBadge>{product.documents.length}</SectionBadge>
            ) : undefined,
        },
        { id: 'product-history', label: 'History' },
    ];

    return (
        <>
            <Head title={product.name} />

            {/*
             * Room under the page for the save bar to float over while it
             * is showing, so it never covers the last thing on it.
             */}
            <div className={cn('workspace-page', dirty && 'pb-24')}>
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
                        <ProductReviewStatusBadge
                            status={product.review_status}
                            label={product.review_status_label}
                        />
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

                                    {permissions.canUpdateProduct ? (
                                        <UnsavedChangesBar
                                            dirty={dirty && !saveRowOnScreen}
                                            processing={processing}
                                        />
                                    ) : null}

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
                                        <SaveRow
                                            processing={processing}
                                            onScreenChange={setSaveRowOnScreen}
                                        />
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
                                outstandingTypes={outstandingDocumentTypes}
                                canUpload={permissions.canUpdateProduct}
                                canGuessKinds={canGuessDocumentKinds}
                            />
                        </section>

                        <section
                            id="product-history"
                            tabIndex={-1}
                            className="scroll-mt-6 outline-none"
                        >
                            <Deferred
                                data="history"
                                fallback={<ProductHistorySkeleton />}
                            >
                                <History />
                            </Deferred>
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

                        <ProductReviewPanel
                            organizationSlug={organizationSlug}
                            product={product}
                            permissions={permissions}
                            reviewNote={reviewNote}
                        />

                        <ProductRequirementsPanel
                            completeness={completeness}
                            templateLabel={savedTemplateLabel}
                        />

                        <ProductPublicPanel
                            organizationSlug={organizationSlug}
                            productId={product.id}
                            seal={seal}
                            override={sealOverride}
                            availableSeals={availableSeals}
                            publicUrl={publicUrl}
                            canOverrideSeal={permissions.canOverrideSeal}
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
 * The history, once it has arrived.
 *
 * A component of its own because Deferred renders its children only when
 * the prop has landed, and the prop is read off the page rather than
 * threaded down through it.
 */
function History() {
    const { history } = usePage<{ history?: ProductEvent[] }>().props;

    return <ProductHistoryPanel events={history ?? []} />;
}

/**
 * How many of a section's requirements are still outstanding.
 *
 * Nothing at all when the section is square with its template, rather
 * than a zero: a row of zeroes reads as a scoreboard, and the point of
 * the mark is to catch the eye only where there is something to do.
 */
function OutstandingBadge({
    section,
    count,
}: {
    section: string;
    count?: number;
}) {
    if (!count) {
        return null;
    }

    return (
        <SectionBadge tone="attention" testId={`${section}-outstanding`}>
            <span className="sr-only">still needed: </span>
            {count}
        </SectionBadge>
    );
}

/**
 * The form's own save button, which says whether it can be seen.
 *
 * The floating bar below is only worth showing when this one has scrolled
 * away: two save buttons on screen at once is a page asking the same
 * question twice.
 */
function SaveRow({
    processing,
    onScreenChange,
}: {
    processing: boolean;
    onScreenChange: (onScreen: boolean) => void;
}) {
    const row = useRef<HTMLDivElement>(null);

    useEffect(() => {
        const element = row.current;

        if (element === null) {
            return;
        }

        const observer = new IntersectionObserver(([entry]) =>
            onScreenChange(entry.isIntersecting),
        );

        observer.observe(element);

        return () => observer.disconnect();
    }, [onScreenChange]);

    return (
        <div ref={row} className="flex items-center gap-3">
            <Button
                type="submit"
                data-test="update-product-submit"
                disabled={processing}
            >
                Save changes
            </Button>
        </div>
    );
}

/**
 * The save button, brought to wherever the person is on the page.
 *
 * The form runs the height of four panels, so the button at its foot can
 * be a long way from the field just edited -- and an edit nobody can see
 * a way to save is an edit that gets lost on the way out. It appears only
 * once something has actually changed, which also makes it the page's
 * answer to "is any of this unsaved?".
 */
function UnsavedChangesBar({
    dirty,
    processing,
}: {
    dirty: boolean;
    processing: boolean;
}) {
    if (!dirty) {
        return null;
    }

    return (
        <div
            data-test="product-unsaved-bar"
            className="pointer-events-none fixed inset-x-0 bottom-6 z-30 flex justify-center px-4"
        >
            <div className="bg-card pointer-events-auto flex items-center gap-3 rounded-full border py-2 pr-2 pl-5 shadow-lg">
                <span className="text-muted-foreground text-sm whitespace-nowrap">
                    Unsaved changes
                </span>
                <Button
                    type="submit"
                    size="sm"
                    className="rounded-full"
                    data-test="product-unsaved-bar-submit"
                    disabled={processing}
                >
                    {processing ? 'Saving…' : 'Save changes'}
                </Button>
            </div>
        </div>
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
