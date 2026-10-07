<?php

namespace Database\Factories;

use App\Enums\FindingCategory;
use App\Enums\FindingSeverity;
use App\Models\ProductAssessment;
use App\Models\ProductAssessmentFinding;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductAssessmentFinding>
 */
class ProductAssessmentFindingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_assessment_id' => ProductAssessment::factory(),
            'product_document_id' => null,
            'document_name' => null,
            'severity' => FindingSeverity::Major,
            'category' => FindingCategory::Incomplete,
            'requirement' => 'DoC: harmonised standards applied',
            'rationale' => 'The declaration does not list which harmonised standards the toy was tested against.',
            'evidence' => null,
            'ask_manufacturer' => 'Send a declaration that lists EN 71-1, EN 71-2 and EN 71-3.',
            'position' => 0,
        ];
    }
}
