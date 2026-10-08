<?php

namespace App\Support;

use App\Enums\ProductReviewStatus;
use App\Models\Organization;
use App\Models\Product;
use App\Models\SupplierConnection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/**
 * The products where it is an organization's move, in the order to take
 * them.
 *
 * A distributor's move is the review, so its queue is what has been
 * submitted, longest waiting first. A supplier's move is the filling in:
 * what was sent back comes first, oldest first, then everything never
 * submitted at all. The dashboard lists the head of this queue and the
 * product page steps through it, so both read it from here.
 */
class ProductQueue
{
    /**
     * The review states the queue holds, in the order they are worked
     * through.
     *
     * @return list<ProductReviewStatus>
     */
    public static function statuses(bool $asSupplier): array
    {
        return $asSupplier
            ? [ProductReviewStatus::ChangesRequested, ProductReviewStatus::Draft]
            : [ProductReviewStatus::InReview];
    }

    /**
     * Get the organization's queue, ordered.
     *
     * @return Builder<Product>|HasMany<Product, Organization>|HasManyThrough<Product, SupplierConnection, Organization>
     */
    public static function for(Organization $organization, bool $asSupplier): Builder|HasMany|HasManyThrough
    {
        $query = ($asSupplier ? $organization->suppliedProducts() : $organization->products())
            ->whereIn('products.review_status', array_map(
                fn (ProductReviewStatus $status) => $status->value,
                self::statuses($asSupplier),
            ));

        if (! $asSupplier) {
            return $query->orderBy('products.submitted_at')->orderBy('products.id');
        }

        $sentBack = ProductReviewStatus::ChangesRequested->value;

        /**
         * Sent back before never submitted, and within each, the one that
         * has waited longest first: since it was sent back, or since it was
         * added.
         */
        return $query
            ->orderByRaw('case when products.review_status = ? then 0 else 1 end', [$sentBack])
            ->orderByRaw('case when products.review_status = ? then products.reviewed_at else products.created_at end', [$sentBack])
            ->orderBy('products.id');
    }
}
