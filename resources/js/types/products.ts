export type CountryOfOrigin = 'DE' | 'CH';

export type CountryOption = {
    value: CountryOfOrigin;
    label: string;
};

export type Product = {
    uuid: string;
    name: string;
    ean: string | null;
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
