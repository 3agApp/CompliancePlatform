<?php

namespace App\Enums;

/**
 * One thing that happened to a product.
 *
 * Two sides of a trade edit the same record, so "who changed this, and when"
 * is a question somebody asks eventually -- usually the moment an authority
 * asks it of them. Every case here is written to the product's history the
 * moment it happens, and nothing in that history is ever edited or removed.
 */
enum ProductEventType: string
{
    case Created = 'created';
    case Updated = 'updated';
    case DocumentUploaded = 'document_uploaded';
    case DocumentRemoved = 'document_removed';

    case Submitted = 'submitted';
    case Approved = 'approved';
    case ChangesRequested = 'changes_requested';
    case ReturnedToDraft = 'returned_to_draft';
    case SealOverridden = 'seal_overridden';
    case SealOverrideCleared = 'seal_override_cleared';

    /**
     * Get the display label for the event.
     */
    public function label(): string
    {
        return match ($this) {
            self::Created => 'Product created',
            self::Updated => 'Details updated',
            self::DocumentUploaded => 'Document uploaded',
            self::DocumentRemoved => 'Document removed',
            self::Submitted => 'Submitted for review',
            self::Approved => 'Approved',
            self::ChangesRequested => 'Changes requested',
            self::ReturnedToDraft => 'Returned to draft',
            self::SealOverridden => 'Public seal set by hand',
            self::SealOverrideCleared => 'Public seal handed back to the review',
        };
    }

    /**
     * Determine if the event is a step in the review itself, rather than a
     * change to the product.
     *
     * The history shows both together -- the point of it is that they
     * interleave -- but a review step is the part that is worth catching the
     * eye, so the timeline marks them differently.
     */
    public function isReviewStep(): bool
    {
        return match ($this) {
            self::Submitted, self::Approved, self::ChangesRequested, self::ReturnedToDraft,
            self::SealOverridden, self::SealOverrideCleared => true,
            default => false,
        };
    }
}
