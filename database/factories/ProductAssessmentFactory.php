<?php

namespace Database\Factories;

use App\Enums\AiProvider;
use App\Enums\AssessmentOverall;
use App\Enums\AssessmentStatus;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductAssessment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductAssessment>
 */
class ProductAssessmentFactory extends Factory
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
            'product_id' => Product::factory(),
            'organization_id' => Organization::factory(),
            'requested_by' => null,
            'status' => AssessmentStatus::Queued,
            'provider' => AiProvider::Gemini,
            'model' => AiProvider::Gemini->defaultModel(),
            'prompt_version' => '1',
            'documents' => [],
            'skipped_documents' => [],
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
            'summary' => 'The declaration of conformity does not name the standards applied.',
            'factory_request' => 'Please send a declaration of conformity that lists the harmonised standards applied (EN 71-1, EN 71-2, EN 71-3).',
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
