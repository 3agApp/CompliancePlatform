export type SupplierConnectionStatus =
    | 'pending'
    | 'active'
    | 'declined'
    | 'revoked';

export type SupplierConnection = {
    id: number;
    companyName: string;
    contactEmail: string;
    status: SupplierConnectionStatus;
    statusLabel: string;
    isClaimed: boolean;
    /** Whether the claim link has ever been mailed. */
    isInvited: boolean;
    isAssignable: boolean;
    canResend: boolean;
    canRestore: boolean;
    productsCount: number;
    expiresAt: string | null;
    createdAt: string | null;
};

export type DistributorConnection = {
    id: number;
    distributorName: string;
    productsCount: number;
    connectedAt: string | null;
};

export type SupplierConnectionOption = {
    id: number;
    label: string;
    isPending: boolean;
    /** Whether the claim link has been mailed; a pending one may not be yet. */
    isInvited: boolean;
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
