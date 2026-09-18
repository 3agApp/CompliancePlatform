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
};

export type Brand = {
    id: number;
    name: string;
    products_count: number;
};

export type BrandOption = {
    id: number;
    label: string;
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
    product_category_id: number | null;
    category_label: string | null;
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
