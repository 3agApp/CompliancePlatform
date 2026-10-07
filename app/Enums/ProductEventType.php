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
    case ApprovalRevoked = 'approval_revoked';
    case ReturnedToDraft = 'returned_to_draft';
    case SealOverridden = 'seal_overridden';
    case SealOverrideCleared = 'seal_override_cleared';
    case LabelsIssued = 'labels_issued';
    case LabelsRevoked = 'labels_revoked';
    case DocumentPublished = 'document_published';
    case DocumentUnpublished = 'document_unpublished';
    case AssessmentRequested = 'assessment_requested';
    case AssessmentCompleted = 'assessment_completed';

    /**
     * Get the display label for the event.
     */
    public function label(): string
    {
        return match ($this) {
            self::Created => __('Product created'),
            self::Updated => __('Details updated'),
            self::DocumentUploaded => __('Document uploaded'),
            self::DocumentRemoved => __('Document removed'),
            self::Submitted => __('Submitted for review'),
            self::Approved => __('Approved'),
            self::ChangesRequested => __('Changes requested'),
            self::ApprovalRevoked => __('Approval taken back'),
            self::ReturnedToDraft => __('Returned to draft'),
            self::SealOverridden => __('Public seal set by hand'),
            self::SealOverrideCleared => __('Public seal handed back to the review'),
            self::LabelsIssued => __('Serialised labels issued'),
            self::LabelsRevoked => __('Serialised labels withdrawn'),
            self::DocumentPublished => __('Document released to the public page'),
            self::DocumentUnpublished => __('Document taken off the public page'),
            self::AssessmentRequested => __('AI document check started'),
            self::AssessmentCompleted => __('AI document check finished'),
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
            self::Submitted, self::Approved, self::ChangesRequested, self::ApprovalRevoked, self::ReturnedToDraft,
            self::SealOverridden, self::SealOverrideCleared => true,
            default => false,
        };
    }
}
