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
            self::SafetyImage => __('Safety image'),
            self::RegulatoryDocument => __('Regulatory document'),
            self::Other => __('Other'),
        };
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
