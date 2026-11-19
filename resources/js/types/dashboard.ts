import type {
    ProductRequirementGroup,
    ProductRequirementKey,
    ProductReviewStatus,
} from './products';

export type DistributorDashboardStats = {
    products: number;
    awaitingReview: number;
    activeSuppliers: number;
    pendingInvitations: number;
    notInvited: number;
};

export type SupplierDashboardStats = {
    products: number;
    changesRequested: number;
    distributors: number;
};

export type DashboardPipelineStage = {
    status: ProductReviewStatus;
    label: string;
    count: number;
};

export type DashboardQueueItem = {
    id: number;
    name: string;
    counterparty: string | null;
    review_status: ProductReviewStatus;
    review_status_label: string;
    completeness_score: number;
    /** When the product started waiting on the viewer. */
    since: string | null;
};

export type DashboardQueue = {
    items: DashboardQueueItem[];
    total: number;
};

/**
 * Where a supplier stands with the distributor: joined, invited, invited
 * and never answered in time, or not told yet.
 */
export type DashboardSupplierStatus =
    | 'active'
    | 'invited'
    | 'expired'
    | 'not_invited';

/** One supplier's share of the catalog, counted per stage of the review. */
export type DashboardSupplierProgress = {
    id: number;
    label: string;
    status: DashboardSupplierStatus;
    products: number;
    draft: number;
    inReview: number;
    changesRequested: number;
    approved: number;
    /** When any of the supplier's products last changed. */
    lastActivity: string | null;
};

/** What is stuck until somebody acts, from a distributor's side. */
export type DashboardAttention = {
    sentBack: {
        items: Array<{
            id: number;
            name: string;
            counterparty: string | null;
            since: string | null;
        }>;
        total: number;
    };
    expiredInvitations: Array<{ id: number; label: string; products: number }>;
    /** Suppliers invited and not joined yet, still holding drafts. */
    waitingSuppliers: Array<{ id: number; label: string; draft: number }>;
    quietSuppliers: Array<{
        id: number;
        label: string;
        draft: number;
        lastActivity: string | null;
    }>;
};

/** One recent thing to happen to a product the viewer can see. */
export type DashboardActivityItem = {
    id: number;
    type: string;
    type_label: string;
    product: { id: number; name: string };
    /** How many products a run of the same event covered; 1 for a single one. */
    count: number;
    actor: string | null;
    created_at: string | null;
};

/** One requirement missing across the products not yet submitted. */
export type DashboardGap = {
    requirement: ProductRequirementKey;
    label: string;
    group: ProductRequirementGroup;
    /** How many unsubmitted products are missing it. */
    count: number;
};

/** How close the products not yet submitted are to done. */
export type DashboardInsights = {
    /** Products in draft or sent back. */
    open: number;
    /** Of those, how many have nothing left open. */
    ready: number;
    averageCompleteness: number;
    gaps: DashboardGap[];
};
