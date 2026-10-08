<?php

namespace App\Http\Controllers;

use App\Enums\SupplierConnectionStatus;
use App\Models\Brand;
use App\Models\Organization;
use App\Models\Product;
use App\Models\SupplierConnection;
use App\Support\LikePattern;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * The search behind ⌘K: one term, looked up across everything the
 * organization can see, a few of each kind.
 *
 * Answered as JSON rather than a page, because the palette asks on every
 * pause in typing and must not disturb the page it was opened over.
 */
class SearchController extends Controller
{
    /**
     * How many of each kind come back. The palette is a way in, not a list.
     */
    protected const int LIMIT = 5;

    /**
     * The shortest term worth asking about. A single letter matches half
     * the catalogue and tells nobody anything.
     */
    protected const int MIN_LENGTH = 2;

    public function __invoke(Request $request, Organization $currentOrganization): JsonResponse
    {
        Gate::authorize('viewAny', [Product::class, $currentOrganization]);

        $term = mb_substr(trim((string) $request->query('q', '')), 0, 100);

        if (mb_strlen($term) < self::MIN_LENGTH) {
            return response()->json(['products' => [], 'productsTotal' => 0, 'connections' => [], 'brands' => []]);
        }

        $isSupplier = $currentOrganization->isSupplier();

        $products = ($isSupplier ? $currentOrganization->suppliedProducts() : $currentOrganization->products())
            ->matching($term);

        return response()->json([
            'products' => (clone $products)
                ->with(['supplierConnection.supplierOrganization', 'supplierConnection.distributorOrganization'])
                ->orderBy('products.name')
                ->limit(self::LIMIT)
                ->get()
                ->map(fn (Product $product) => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'articleNumber' => $product->internal_article_number,
                    'counterparty' => $this->counterpartyName($product->supplierConnection, $isSupplier),
                    'reviewStatus' => $product->review_status->value,
                    'reviewStatusLabel' => $product->review_status->label(),
                    'url' => route('products.edit', ['current_organization' => $currentOrganization->slug, 'product' => $product->id]),
                ])
                ->all(),
            'productsTotal' => $products->count(),
            'connections' => $this->connections($currentOrganization, $isSupplier, $term),
            'brands' => $this->brands($currentOrganization, $isSupplier, $term),
        ]);
    }

    /**
     * Get the trades whose other side is called something like the term,
     * each leading to the products that run through it.
     *
     * @return array<array{id: int, label: string, url: string}>
     */
    protected function connections(Organization $organization, bool $asSupplier, string $term): array
    {
        $pattern = LikePattern::contains($term);

        $query = $asSupplier
            ? $organization->distributorConnections()
                ->where('status', SupplierConnectionStatus::Active)
                ->whereHas('distributorOrganization', fn ($query) => $query->whereRaw("lower(name) like lower(?) escape '!'", [$pattern]))
                ->with('distributorOrganization')
            : $organization->supplierConnections()
                ->where(fn ($query) => $query
                    ->whereRaw("lower(company_name) like lower(?) escape '!'", [$pattern])
                    ->orWhereHas('supplierOrganization', fn ($query) => $query->whereRaw("lower(name) like lower(?) escape '!'", [$pattern])))
                ->with('supplierOrganization');

        return $query
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (SupplierConnection $connection) => [
                'id' => $connection->id,
                'label' => $this->counterpartyName($connection, $asSupplier) ?? '',
                'url' => route('products.index', ['current_organization' => $organization->slug, 'connection' => $connection->id]),
            ])
            ->all();
    }

    /**
     * Get the brands called something like the term, each leading to the
     * products that carry it.
     *
     * @return array<array{id: int, name: string, counterparty: string|null, url: string}>
     */
    protected function brands(Organization $organization, bool $asSupplier, string $term): array
    {
        return ($asSupplier ? $organization->suppliedBrands() : $organization->brands())
            ->whereRaw("lower(brands.name) like lower(?) escape '!'", [LikePattern::contains($term)])
            ->with(['supplierConnection.supplierOrganization', 'supplierConnection.distributorOrganization'])
            ->orderBy('brands.name')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (Brand $brand) => [
                'id' => $brand->id,
                'name' => $brand->name,
                /** Two trades can each carry a brand of the same name. */
                'counterparty' => $this->counterpartyName($brand->supplierConnection, $asSupplier),
                'url' => route('products.index', ['current_organization' => $organization->slug, 'brand' => $brand->id]),
            ])
            ->all();
    }

    /**
     * Name the other side of a trade from where the viewer stands.
     */
    protected function counterpartyName(?SupplierConnection $connection, bool $asSupplier): ?string
    {
        if ($connection === null) {
            return null;
        }

        return $asSupplier
            ? $connection->distributorOrganization->name
            : ($connection->supplierOrganization->name ?? $connection->company_name);
    }
}
