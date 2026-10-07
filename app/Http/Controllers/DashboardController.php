<?php

namespace App\Http\Controllers;

use App\Enums\ProductReviewStatus;
use App\Enums\SupplierConnectionStatus;
use App\Models\Organization;
use App\Models\Product;
use App\Models\SupplierConnection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
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

        $isSupplier = $currentOrganization->isSupplier();

        return Inertia::render('dashboard', [
            'viewerType' => $currentOrganization->type->value,
            'stats' => $isSupplier
                ? $this->supplierStats($currentOrganization)
                : $this->distributorStats($currentOrganization),
            'pipeline' => $this->pipeline($currentOrganization, $isSupplier),
            'queue' => $this->queue($currentOrganization, $isSupplier),
        ]);
    }

    /**
     * Get how the catalog splits across the review, one stage at a time.
     *
     * The product count alone says how big the catalog is, not how much of
     * it is done -- and a distributor with one product in review and two
     * hundred never submitted is in a very different place from one with
     * two hundred signed off. Every stage is listed, empty ones included,
     * so the bar keeps its shape as products move through it.
     *
     * @return array<array{status: string, label: string, count: int}>
     */
    protected function pipeline(Organization $organization, bool $asSupplier): array
    {
        $counts = $this->visibleProducts($organization, $asSupplier)
            ->toBase()
            ->selectRaw('products.review_status as status, count(*) as aggregate')
            ->groupBy('products.review_status')
            ->pluck('aggregate', 'status');

        return collect([
            ProductReviewStatus::Draft,
            ProductReviewStatus::InReview,
            ProductReviewStatus::ChangesRequested,
            ProductReviewStatus::Approved,
        ])
            ->map(fn (ProductReviewStatus $status) => [
                'status' => $status->value,
                'label' => $status->label(),
                'count' => (int) ($counts[$status->value] ?? 0),
            ])
            ->all();
    }

    /**
     * Get the first few products waiting on the viewer, and how many there
     * are in all.
     *
     * A count of what is waiting still leaves somebody to go and find it,
     * so the products themselves are named, longest-waiting first. For a
     * distributor that is what has been submitted; for a supplier it is
     * what was sent back, ahead of what was never sent at all.
     *
     * @return array{items: array<array{id: int, name: string, counterparty: string|null, review_status: string, review_status_label: string, completeness_score: int, since: string|null}>, total: int}
     */
    protected function queue(Organization $organization, bool $asSupplier): array
    {
        $limit = 5;

        $stages = $asSupplier
            ? [
                [ProductReviewStatus::ChangesRequested, 'products.reviewed_at'],
                [ProductReviewStatus::Draft, 'products.created_at'],
            ]
            : [[ProductReviewStatus::InReview, 'products.submitted_at']];

        $items = collect();
        $total = 0;

        foreach ($stages as [$status, $waitingSince]) {
            $query = $this->visibleProducts($organization, $asSupplier)->inReviewStatus($status);

            $total += (clone $query)->count();

            if ($items->count() < $limit) {
                $items = $items->concat(
                    $query
                        ->with(['template', 'documents:id,product_id,type', 'supplierConnection.supplierOrganization', 'supplierConnection.distributorOrganization'])
                        ->orderBy($waitingSince)
                        ->orderBy('products.id')
                        ->limit($limit - $items->count())
                        ->get()
                        ->map(fn (Product $product) => [
                            'id' => $product->id,
                            'name' => $product->name,
                            'counterparty' => $this->counterparty($product->supplierConnection, $asSupplier),
                            'review_status' => $product->review_status->value,
                            'review_status_label' => $product->review_status->label(),
                            'completeness_score' => $product->completeness()->score,
                            'since' => $product->getAttribute(str_replace('products.', '', $waitingSince))?->toISOString(),
                        ]),
                );
            }
        }

        return ['items' => $items->values()->all(), 'total' => $total];
    }

    /**
     * Get the products the organization can see from its side of the trade.
     *
     * @return HasMany<Product, Organization>|HasManyThrough<Product, SupplierConnection, Organization>
     */
    protected function visibleProducts(Organization $organization, bool $asSupplier): HasMany|HasManyThrough
    {
        return $asSupplier ? $organization->suppliedProducts() : $organization->products();
    }

    /**
     * Name the other side of the trade: the distributor to a supplier, and
     * to a distributor the supplier -- or, for an invitation nobody has
     * claimed yet, the company it was sent to.
     */
    protected function counterparty(?SupplierConnection $connection, bool $asSupplier): ?string
    {
        if ($connection === null) {
            return null;
        }

        if ($asSupplier) {
            return $connection->distributorOrganization->name;
        }

        return $connection->supplierOrganization->name ?? $connection->company_name;
    }

    /**
     * Get the numbers that orient a distributor.
     *
     * Three indexed counts, so there is nothing here worth deferring. Reach
     * for Inertia::optional only once something expensive joins them.
     *
     * @return array{products: int, awaitingReview: int, activeSuppliers: int, pendingInvitations: int, notInvited: int}
     */
    protected function distributorStats(Organization $organization): array
    {
        return [
            'products' => $organization->products()->count(),
            /**
             * What is actually waiting on this organization, which is the
             * one number on the page that is a to-do list rather than a
             * measurement.
             */
            'awaitingReview' => $organization->products()
                ->where('review_status', ProductReviewStatus::InReview)
                ->count(),
            'activeSuppliers' => $organization->supplierConnections()
                ->where('status', SupplierConnectionStatus::Active)
                ->count(),
            'pendingInvitations' => $organization->supplierConnections()
                ->where('status', SupplierConnectionStatus::Pending)
                ->whereNotNull('invited_at')
                ->count(),
            /**
             * Suppliers added while setting up a catalog and not told yet.
             * Easy to forget, since nothing on their side will prompt
             * anybody, so the dashboard keeps count until they are invited.
             */
            'notInvited' => $organization->supplierConnections()
                ->where('status', SupplierConnectionStatus::Pending)
                ->whereNull('invited_at')
                ->count(),
        ];
    }

    /**
     * Get the numbers that orient a supplier.
     *
     * The product count goes through the connections, so a revoked
     * relationship leaves the number at the same moment it leaves the list.
     *
     * @return array{products: int, changesRequested: int, distributors: int}
     */
    protected function supplierStats(Organization $organization): array
    {
        return [
            'products' => $organization->suppliedProducts()->count(),
            /**
             * The products a distributor has handed back, which is the
             * supplier's side of the same to-do list.
             */
            'changesRequested' => $organization->suppliedProducts()
                ->where('products.review_status', ProductReviewStatus::ChangesRequested)
                ->count(),
            'distributors' => $organization->distributorConnections()
                ->where('status', SupplierConnectionStatus::Active)
                ->count(),
        ];
    }
}
