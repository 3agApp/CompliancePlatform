<?php

namespace App\Enums;

/**
 * One thing a template can ask a product for: a kind of document, or a field
 * on the product itself.
 *
 * Every case is named by the column it is stored in on product_templates, so
 * this enum is the register those columns are read and written through. The
 * template editor, the checklist on a product and the completeness score all
 * come from here, which is what keeps a new requirement down to one case and
 * one column rather than a change in four places.
 *
 * Nothing here is ever enforced. A product saves with none of its
 * requirements met; the requirements say what is still owed, not what must be
 * supplied before the form will take it.
 */
enum ProductRequirement: string
{
    case TestReport = 'requires_test_report';
    case DeclarationOfConformity = 'requires_declaration_of_conformity';
    case Certificate = 'requires_certificate';
    case SafetyImage = 'requires_safety_image';
    case ProductImage = 'requires_product_image';
    case RegulatoryDocument = 'requires_regulatory_document';
    case ManualOrInstructions = 'requires_manual_or_instructions';
    case OtherDocument = 'requires_other_document';

    case Brand = 'requires_brand';
    case Ean = 'requires_ean';
    case InternalArticleNumber = 'requires_internal_article_number';
    case SupplierArticleNumber = 'requires_supplier_article_number';
    case OrderNumber = 'requires_order_number';
    case CustomsTariffNumber = 'requires_customs_tariff_number';
    case CountryOfOrigin = 'requires_country_of_origin';
    case AgeGrading = 'requires_age_grading';
    case SafetyNotice = 'requires_safety_notice';
    case WarningText = 'requires_warning_text';
    case MaterialInformation = 'requires_material_information';
    case UsageRestrictions = 'requires_usage_restrictions';
    case SafetyInstructions = 'requires_safety_instructions';
    case AdditionalNotes = 'requires_additional_notes';

    /**
     * The group the requirement is shown under.
     */
    public const string DOCUMENT_GROUP = 'document';

    /**
     * The group the requirement is shown under.
     */
    public const string DATA_GROUP = 'data';

    /**
     * Get the kind of document this asks for, if it asks for one at all.
     */
    public function documentType(): ?ProductDocumentType
    {
        return match ($this) {
            self::TestReport => ProductDocumentType::TestReport,
            self::DeclarationOfConformity => ProductDocumentType::DeclarationOfConformity,
            self::Certificate => ProductDocumentType::Certificate,
            self::SafetyImage => ProductDocumentType::SafetyImage,
            self::ProductImage => ProductDocumentType::ProductImage,
            self::RegulatoryDocument => ProductDocumentType::RegulatoryDocument,
            self::ManualOrInstructions => ProductDocumentType::ManualOrInstructions,
            self::OtherDocument => ProductDocumentType::Other,
            default => null,
        };
    }

    /**
     * Get the attribute on the product this asks to be filled in, if it asks
     * for a field rather than a document.
     */
    public function productAttribute(): ?string
    {
        return match ($this) {
            self::Brand => 'brand_id',
            self::Ean => 'ean',
            self::InternalArticleNumber => 'internal_article_number',
            self::SupplierArticleNumber => 'supplier_article_number',
            self::OrderNumber => 'order_number',
            self::CustomsTariffNumber => 'customs_tariff_number',
            self::CountryOfOrigin => 'country_of_origin',
            self::AgeGrading => 'age_grading',
            self::SafetyNotice => 'safety_notice',
            self::WarningText => 'warning_text',
            self::MaterialInformation => 'material_information',
            self::UsageRestrictions => 'usage_restrictions',
            self::SafetyInstructions => 'safety_instructions',
            self::AdditionalNotes => 'additional_notes',
            default => null,
        };
    }

    /**
     * Get the label for one of the product's own columns, if any
     * requirement asks for it.
     *
     * The register already names every field a template can ask a person to
     * fill in, so the history reads its labels off here rather than keeping
     * a second list that would drift from this one.
     */
    public static function labelForAttribute(string $attribute): ?string
    {
        foreach (self::cases() as $requirement) {
            if ($requirement->productAttribute() === $attribute) {
                return $requirement->label();
            }
        }

        return null;
    }

    /**
     * Get the group the requirement belongs to.
     */
    public function group(): string
    {
        return $this->documentType() !== null ? self::DOCUMENT_GROUP : self::DATA_GROUP;
    }

    /**
     * Get what the requirement is worth towards the completeness score.
     *
     * The papers an authority asks for first are worth the most, and a
     * manual is worth nothing: it is expected on a toy and listed as such,
     * but a product that is otherwise fully documented should not read as
     * unfinished for want of an instruction leaflet. Everything a person
     * types into the form is worth one, which puts the safety wording at
     * roughly a fifth of a typical toy template.
     */
    public function weight(): int
    {
        return match ($this) {
            self::TestReport, self::DeclarationOfConformity => 3,
            self::Certificate, self::SafetyImage => 2,
            self::ManualOrInstructions => 0,
            default => 1,
        };
    }

    /**
     * Get the display label for the requirement.
     */
    public function label(): string
    {
        return $this->documentType()?->label() ?? match ($this) {
            self::Brand => 'Brand',
            self::Ean => 'EAN/barcode',
            self::InternalArticleNumber => 'Internal article number',
            self::SupplierArticleNumber => 'Supplier article number',
            self::OrderNumber => 'Order number',
            self::CustomsTariffNumber => 'Customs tariff number',
            self::CountryOfOrigin => 'Country of origin',
            self::AgeGrading => 'Age grading',
            self::SafetyNotice => 'Safety notice',
            self::WarningText => 'Warning text',
            self::MaterialInformation => 'Material information',
            self::UsageRestrictions => 'Usage restrictions',
            self::SafetyInstructions => 'Safety instructions',
            self::AdditionalNotes => 'Additional notes',
            default => $this->value,
        };
    }

    /**
     * Get the requirements a set of template flags switches on.
     *
     * Reading the columns lives here rather than on the template, so the
     * register stays the only thing that knows what its own column names
     * mean.
     *
     * @param  array<string, mixed>  $flags
     * @return array<int, self>
     */
    public static function switchedOn(array $flags): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $requirement) => (bool) ($flags[$requirement->value] ?? false),
        ));
    }

    /**
     * Get the columns the requirements are stored in.
     *
     * @return array<int, string>
     */
    public static function columns(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get every requirement a template can ask for, for the editor and the
     * checklist alike.
     *
     * @return array<array{value: string, label: string, group: string, weight: int}>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->map(fn (self $requirement) => [
                'value' => $requirement->value,
                'label' => $requirement->label(),
                'group' => $requirement->group(),
                'weight' => $requirement->weight(),
            ])
            ->values()
            ->toArray();
    }
}
