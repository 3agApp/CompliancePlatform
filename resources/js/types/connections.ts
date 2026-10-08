import type { ProductReviewStatus } from './products';
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
    /** Invited, never answered, and the link has run out. */
    isExpired: boolean;
    isAssignable: boolean;
    canResend: boolean;
    canRestore: boolean;
    productsCount: number;
    brandsCount: number;
    /** The supplier's products counted per stage of the review. */
    productsByStatus: Record<ProductReviewStatus, number>;
    /** When any of the supplier's products last changed. */
    lastActivityAt: string | null;
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
