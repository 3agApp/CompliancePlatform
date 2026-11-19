<?php

namespace App\Enums;

enum ProductDocumentType: string
{
    case TestReport = 'test_report';
    case DeclarationOfConformity = 'declaration_of_conformity';
    case ManualOrInstructions = 'manual_or_instructions';
    case Certificate = 'certificate';
    case ProductImage = 'product_image';
    case SafetyImage = 'safety_image';
    case PackagingCloseUp = 'packaging_closeup';
    case RegulatoryDocument = 'regulatory_document';
    case Other = 'other';

    /**
     * Get the display label for the kind of document.
     */
    public function label(): string
    {
        return match ($this) {
            self::TestReport => __('Test report'),
            self::DeclarationOfConformity => __('Declaration of conformity'),
            self::ManualOrInstructions => __('Manual or instructions'),
            self::Certificate => __('Certificate'),
            self::ProductImage => __('Product image'),
            self::SafetyImage => __('Warning image'),
            self::PackagingCloseUp => __('Packaging close-up'),
            self::RegulatoryDocument => __('Regulatory document'),
            self::Other => __('Other'),
        };
    }

    /**
     * Determine if a file of this kind starts out on the public page.
     *
     * Pictures of the article and of its warning are filed to be seen by
     * buyers, so they are switched on as they arrive. Papers start hidden
     * until the distributor chooses to release them, and so do close-ups of
     * the packaging: they are evidence for the reviewer -- batch numbers,
     * the label as printed -- and the public page has no place to show them.
     */
    public function isPublicByDefault(): bool
    {
        return $this->showsPicturePublicly();
    }

    /**
     * Determine if a file of this kind is a picture, recognised by sight and
     * shown in a gallery rather than listed as a paper.
     */
    public function isPicture(): bool
    {
        return in_array($this, [self::ProductImage, self::SafetyImage, self::PackagingCloseUp], true);
    }

    /**
     * Determine if the public page has a place to show a picture of this
     * kind: the gallery for the article, the warning for its safety image.
     *
     * Any other picture -- a packaging close-up, a scanned certificate --
     * would be released to nowhere, so it cannot be released at all.
     */
    public function showsPicturePublicly(): bool
    {
        return in_array($this, [self::ProductImage, self::SafetyImage], true);
    }

    /**
     * Determine if a paper of this kind is offered for an AI reading on its
     * own.
     *
     * A product photo shows what the toy looks like and carries no claim to
     * check, so reading one alone would only spend the organization's key.
     * Safety images stay in: their warnings and marks are what is checked.
     */
    public function offersAnalysis(): bool
    {
        return $this !== self::ProductImage;
    }

    /**
     * Get all the kinds a product document can be filed under.
     *
     * @return array<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->map(fn (self $type) => ['value' => $type->value, 'label' => $type->label()])
            ->values()
            ->toArray();
    }
}
