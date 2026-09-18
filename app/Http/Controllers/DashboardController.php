<?php

namespace App\Http\Controllers;

use App\Enums\SupplierConnectionStatus;
use App\Models\Organization;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The landing page for an organization: where it stands and what to do next.
 *
 * The two sides measure different things, so the numbers are chosen per side
 * rather than shared. Every number is something the viewer can click through
 * to; a count with no page behind it is a dead end.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request, Organization $currentOrganization): Response
    {
        Gate::authorize('viewAny', [Product::class, $currentOrganization]);

        return Inertia::render('dashboard', [
            'viewerType' => $currentOrganization->type->value,
            'stats' => $currentOrganization->isSupplier()
                ? $this->supplierStats($currentOrganization)
                : $this->distributorStats($currentOrganization),
        ]);
    }

    /**
     * Get the numbers that orient a distributor.
     *
     * Three indexed counts, so there is nothing here worth deferring. Reach
     * for Inertia::optional only once something expensive joins them.
     *
     * @return array{products: int, activeSuppliers: int, pendingInvitations: int}
     */
    protected function distributorStats(Organization $organization): array
    {
        return [
            'products' => $organization->products()->count(),
            'activeSuppliers' => $organization->supplierConnections()
                ->where('status', SupplierConnectionStatus::Active)
                ->count(),
            'pendingInvitations' => $organization->supplierConnections()
                ->where('status', SupplierConnectionStatus::Pending)
                ->count(),
        ];
    }

    /**
     * Get the numbers that orient a supplier.
     *
     * The product count goes through the connections, so a revoked
     * relationship leaves the number at the same moment it leaves the list.
     *
     * @return array{products: int, distributors: int}
     */
    protected function supplierStats(Organization $organization): array
    {
        return [
            'products' => $organization->suppliedProducts()->count(),
            'distributors' => $organization->distributorConnections()
                ->where('status', SupplierConnectionStatus::Active)
                ->count(),
        ];
    }
}
