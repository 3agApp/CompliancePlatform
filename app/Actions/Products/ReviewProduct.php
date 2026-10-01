<?php

namespace App\Actions\Products;

use App\Enums\ProductEventType;
use App\Enums\ProductReviewStatus;
use App\Models\Organization;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The moves a product can make between the supplier filling it in and the
 * distributor signing it off.
 *
 * Every move is a status and a line of history written together, so the
 * catalogue and the record can never disagree about what happened. The rules
 * about who may make a move live in the policy; the rules about which moves
 * exist live here.
 */
class ReviewProduct
{
    /**
     * Offer the product up for review.
     */
    public function submit(Product $product, User $actor, Organization $organization): void
    {
        DB::transaction(function () use ($product, $actor, $organization) {
            $product->forceFill([
                'review_status' => ProductReviewStatus::InReview,
                'submitted_at' => now(),
                'reviewed_at' => null,
            ])->save();

            $product->recordEvent(ProductEventType::Submitted, $actor, $organization);
        });
    }

    /**
     * Sign the product off.
     */
    public function approve(Product $product, User $actor, Organization $organization): void
    {
        DB::transaction(function () use ($product, $actor, $organization) {
            $product->forceFill([
                'review_status' => ProductReviewStatus::Approved,
                'reviewed_at' => now(),
            ])->save();

            $product->recordEvent(ProductEventType::Approved, $actor, $organization);
        });
    }

    /**
     * Send the product back with a note saying what is still needed.
     *
     * The note is the whole point of this move rather than a plain refusal,
     * so it is required by the request that reaches here.
     */
    public function requestChanges(Product $product, User $actor, Organization $organization, string $note): void
    {
        DB::transaction(function () use ($product, $actor, $organization, $note) {
            $product->forceFill([
                'review_status' => ProductReviewStatus::ChangesRequested,
                'reviewed_at' => now(),
            ])->save();

            $product->recordEvent(ProductEventType::ChangesRequested, $actor, $organization, note: $note);
        });
    }

    /**
     * Take back the sign-off and put the product in front of the reviewer
     * again.
     *
     * For an approval given by mistake. The supplier's submission still
     * stands, so the product goes back into review rather than to draft:
     * the distributor can then approve it again or send it back with a
     * note. The original approval stays in the history; this is written
     * after it, with the reason, rather than over it.
     */
    public function reopen(Product $product, User $actor, Organization $organization, string $note): void
    {
        DB::transaction(function () use ($product, $actor, $organization, $note) {
            $product->forceFill([
                'review_status' => ProductReviewStatus::InReview,
                'reviewed_at' => null,
            ])->save();

            $product->recordEvent(ProductEventType::ApprovalRevoked, $actor, $organization, note: $note);
        });
    }

    /**
     * Withdraw the product after the supplier changed it.
     *
     * A product under review is being read as it stands, and an approved one
     * was signed off as it stood; either way, the supplier changing it means
     * the status no longer describes the product. So the change puts it back
     * in draft and the supplier offers it up again, which is also what makes
     * the distributor look at it a second time.
     *
     * Only the supplier's changes do this. The distributor owns the product
     * and is the one doing the reviewing, so their own edit is part of the
     * review rather than something that invalidates it.
     */
    public function withdrawAfterEdit(Product $product, User $actor, Organization $organization): bool
    {
        if ($organization->id === $product->organization_id) {
            return false;
        }

        if (! $product->review_status->isUnsettledByEdit()) {
            return false;
        }

        DB::transaction(function () use ($product, $actor, $organization) {
            $product->forceFill([
                'review_status' => ProductReviewStatus::Draft,
                'submitted_at' => null,
                'reviewed_at' => null,
            ])->save();

            $product->recordEvent(ProductEventType::ReturnedToDraft, $actor, $organization);
        });

        return true;
    }
}
