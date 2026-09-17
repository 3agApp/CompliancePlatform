<?php

namespace App\Http\Controllers\Suppliers;

use App\Enums\SupplierConnectionStatus;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\SupplierConnection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The supplier's side of the relationship: the distributors they work for.
 *
 * Read only. A supplier that wants out asks the distributor to end it, which
 * keeps a single actor in charge of each connection row.
 */
class DistributorConnectionController extends Controller
{
    /**
     * Display the distributors this supplier works for.
     */
    public function index(Request $request, Organization $currentOrganization): Response
    {
        Gate::authorize('viewAny', [SupplierConnection::class, $currentOrganization]);

        return Inertia::render('distributors/index', [
            'connections' => $currentOrganization->distributorConnections()
                ->where('status', SupplierConnectionStatus::Active)
                ->with('distributorOrganization')
                ->withCount('products')
                ->get()
                ->map(fn (SupplierConnection $connection) => [
                    'uuid' => $connection->uuid,
                    'distributorName' => $connection->distributorOrganization->name,
                    'productsCount' => (int) ($connection->products_count ?? 0),
                    'connectedAt' => $connection->accepted_at?->toIso8601String(),
                ])
                ->sortBy('distributorName')
                ->values(),
        ]);
    }
}
