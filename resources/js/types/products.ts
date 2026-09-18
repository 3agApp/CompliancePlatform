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
    uuid: string;
    name: string;
    products_count: number;
};

export type ProductCategoryPermissions = {
    canCreateCategory: boolean;
    canUpdateCategory: boolean;
    canDeleteCategory: boolean;
};

export type Product = {
    uuid: string;
    name: string;
    brand: string | null;
    product_category_id: number | null;
    category_label: string | null;
    ean: string | null;
    internal_article_number: string | null;
    supplier_article_number: string | null;
    order_number: string | null;
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
    connection: string | null;
    search: string | null;
};

export type ProductCounterparty = {
    uuid: string;
    label: string;
};
