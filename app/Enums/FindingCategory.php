<?php

namespace App\Enums;

/**
 * What kind of gap a finding is.
 */
enum FindingCategory: string
{
    case MissingDocument = 'missing_document';
    case WrongDocumentType = 'wrong_document_type';
    case Incomplete = 'incomplete';
    case OutdatedOrExpired = 'outdated_or_expired';
    case MismatchWithProduct = 'mismatch_with_product';
    case Unclear = 'unclear';

    /**
     * Get the display label for the category.
     */
    public function label(): string
    {
        return match ($this) {
            self::MissingDocument => __('Missing document'),
            self::WrongDocumentType => __('Wrong kind of document'),
            self::Incomplete => __('Incomplete'),
            self::OutdatedOrExpired => __('Outdated or expired'),
            self::MismatchWithProduct => __('Does not match the product'),
            self::Unclear => __('Unclear or unreadable'),
        };
    }
}
