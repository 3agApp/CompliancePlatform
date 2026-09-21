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
    ean: string | null;
    internal_article_number: string | null;
    supplier_article_number: string | null;
    order_number: string | null;
    customs_tariff_number: string | null;
    country_of_origin: CountryOfOrigin | null;
    country_of_origin_label: string | null;
    supplier_connection_id: number | null;
    counterparty: string | null;
    connection_status: string | null;
    created_at: string | null;
};

export type ProductPermissions = {
    canCreateProduct: boolean;
    canUpdateProduct: boolean;
    canDeleteProduct: boolean;
};

export type ProductFilters = {
    connection: number | null;
    category: number | null;
    brand: number | null;
    search: string | null;
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

export type ProductDocument = {
    id: number;
    type: ProductDocumentType;
    type_label: string;
    name: string;
    size: number;
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
