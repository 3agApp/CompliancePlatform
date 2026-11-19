<?php

namespace Database\Factories;

use App\Enums\FindingCategory;
use App\Enums\FindingSeverity;
use App\Models\ProductDocumentAnalysis;
use App\Models\ProductDocumentAnalysisFinding;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductDocumentAnalysisFinding>
 */
class ProductDocumentAnalysisFindingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_document_analysis_id' => ProductDocumentAnalysis::factory(),
            'severity' => FindingSeverity::Major,
            'category' => FindingCategory::Incomplete,
            'requirement' => 'Test report: sample identification',
            'rationale' => 'The sample is described by name only, with no article number to tie it to this product.',
            'evidence' => null,
            'ask_manufacturer' => 'Send a report whose sample description gives the article number.',
            'position' => 0,
        ];
    }
}
