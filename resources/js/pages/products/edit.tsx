import { Deferred, Form, Head, Link, router, usePage } from '@inertiajs/react';
import { ArrowLeft, ExternalLink, Trash2 } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import DeleteProductModal from '@/components/delete-product-modal';
import Heading from '@/components/heading';
import ProductAssessmentPanel, {
    ProductAssessmentSkeleton,
} from '@/components/product-assessment-panel';
import ProductClassificationFields from '@/components/product-classification-fields';
import ProductComplianceFields from '@/components/product-compliance-fields';
import ProductDocumentsPanel from '@/components/product-documents-panel';
import ProductFormFields from '@/components/product-form-fields';
import ProductHistoryPanel, {
    ProductHistorySkeleton,
} from '@/components/product-history-panel';
import ProductPublicPanel from '@/components/product-public-panel';
import ProductRequirementsPanel from '@/components/product-requirements-panel';
import ProductReviewPanel, {
    ProductReviewActions,
} from '@/components/product-review-panel';
import ProductReviewStatusBadge from '@/components/product-review-status-badge';
import SerialLabelsPanel from '@/components/serial-labels-panel';
import { Button } from '@/components/ui/button';
import { SectionBadge, SectionNav } from '@/components/ui/section-nav';
import { useUnsavedChanges } from '@/hooks/use-unsaved-changes';
import { t, tn } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { index, update } from '@/routes/products';
import type {
    BrandOption,
    CountryOption,
    LabelBatchSummary,
    OrganizationType,
    ProductAssessmentState,
    ProductAssessmentUnavailableReason,
    ProductCategoryOption,
    ProductCompleteness,
    ProductCompletenessItem,
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

const TABS = [
    'details',
    'documents',
    'assessment',
    'history',
    'public',
] as const;

type Tab = (typeof TABS)[number];

/** The id the product form carries, so a button outside it can submit it. */
const FORM_ID = 'edit-product-form';

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
    canAddSupplier: boolean;
    canGuessDocumentKinds: boolean;
    completeness: ProductCompleteness;
    reviewNote: string | null;
    seal: ProductSeal;
    sealOverride: ProductSealOverride | null;
    availableSeals: ProductSealOption[];
    publicUrl: string;
    labelBatches: LabelBatchSummary[];
    /**
     * Deferred: the one thing on this page that grows without bound, and
     * the only one nobody reads before everything above it.
     */
    history?: ProductEvent[];
    /**
     * The AI reading of the papers. Null for anyone who does not rule on
     * the product; deferred, and so undefined at first, for those who do.
     */
    assessment?: ProductAssessmentState | null;
    assessmentUnavailableReason: ProductAssessmentUnavailableReason | null;
    viewerType: OrganizationType;
};

/**
 * The section of the details tab an outstanding requirement is answered
 * in, or the documents tab for a paper.
 */
function sectionFor(item: ProductCompletenessItem): string {
    if (item.group === 'document') {
        return 'product-documents';
    }

    return COMPLIANCE_REQUIREMENTS.includes(item.requirement)
        ? 'product-compliance'
        : 'product-identification';
}

/**
 * The tab named in the address, so a reload or a shared link opens the
 * same one. Anything unknown falls back to the details.
 */
function tabFromUrl(url: string): Tab {
    const requested = new URL(url, 'http://localhost').searchParams.get('tab');

    return TABS.find((tab) => tab === requested) ?? 'details';
}

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
    canAddSupplier,
    canGuessDocumentKinds,
    completeness,
    reviewNote,
    seal,
    sealOverride,
    availableSeals,
    publicUrl,
    labelBatches,
    assessment,
    assessmentUnavailableReason,
    viewerType,
}: Props) {
    const { currentOrganization } = usePage().props;
    const pageUrl = usePage().url;
    const organizationSlug = currentOrganization?.slug ?? '';

    const [dirty, setDirty] = useState(false);
    const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);

    const [tab, setTab] = useState<Tab>(() => {
        const requested = tabFromUrl(pageUrl);

        return requested === 'assessment' && assessment === null
            ? 'details'
            : requested;
    });

    /**
     * Keep the open tab in the address without a request, so it survives
     * a reload and a shared link opens it. Run on every change of tab,
     * however it came about, and again whenever the page lands on a new
     * address: the forms on the other tabs are answered with a redirect to
     * the product's plain address, which keeps the page as it is but drops
     * the tab from the address bar.
     */
    useEffect(() => {
        if (tabFromUrl(window.location.href) === tab) {
            return;
        }

        const url = new URL(window.location.href);

        if (tab === 'details') {
            url.searchParams.delete('tab');
        } else {
            url.searchParams.set('tab', tab);
        }

        router.replace({
            url: url.pathname + url.search,
            preserveScroll: true,
            preserveState: true,
        });
    }, [tab, pageUrl]);

    /**
     * Whether the form's own save button is on screen. While it is, it is
     * the only one the page needs. On any other tab it is not, so the
     * floating bar carries an unsaved edit along wherever the person goes.
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

            const section = sectionFor(item);

            return { ...counts, [section]: (counts[section] ?? 0) + 1 };
        },
        {},
    );

    /** The requirements the saved product still owes, for the field markers. */
    const outstandingRequirements = completeness.items
        .filter((item) => !item.satisfied)
        .map((item) => item.requirement);

    const outstandingDetails =
        (outstanding['product-identification'] ?? 0) +
        (outstanding['product-compliance'] ?? 0);

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
     * Where an outstanding item from the status strip is answered: the
     * documents tab for a paper, else the section of the details holding
     * the field -- scrolled to once the tab it sits on is showing.
     */
    const openItem = (item: ProductCompletenessItem) => {
        const section = sectionFor(item);

        if (section === 'product-documents') {
            setTab('documents');

            return;
        }

        setTab('details');

        requestAnimationFrame(() => {
            const target = document.getElementById(section);

            target?.scrollIntoView({ behavior: 'smooth', block: 'start' });
            target?.focus({ preventScroll: true });
        });
    };

    /**
     * The three sets of questions on the details tab -- who supplies it,
     * what it is, and what it claims -- stacked in the order a product is
     * usually filled in. They stay one form on one tab, so everything typed
     * is submitted together.
     */
    const sections = [
        { id: 'product-classification', label: t('Classification') },
        {
            id: 'product-identification',
            label: t('Identification'),
            badge: (
                <OutstandingBadge
                    section="product-identification"
                    count={outstanding['product-identification']}
                />
            ),
        },
        {
            id: 'product-compliance',
            label: t('Compliance'),
            badge: (
                <OutstandingBadge
                    section="product-compliance"
                    count={outstanding['product-compliance']}
                />
            ),
        },
    ];

    const tabs: Array<{ key: Tab; label: string; badge?: React.ReactNode }> = [
        {
            key: 'details',
            label: t('Details'),
            badge: (
                <OutstandingBadge
                    section="details"
                    count={outstandingDetails}
                />
            ),
        },
        {
            key: 'documents',
            label: t('Documents'),
            badge: (
                <>
                    {product.documents.length > 0 ? (
                        <SectionBadge>{product.documents.length}</SectionBadge>
                    ) : null}
                    <OutstandingBadge
                        section="product-documents"
                        count={outstanding['product-documents']}
                    />
                </>
            ),
        },
        ...(assessment !== null
            ? [{ key: 'assessment' as const, label: t('AI check') }]
            : []),
        { key: 'history', label: t('Activity') },
        {
            key: 'public',
            label: permissions.canManageSerialLabels
                ? t('Public page & labels')
                : t('Public page'),
        },
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
                                <ArrowLeft className="h-4 w-4" />{' '}
                                {t('Products')}
                            </Link>
                        </Button>
                        <div className="flex flex-wrap items-center gap-3">
                            <h1 className="page-title break-words">
                                {product.name}
                            </h1>
                            <ProductReviewStatusBadge
                                status={product.review_status}
                                label={product.review_status_label}
                            />
                        </div>
                        <div className="text-muted-foreground flex flex-wrap gap-x-5 gap-y-1 text-sm">
                            {product.counterparty ? (
                                <p>
                                    {tn(
                                        viewerType === 'supplier'
                                            ? 'Assigned by :name'
                                            : 'Supplied by :name',
                                        {
                                            name: (
                                                <span className="text-foreground font-medium">
                                                    {product.counterparty}
                                                </span>
                                            ),
                                        },
                                    )}
                                </p>
                            ) : null}
                            <p>
                                {tn('Template :name', {
                                    name: (
                                        <span className="text-foreground font-medium">
                                            {savedTemplateLabel}
                                        </span>
                                    ),
                                })}
                            </p>
                        </div>
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        <Button variant="ghost" asChild>
                            <a
                                href={publicUrl}
                                target="_blank"
                                rel="noreferrer"
                                data-test="product-public-page-link"
                            >
                                <ExternalLink className="h-4 w-4" />{' '}
                                {t('Public page')}
                            </a>
                        </Button>

                        {permissions.canDeleteProduct ? (
                            <Button
                                variant="outline"
                                size="icon"
                                aria-label={t('Delete product')}
                                title={t('Delete product')}
                                data-test="product-delete-button"
                                onClick={() => setDeleteDialogOpen(true)}
                            >
                                <Trash2 className="h-4 w-4" />
                            </Button>
                        ) : null}

                        <ProductReviewActions
                            organizationSlug={organizationSlug}
                            product={product}
                            permissions={permissions}
                            completeness={completeness}
                        />
                    </div>
                </div>

                <ProductReviewPanel
                    product={product}
                    permissions={permissions}
                    reviewNote={reviewNote}
                    completeness={completeness}
                    templateLabel={savedTemplateLabel}
                    onOpenItem={openItem}
                />

                <ProductTabs tabs={tabs} active={tab} onChange={setTab} />

                {/*
                 * Every tab stays mounted and is only hidden, so an edit
                 * typed on the details is still there after a look at the
                 * documents, and the deferred panels load once.
                 */}
                <TabPanel tab="details" active={tab}>
                    <div className="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_18rem]">
                        <Form
                            {...update.form([organizationSlug, product.id])}
                            id={FORM_ID}
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
                            className="min-w-0 space-y-6"
                            /**
                             * Typing raises input; the selects raise only
                             * change. Both are needed, or picking a supplier
                             * or a country and walking away would lose the
                             * edit without the guard ever asking.
                             */
                            onInput={() => setDirty(true)}
                            onChange={() => setDirty(true)}
                            onSuccess={() => setDirty(false)}
                            /**
                             * Saved from the bar on another tab, a refused
                             * field would otherwise complain out of sight.
                             */
                            onError={() => setTab('details')}
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
                                            title={t('Classification')}
                                            description={t(
                                                'Who supplies the product, and which template it is held to.',
                                            )}
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
                                            /**
                                             * A supplier added from the
                                             * dialog is chosen without a
                                             * change event, so the page is
                                             * told it has an edit to save.
                                             */
                                            onConnectionChange={(id) => {
                                                setConnectionId(id);
                                                setDirty(true);
                                            }}
                                            disabled={
                                                !permissions.canUpdateProduct
                                            }
                                            idPrefix="edit-product"
                                            addSupplier={
                                                canAddSupplier
                                                    ? { organizationSlug }
                                                    : null
                                            }
                                        />
                                    </section>

                                    <section
                                        id="product-identification"
                                        tabIndex={-1}
                                        className="workspace-panel scroll-mt-6 space-y-6 p-6 outline-none"
                                    >
                                        <Heading
                                            variant="small"
                                            title={t('Product details')}
                                            description={t(
                                                'What the product is, and the numbers each side of the trade knows it by.',
                                            )}
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
                                            outstanding={
                                                outstandingRequirements
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
                                            title={t('Compliance details')}
                                            description={t(
                                                'What the product claims about its own safety: the warnings it carries, who it is for, and how it may be used.',
                                            )}
                                        />

                                        <ProductComplianceFields
                                            errors={errors}
                                            requirements={
                                                template?.requirements ?? []
                                            }
                                            outstanding={
                                                outstandingRequirements
                                            }
                                            product={product}
                                            disabled={
                                                !permissions.canUpdateProduct
                                            }
                                            idPrefix="edit-product"
                                        />
                                    </section>

                                    {permissions.canUpdateProduct ? (
                                        <SaveRow
                                            processing={processing}
                                            visible={tab === 'details'}
                                            onScreenChange={setSaveRowOnScreen}
                                        />
                                    ) : (
                                        <p className="text-muted-foreground text-sm">
                                            {t(
                                                'You do not have permission to edit this product.',
                                            )}
                                        </p>
                                    )}
                                </>
                            )}
                        </Form>

                        {/*
                         * Taller than a laptop screen once the checklist is
                         * long, so the rail is held to the viewport and
                         * scrolls on its own. The padding keeps focus rings
                         * from being clipped by the scroll box.
                         */}
                        <div className="grid gap-4 lg:sticky lg:top-6 lg:-m-1 lg:max-h-[calc(100svh-3rem)] lg:overflow-y-auto lg:p-1">
                            {/*
                             * Hidden on small screens, where the rail sits
                             * below the form: a link that scrolls backwards
                             * past everything it names is worse than a plain
                             * scroll.
                             */}
                            <SectionNav
                                sections={sections}
                                idPrefix="edit-product"
                                className="hidden lg:block"
                            />

                            {/*
                             * The status strip above the tabs already lists
                             * what is still needed, so the rail keeps the
                             * score and what is done.
                             */}
                            <ProductRequirementsPanel
                                completeness={completeness}
                                templateLabel={savedTemplateLabel}
                                hideOutstanding
                            />
                        </div>
                    </div>
                </TabPanel>

                {/*
                 * The documents panel posts its own multipart form, so it
                 * sits outside the one above -- uploading a file never
                 * touches the fields.
                 */}
                <TabPanel tab="documents" active={tab}>
                    <section
                        id="product-documents"
                        tabIndex={-1}
                        className="outline-none"
                    >
                        <ProductDocumentsPanel
                            organizationSlug={organizationSlug}
                            productId={product.id}
                            documents={product.documents}
                            availableDocumentTypes={availableDocumentTypes}
                            outstandingTypes={outstandingDocumentTypes}
                            canUpload={permissions.canUpdateProduct}
                            canPublish={permissions.canPublishDocuments}
                            canGuessKinds={canGuessDocumentKinds}
                        />
                    </section>
                </TabPanel>

                {assessment !== null ? (
                    <TabPanel tab="assessment" active={tab}>
                        <section
                            id="product-assessment"
                            tabIndex={-1}
                            className="outline-none"
                        >
                            <Deferred
                                data="assessment"
                                fallback={<ProductAssessmentSkeleton />}
                            >
                                <Assessment
                                    organizationSlug={organizationSlug}
                                    product={product}
                                    unavailableReason={
                                        assessmentUnavailableReason
                                    }
                                    canReview={permissions.canReviewProduct}
                                />
                            </Deferred>
                        </section>
                    </TabPanel>
                ) : null}

                <TabPanel tab="history" active={tab}>
                    <section
                        id="product-history"
                        tabIndex={-1}
                        className="outline-none"
                    >
                        <Deferred
                            data="history"
                            fallback={<ProductHistorySkeleton />}
                        >
                            <History />
                        </Deferred>
                    </section>
                </TabPanel>

                <TabPanel tab="public" active={tab}>
                    <div className="grid items-start gap-6 lg:grid-cols-2">
                        <ProductPublicPanel
                            organizationSlug={organizationSlug}
                            productId={product.id}
                            seal={seal}
                            override={sealOverride}
                            availableSeals={availableSeals}
                            publicUrl={publicUrl}
                            canOverrideSeal={permissions.canOverrideSeal}
                        />

                        {permissions.canManageSerialLabels ? (
                            <SerialLabelsPanel
                                organizationSlug={organizationSlug}
                                productId={product.id}
                                batches={labelBatches}
                            />
                        ) : null}
                    </div>
                </TabPanel>
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
 * The row of tabs under the status strip.
 *
 * Arrow keys move between them as the tab pattern expects, and only the
 * open one sits in the Tab order, so the keyboard reaches the panel in
 * one press rather than five.
 */
function ProductTabs({
    tabs,
    active,
    onChange,
}: {
    tabs: Array<{ key: Tab; label: string; badge?: React.ReactNode }>;
    active: Tab;
    onChange: (tab: Tab) => void;
}) {
    const buttons = useRef<Partial<Record<Tab, HTMLButtonElement>>>({});

    const step = (event: React.KeyboardEvent, from: number) => {
        const offsets: Record<string, number> = {
            ArrowRight: 1,
            ArrowLeft: -1,
        };

        let next: number | undefined;

        if (event.key in offsets) {
            next = (from + offsets[event.key] + tabs.length) % tabs.length;
        } else if (event.key === 'Home') {
            next = 0;
        } else if (event.key === 'End') {
            next = tabs.length - 1;
        }

        if (next === undefined) {
            return;
        }

        event.preventDefault();
        onChange(tabs[next].key);
        buttons.current[tabs[next].key]?.focus();
    };

    return (
        <div
            role="tablist"
            aria-label={t('Product sections')}
            className="-mb-2 flex gap-6 overflow-x-auto shadow-[inset_0_-1px_0_var(--border)]"
        >
            {tabs.map((tab, position) => {
                const isActive = tab.key === active;

                return (
                    <button
                        key={tab.key}
                        ref={(element) => {
                            if (element !== null) {
                                buttons.current[tab.key] = element;
                            }
                        }}
                        type="button"
                        role="tab"
                        id={`product-tab-${tab.key}`}
                        aria-selected={isActive}
                        aria-controls={`product-tabpanel-${tab.key}`}
                        tabIndex={isActive ? 0 : -1}
                        data-test={`product-tab-${tab.key}`}
                        onClick={() => onChange(tab.key)}
                        onKeyDown={(event) => step(event, position)}
                        className={cn(
                            'focus-visible:ring-ring inline-flex shrink-0 items-center gap-2 border-b-2 px-0.5 py-3 text-sm font-medium whitespace-nowrap transition-colors outline-none focus-visible:ring-2',
                            isActive
                                ? 'border-foreground text-foreground'
                                : 'text-muted-foreground hover:text-foreground border-transparent',
                        )}
                    >
                        {tab.label}
                        {tab.badge}
                    </button>
                );
            })}
        </div>
    );
}

function TabPanel({
    tab,
    active,
    children,
}: {
    tab: Tab;
    active: Tab;
    children: React.ReactNode;
}) {
    return (
        <div
            role="tabpanel"
            id={`product-tabpanel-${tab}`}
            aria-labelledby={`product-tab-${tab}`}
            data-test={`product-tabpanel-${tab}`}
            hidden={tab !== active}
        >
            {children}
        </div>
    );
}

/**
 * The AI reading, once it has arrived.
 *
 * Read off the page for the same reason as the history below: Deferred
 * renders its children only once the prop has landed.
 */
function Assessment(
    props: Omit<
        React.ComponentProps<typeof ProductAssessmentPanel>,
        'assessment'
    >,
) {
    const { assessment } = usePage<{
        assessment?: ProductAssessmentState | null;
    }>().props;

    if (!assessment) {
        return null;
    }

    return <ProductAssessmentPanel {...props} assessment={assessment} />;
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
            <span className="sr-only">{t('still needed:')} </span>
            {count}
        </SectionBadge>
    );
}

/**
 * The form's own save button, which says whether it can be seen.
 *
 * The floating bar below is only worth showing when this one is out of
 * sight -- scrolled away, or on a tab that is not open: two save buttons
 * on screen at once is a page asking the same question twice.
 */
function SaveRow({
    processing,
    visible,
    onScreenChange,
}: {
    processing: boolean;
    visible: boolean;
    onScreenChange: (onScreen: boolean) => void;
}) {
    const row = useRef<HTMLDivElement>(null);
    const [intersecting, setIntersecting] = useState(true);

    useEffect(() => {
        const element = row.current;

        if (element === null) {
            return;
        }

        const observer = new IntersectionObserver(([entry]) =>
            setIntersecting(entry.isIntersecting),
        );

        observer.observe(element);

        return () => observer.disconnect();
    }, []);

    useEffect(() => {
        onScreenChange(visible && intersecting);
    }, [visible, intersecting, onScreenChange]);

    return (
        <div ref={row} className="flex items-center gap-3">
            <Button
                type="submit"
                data-test="update-product-submit"
                disabled={processing}
            >
                {t('Save changes')}
            </Button>
        </div>
    );
}

/**
 * The save button, brought to wherever the person is.
 *
 * The form runs the height of three panels and sits on one tab of five,
 * so the button at its foot can be a long way from where the person is --
 * and an edit nobody can see a way to save is an edit that gets lost on
 * the way out. Drawn outside the form, so it still shows while the tab
 * holding the form is hidden, and tied back to it by the form's id.
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

    return createPortal(
        <div
            data-test="product-unsaved-bar"
            className="pointer-events-none fixed inset-x-0 bottom-6 z-30 flex justify-center px-4"
        >
            <div className="bg-card pointer-events-auto flex items-center gap-3 rounded-full border py-2 pr-2 pl-5 shadow-lg">
                <span className="size-2 rounded-full bg-amber-500" />
                <span className="text-muted-foreground text-sm whitespace-nowrap">
                    {t('Unsaved changes')}
                </span>
                <Button
                    type="submit"
                    form={FORM_ID}
                    size="sm"
                    className="rounded-full"
                    data-test="product-unsaved-bar-submit"
                    disabled={processing}
                >
                    {processing ? t('Saving…') : t('Save changes')}
                </Button>
            </div>
        </div>,
        document.body,
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
        t('This product has changes that have not been saved. Leave anyway?'),
    );

    return null;
}
