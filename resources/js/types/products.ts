import type { SupplierConnectionStatus } from './connections';

export type CountryOfOrigin = 'DE' | 'CH';

export type CountryOption = {
    value: CountryOfOrigin;
    label: string;
};

export type ProductCategoryOption = {
    id: number;
    label: string;
};

export type ProductCategory = {
    id: number;
    name: string;
    products_count: number;
    templates: ProductTemplate[];
};

/**
 * Everything a template can ask a product for, named by the column it is
 * stored in. Mirrors the ProductRequirement enum on the server, which is the
 * one place the list is defined.
 */
export type ProductRequirementKey =
    | 'requires_test_report'
    | 'requires_declaration_of_conformity'
    | 'requires_certificate'
    | 'requires_safety_image'
    | 'requires_product_image'
    | 'requires_regulatory_document'
    | 'requires_manual_or_instructions'
    | 'requires_other_document'
    | 'requires_brand'
    | 'requires_ean'
    | 'requires_internal_article_number'
    | 'requires_supplier_article_number'
    | 'requires_order_number'
    | 'requires_customs_tariff_number'
    | 'requires_country_of_origin'
    | 'requires_age_grading'
    | 'requires_safety_notice'
    | 'requires_warning_text'
    | 'requires_material_information'
    | 'requires_usage_restrictions'
    | 'requires_safety_instructions'
    | 'requires_additional_notes';

export type ProductRequirementGroup = 'document' | 'data';

export type ProductRequirementOption = {
    value: ProductRequirementKey;
    label: string;
    group: ProductRequirementGroup;
    weight: number;
};

/**
 * A homework sheet as the categories page manages it.
 */
export type ProductTemplate = {
    id: number;
    name: string;
    products_count: number;
    requirements: ProductRequirementKey[];
};

/**
 * A homework sheet as a product form chooses it. The category narrows the
 * list to the family already picked; the requirements mark the fields.
 */
export type ProductTemplateOption = {
    id: number;
    label: string;
    product_category_id: number;
    requirements: ProductRequirementKey[];
};

/**
 * One line of a product's checklist: what is asked for, and whether it has
 * been answered.
 */
export type ProductCompletenessItem = {
    requirement: ProductRequirementKey;
    label: string;
    group: ProductRequirementGroup;
    /** The kind of paper it asks for, on a document requirement. */
    document_type: ProductDocumentType | null;
    weight: number;
    satisfied: boolean;
};

export type ProductCompleteness = {
    score: number;
    items: ProductCompletenessItem[];
};

export type Brand = {
    id: number;
    name: string;
    products_count: number;
};

export type BrandOption = {
    id: number;
    label: string;
    supplier_connection_id: number;
};

/**
 * One trade, with the makers named under it, as the brands page reads it.
 */
export type BrandConnection = {
    id: number;
    label: string;
    status: SupplierConnectionStatus;
    statusLabel: string;
    canAddBrand: boolean;
    brands: Brand[];
};

export type BrandPermissions = {
    canCreateBrand: boolean;
    canUpdateBrand: boolean;
    canDeleteBrand: boolean;
};

export type ProductCategoryPermissions = {
    canCreateCategory: boolean;
    canUpdateCategory: boolean;
    canDeleteCategory: boolean;
};

/**
 * Whose move it is on a product: the supplier fills it in and offers it up,
 * the distributor signs it off or hands it back with a note. Mirrors the
 * ProductReviewStatus enum on the server.
 */
export type ProductReviewStatus =
    | 'draft'
    | 'in_review'
    | 'approved'
    | 'changes_requested';

export type ProductReviewStatusOption = {
    value: ProductReviewStatus;
    label: string;
};

/**
 * One thing that happened to a product. Mirrors ProductEventType.
 */
export type ProductEventType =
    | 'created'
    | 'updated'
    | 'document_uploaded'
    | 'document_removed'
    | 'submitted'
    | 'approved'
    | 'changes_requested'
    | 'approval_revoked'
    | 'returned_to_draft'
    | 'seal_overridden'
    | 'seal_override_cleared'
    | 'labels_issued'
    | 'labels_revoked'
    | 'document_published'
    | 'document_unpublished'
    | 'assessment_requested'
    | 'assessment_completed';

/**
 * One field that changed, as the history kept it: both sides are the text
 * they read as at the time, never the ids behind them.
 */
export type ProductEventChange = {
    field: string;
    label: string;
    from: string | null;
    to: string | null;
};

/**
 * One line of a product's history.
 *
 * The actor is a name rather than an account, because the record has to
 * survive the account being closed.
 */
export type ProductEvent = {
    id: number;
    type: ProductEventType;
    type_label: string;
    is_review_step: boolean;
    actor: string | null;
    actor_organization: string | null;
    note: string | null;
    changes: ProductEventChange[];
    created_at: string | null;
};

/**
 * What the public seal on a product says. Mirrors ProductSealStatus.
 */
export type ProductSealStatus = 'verified' | 'in_progress' | 'not_verified';

export type ProductSealOption = {
    value: ProductSealStatus;
    label: string;
    message: string;
};

/**
 * The seal as the public page and the product page both read it.
 *
 * The score is only worth showing on an unfinished check; the date only
 * exists on a product that actually passed one.
 */
export type ProductSeal = {
    status: ProductSealStatus;
    label: string;
    message: string;
    score: number;
    approvedAt: string | null;
    isOverridden: boolean;
};

/**
 * A seal somebody set by hand, and who to ask about it.
 */
export type ProductSealOverride = {
    seal: ProductSealStatus;
    label: string;
    reason: string | null;
    setBy: string | null;
    setAt: string | null;
};

/**
 * One picture of the article, as the public page shows it.
 */
export type PublicProductImage = {
    id: number;
    url: string;
    name: string;
};

/**
 * A product as a reader without an account sees it: what the article is,
 * and nothing about who supplies it or what it cost.
 */
export type PublicProduct = {
    uuid: string;
    name: string;
    brand: string | null;
    ean: string | null;
    internal_article_number: string | null;
};

/**
 * What a check of one serial found. Mirrors ProductUnitStatus.
 */
export type ProductUnitStatus =
    | 'unknown'
    | 'revoked'
    | 'first_check'
    | 'checked_before'
    | 'checked_elsewhere';

/**
 * The answer to a check somebody just made, with the checks that came
 * before theirs.
 */
export type UnitCheckResult = {
    serial: string;
    status: ProductUnitStatus;
    checkedAt: string | null;
    earlierChecks: number;
    history: { at: string; thisDevice: boolean }[];
};

/**
 * A document the distributor released to the public page.
 */
export type PublicProductDocument = {
    id: number;
    type: ProductDocumentType;
    name: string;
    size: number;
    url: string;
};

/**
 * What the reader needs to use the article safely.
 */
export type PublicProductSafety = {
    age_grading: string | null;
    warning_text: string | null;
    safety_notice: string | null;
    safety_instructions: string | null;
    material_information: string | null;
    usage_restrictions: string | null;
};

/**
 * One run of serialised labels, as the product page lists it.
 */
export type LabelBatchSummary = {
    id: number;
    quantity: number;
    issuedFor: string;
    checked: number;
    checks: number;
    unusual: number;
    createdAt: string | null;
    createdBy: string | null;
    revokedAt: string | null;
};

/**
 * One packet in a run, as the run's overview lists it.
 */
export type LabelUnitOverview = {
    id: number;
    serial: string;
    checks: number;
    devices: number;
    firstCheckedAt: string | null;
    lastCheckedAt: string | null;
    revoked: boolean;
};

export type Product = {
    id: number;
    name: string;
    brand_id: number | null;
    brand_label: string | null;
    product_category_id: number;
    category_label: string;
    product_template_id: number;
    template_label: string;
    completeness_score: number;
    review_status: ProductReviewStatus;
    review_status_label: string;
    review_status_description: string;
    submitted_at: string | null;
    reviewed_at: string | null;
    ean: string | null;
    internal_article_number: string | null;
    supplier_article_number: string | null;
    order_number: string | null;
    customs_tariff_number: string | null;
    country_of_origin: CountryOfOrigin | null;
    country_of_origin_label: string | null;
    supplier_connection_id: number;
    counterparty: string | null;
    connection_status: string | null;
    /** Whether the supplier has been sent the claim link yet. */
    connection_is_invited: boolean;
    created_at: string | null;
};

export type ProductPermissions = {
    canCreateProduct: boolean;
    canUpdateProduct: boolean;
    canDeleteProduct: boolean;
    canReviewProduct: boolean;
    canOverrideSeal: boolean;
    canManageSerialLabels: boolean;
    canPublishDocuments: boolean;
};

export type ProductFilters = {
    connection: number | null;
    category: number | null;
    brand: number | null;
    search: string | null;
    status: ProductReviewStatus | null;
    perPage: number;
};

export type ProductCounterparty = {
    id: number;
    label: string;
};

export type ProductComplianceDetails = {
    age_grading: string | null;
    safety_notice: string | null;
    warning_text: string | null;
    material_information: string | null;
    usage_restrictions: string | null;
    safety_instructions: string | null;
    additional_notes: string | null;
};

export type ProductDocumentType =
    | 'test_report'
    | 'declaration_of_conformity'
    | 'manual_or_instructions'
    | 'certificate'
    | 'product_image'
    | 'safety_image'
    | 'regulatory_document'
    | 'other';

export type ProductDocumentTypeOption = {
    value: ProductDocumentType;
    label: string;
};

/**
 * How a document can be shown without being downloaded first, or null when
 * the browser has no viewer for it and a download is the only way in.
 */
export type DocumentPreviewKind = 'pdf' | 'image';

export type ProductDocument = {
    id: number;
    type: ProductDocumentType;
    type_label: string;
    name: string;
    size: number;
    preview_kind: DocumentPreviewKind | null;
    is_public: boolean;
    uploaded_by: string | null;
    created_at: string | null;
};

/**
 * A product as its own page sees it: everything the list carries, plus the
 * compliance details and the papers filed against it.
 */
export type ProductDetail = Product &
    ProductComplianceDetails & {
        documents: ProductDocument[];
    };

/**
 * Where one AI reading of a product's papers has got to. Mirrors
 * AssessmentStatus.
 */
export type ProductAssessmentStatus =
    | 'queued'
    | 'running'
    | 'completed'
    | 'failed';

/**
 * What a reading concluded. Mirrors AssessmentOverall -- there is no
 * "compliant": a person decides that.
 */
export type ProductAssessmentOverall =
    | 'no_gaps_found'
    | 'gaps_found'
    | 'insufficient_documents';

export type ProductFindingSeverity = 'critical' | 'major' | 'minor' | 'info';

/**
 * One run as a list of runs shows it.
 */
export type ProductAssessmentSummary = {
    id: number;
    status: ProductAssessmentStatus;
    status_label: string;
    is_pending: boolean;
    overall: ProductAssessmentOverall | null;
    overall_label: string | null;
    findings_count: number;
    created_at: string | null;
    completed_at: string | null;
};

/**
 * One gap a reading found, with why and what to ask for.
 */
export type ProductAssessmentFinding = {
    id: number;
    severity: ProductFindingSeverity;
    severity_label: string;
    category: string;
    category_label: string;
    requirement: string;
    rationale: string;
    evidence: string | null;
    ask_manufacturer: string | null;
    /** Null when the finding is about the product as a whole, or the paper has since been removed. */
    document_id: number | null;
    document_name: string | null;
};

/**
 * One run in full.
 */
export type ProductAssessmentDetail = ProductAssessmentSummary & {
    summary: string | null;
    factory_request: string | null;
    failure_reason: string | null;
    provider_label: string;
    model_label: string;
    prompt_version: string;
    requested_by: string | null;
    documents: { id: number; name: string }[];
    skipped_documents: { id: number; name: string; reason: string }[];
    findings: ProductAssessmentFinding[];
};

/**
 * The AI reading of a product's papers, as the edit page receives it.
 */
export type ProductAssessmentState = {
    latest: ProductAssessmentDetail | null;
    runs: ProductAssessmentSummary[];
};

/**
 * Why a run cannot be started. Mirrors the constants on
 * AssessProductDocuments.
 */
export type ProductAssessmentUnavailableReason =
    | 'not_configured'
    | 'analysis_disabled';
