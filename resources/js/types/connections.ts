export type SupplierConnectionStatus =
    | 'pending'
    | 'active'
    | 'declined'
    | 'revoked';

export type SupplierConnection = {
    uuid: string;
    companyName: string;
    contactEmail: string;
    status: SupplierConnectionStatus;
    statusLabel: string;
    isClaimed: boolean;
    isAssignable: boolean;
    canResend: boolean;
    canRestore: boolean;
    productsCount: number;
    expiresAt: string | null;
    createdAt: string | null;
};

export type DistributorConnection = {
    uuid: string;
    distributorName: string;
    productsCount: number;
    connectedAt: string | null;
};

export type SupplierConnectionOption = {
    id: number;
    label: string;
    isPending: boolean;
};

export type SupplierConnectionPermissions = {
    canViewConnection: boolean;
    canManageConnection: boolean;
};

export type PendingSupplierConnection = {
    code: string;
    inviterName: string;
    companyName: string;
    expiresAt: string | null;
    distributorName: string;
};

export type ClaimableSupplierConnection = {
    code: string;
    companyName: string;
    distributorName: string;
    inviterName: string;
    expiresAt: string | null;
};

export type BindableOrganization = {
    name: string;
    slug: string;
};
