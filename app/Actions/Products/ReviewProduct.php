<?php

namespace App\Actions\Products;

use App\Enums\OrganizationPermission;
use App\Enums\ProductEventType;
use App\Enums\ProductReviewStatus;
use App\Models\Organization;
use App\Models\Product;
use App\Models\User;
use App\Notifications\Products\ProductChangesRequested;
use App\Notifications\Products\ProductSubmittedForReview;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

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
        $isResubmission = $product->review_status === ProductReviewStatus::ChangesRequested;

        DB::transaction(function () use ($product, $actor, $organization) {
            $product->forceFill([
                'review_status' => ProductReviewStatus::InReview,
                'submitted_at' => now(),
                'reviewed_at' => null,
            ])->save();

            $product->recordEvent(ProductEventType::Submitted, $actor, $organization);
        });

        $this->tellReviewers($product, $actor, $organization, $isResubmission);
    }

    /**
     * Email the distributor's reviewers that the product is waiting on them.
     *
     * Only for a supplier's submission. A distributor submitting their own
     * product is already the one who reviews it, and does not need telling.
     */
    protected function tellReviewers(Product $product, User $actor, Organization $organization, bool $isResubmission): void
    {
        if ($organization->id === $product->organization_id) {
            return;
        }

        $distributor = $product->organization;

        $reviewers = $distributor->members()
            ->get()
            ->filter(fn (User $member): bool => $member->hasOrganizationPermission($distributor, OrganizationPermission::ReviewProduct));

        $product->loadMissing(['template', 'documents']);

        $outstanding = collect($product->completeness()->items)
            ->reject(fn (array $item): bool => $item['satisfied'])
            ->count();

        Notification::send($reviewers, new ProductSubmittedForReview($product, $organization, $actor->name, $isResubmission, $outstanding));
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

        $this->tellSupplier($product, $actor, $note);
    }

    /**
     * Email the supplier that the product is back with them, and why.
     *
     * Only the people who can do something about it: those who may edit the
     * product on the supplier's side. A supplier that has not claimed the
     * connection has no account to reach, and the distributor already knows.
     */
    protected function tellSupplier(Product $product, User $actor, string $note): void
    {
        $supplier = $product->supplierConnection?->isActive()
            ? $product->supplierConnection->supplierOrganization
            : null;

        if ($supplier === null) {
            return;
        }

        $recipients = $supplier->members()
            ->get()
            ->filter(fn (User $member): bool => $member->hasOrganizationPermission($supplier, OrganizationPermission::UpdateProduct));

        Notification::send($recipients, new ProductChangesRequested($product, $supplier, $actor->name, $note));
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
