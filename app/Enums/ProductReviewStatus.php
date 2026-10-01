<?php

namespace App\Enums;

/**
 * Where a product stands between the supplier filling it in and the
 * distributor signing it off.
 *
 * The distributor creates the product and the supplier answers for it, so
 * the two sides need one word for whose turn it is. A product starts as
 * homework: nobody has offered it up yet. Submitting hands it over,
 * reviewing hands it back -- either signed off, or with a note saying what
 * is still missing.
 *
 * Nothing here is enforced by the schema and nothing here gates the form: a
 * product can be submitted with half its requirements outstanding, and the
 * reviewer decides whether that is good enough. The status says whose move
 * it is, not whether the product is complete -- the completeness score
 * already answers that.
 */
enum ProductReviewStatus: string
{
    case Draft = 'draft';
    case InReview = 'in_review';
    case Approved = 'approved';
    case ChangesRequested = 'changes_requested';

    /**
     * Get the display label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::InReview => 'In review',
            self::Approved => 'Approved',
            self::ChangesRequested => 'Changes requested',
        };
    }

    /**
     * Get the one line that says whose move it is.
     */
    public function description(): string
    {
        return match ($this) {
            self::Draft => 'Not submitted yet. Fill in what the template asks for, then submit it for review.',
            self::InReview => 'Submitted and waiting on the distributor.',
            self::Approved => 'Signed off by the distributor.',
            self::ChangesRequested => 'Sent back with a note. Make the changes, then submit it again.',
        };
    }

    /**
     * Determine if a product in this state can be submitted for review.
     *
     * A product already in review cannot be submitted twice, and an approved
     * one has nothing to submit: editing it is what puts it back in draft,
     * and that is the step that makes it submittable again.
     */
    public function isSubmittable(): bool
    {
        return $this === self::Draft || $this === self::ChangesRequested;
    }

    /**
     * Determine if a product in this state is waiting on a reviewer.
     */
    public function isReviewable(): bool
    {
        return $this === self::InReview;
    }

    /**
     * Determine if a reviewer can take back the sign-off on a product in
     * this state.
     */
    public function isReopenable(): bool
    {
        return $this === self::Approved;
    }

    /**
     * Determine if an edit by the supplier should put the product back in
     * draft.
     *
     * A product under review is being read as it stands, and an approved one
     * was signed off as it stood. Changing either behind the reviewer's back
     * would leave the status describing a product that no longer exists, so
     * the edit withdraws it and the supplier submits again.
     */
    public function isUnsettledByEdit(): bool
    {
        return $this === self::InReview || $this === self::Approved;
    }

    /**
     * Get every status the product list can be filtered by.
     *
     * @return array<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->map(fn (self $status) => ['value' => $status->value, 'label' => $status->label()])
            ->values()
            ->toArray();
    }

    /**
     * Get the statuses as their backing values.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
