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
