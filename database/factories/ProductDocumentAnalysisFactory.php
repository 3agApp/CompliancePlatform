<?php

namespace Database\Factories;

use App\Enums\AiProvider;
use App\Enums\AssessmentOverall;
use App\Enums\AssessmentStatus;
use App\Models\Organization;
use App\Models\ProductDocument;
use App\Models\ProductDocumentAnalysis;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductDocumentAnalysis>
 */
class ProductDocumentAnalysisFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * A run that has been asked for and not yet picked up.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_document_id' => ProductDocument::factory(),
            'organization_id' => Organization::factory(),
            'requested_by' => null,
            'status' => AssessmentStatus::Queued,
            'provider' => AiProvider::Gemini,
            'model' => AiProvider::Gemini->defaultModel(),
            'prompt_version' => '1',
        ];
    }

    /**
     * Indicate that the run finished with gaps to show.
     */
    public function completed(AssessmentOverall $overall = AssessmentOverall::GapsFound): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AssessmentStatus::Completed,
            'overall' => $overall,
            'summary' => 'An EN 71-3 test report from a named laboratory; the sample description does not give an article number.',
            'facts' => [
                'issuer' => 'Example Testing Laboratory GmbH',
                'document_number' => 'TR-2026-0142',
                'issued_on' => '2026-03-04',
                'expires_on' => null,
                'standards' => ['EN 71-3:2019+A1:2021'],
                'covered_product' => 'Wooden stacking rings',
                'type_matches' => true,
                'suggested_type' => null,
            ],
            'started_at' => now()->subMinute(),
            'completed_at' => now(),
        ]);
    }

    /**
     * Indicate that the run could not finish.
     */
    public function failed(string $reason = 'The AI provider did not answer.'): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AssessmentStatus::Failed,
            'failure_reason' => $reason,
            'started_at' => now()->subMinute(),
            'completed_at' => now(),
        ]);
    }
}
