import type { ProductReviewStatus } from './products';

export type DistributorDashboardStats = {
    products: number;
    awaitingReview: number;
    activeSuppliers: number;
    pendingInvitations: number;
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
