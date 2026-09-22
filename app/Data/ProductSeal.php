<?php

namespace App\Data;

use App\Enums\ProductReviewStatus;
use App\Enums\ProductSealStatus;
use App\Models\Product;

/**
 * The seal a product carries on its public page.
 *
 * Read from the review rather than kept alongside it, so the seal cannot
 * drift from what the review actually says: a product that is approved is
 * verified, one that has been worked on is a check in progress, and one
 * nobody has started is neither.
 *
 * An override stands in front of all of that. It is the distributor's own
 * word about their own product, so it is recorded with who gave it and why,
 * and the public page says the seal was set by hand rather than passing it
 * off as the outcome of a check.
 */
readonly class ProductSeal
{
    public function __construct(
        public ProductSealStatus $status,
        public string $label,
        public string $message,
        /** How far along the product is against its template, for the bar on an unfinished check. */
        public int $score,
        /** When the check passed, on a verified product that earned it. */
        public ?string $approvedAt,
        public bool $isOverridden,
    ) {
        //
    }

    /**
     * Read the seal off the product.
     *
     * Expects the template and documents to be loaded, because the score
     * comes from the same reading the catalogue uses.
     */
    public static function for(Product $product): self
    {
        $score = $product->completeness()->score;
        $earned = self::earned($product, $score);
        $status = $product->seal_override ?? $earned;

        /**
         * Only a product that actually passed a check shows a date. An
         * override forced to verified has no approval behind it, so it has
         * no date to show either.
         */
        $approvedAt = $earned === ProductSealStatus::Verified && $status === ProductSealStatus::Verified
            ? $product->reviewed_at?->toISOString()
            : null;

        return new self(
            status: $status,
            label: $status->label(),
            message: $status->message(),
            score: $score,
            approvedAt: $approvedAt,
            isOverridden: $product->seal_override !== null,
        );
    }

    /**
     * Work out the seal the product's own review has earned it.
     *
     * Approved is the only way to green. Below that, anything that has been
     * filled in at all is a check under way, and a product with nothing on
     * it yet is simply not verified.
     */
    protected static function earned(Product $product, int $score): ProductSealStatus
    {
        if ($product->review_status === ProductReviewStatus::Approved) {
            return ProductSealStatus::Verified;
        }

        return $score > 0 ? ProductSealStatus::InProgress : ProductSealStatus::NotVerified;
    }
}
