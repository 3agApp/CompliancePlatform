<?php

namespace App\Enums;

/**
 * What an AI reading concluded about the papers as a whole.
 *
 * There is deliberately no "compliant". The reading is a second pair of eyes
 * for the person who signs the product off, and the best it can say is that
 * it found nothing to flag -- never that the product may go on sale.
 */
enum AssessmentOverall: string
{
    case NoGapsFound = 'no_gaps_found';
    case GapsFound = 'gaps_found';
    case InsufficientDocuments = 'insufficient_documents';

    /**
     * Get the display label for the conclusion.
     */
    public function label(): string
    {
        return match ($this) {
            self::NoGapsFound => __('No gaps found'),
            self::GapsFound => __('Gaps found'),
            self::InsufficientDocuments => __('Not enough to judge'),
        };
    }
}
