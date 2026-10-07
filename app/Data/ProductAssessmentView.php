<?php

namespace App\Data;

use App\Actions\Products\AssessProductDocuments;
use App\Models\Product;
use App\Models\ProductAssessment;
use App\Models\ProductAssessmentFinding;

/**
 * An AI reading of a product's papers, as the product page shows it.
 *
 * Kept in one place because the page gets the latest run with the page and
 * an earlier run on request, and the two must read the same.
 */
class ProductAssessmentView
{
    /**
     * How many earlier runs the page lists.
     */
    public const int HISTORY_LIMIT = 10;

    /**
     * Transform one run, with its findings, for the frontend.
     *
     * @return array<string, mixed>
     */
    public static function detail(ProductAssessment $assessment): array
    {
        $assessment->loadMissing(['findings', 'requester']);

        return [
            ...self::summary($assessment),
            'summary' => $assessment->summary,
            'factory_request' => $assessment->factory_request,
            'failure_reason' => $assessment->failure_reason,
            'provider_label' => $assessment->provider->label(),
            'model_label' => $assessment->provider->modelLabel($assessment->model),
            'prompt_version' => $assessment->prompt_version,
            'requested_by' => $assessment->requester?->name,
            'documents' => array_map(fn (array $document): array => [
                'id' => $document['id'],
                'name' => $document['name'],
            ], $assessment->documents),
            'skipped_documents' => array_map(fn (array $document): array => [
                'id' => $document['id'],
                'name' => $document['name'],
                'reason' => self::skippedReasonLabel($document['reason']),
            ], $assessment->skipped_documents),
            'findings' => $assessment->findings
                ->sortBy(fn (ProductAssessmentFinding $finding): array => [$finding->severity->rank(), $finding->position])
                ->map(fn (ProductAssessmentFinding $finding): array => [
                    'id' => $finding->id,
                    'severity' => $finding->severity->value,
                    'severity_label' => $finding->severity->label(),
                    'category' => $finding->category->value,
                    'category_label' => $finding->category->label(),
                    'requirement' => $finding->requirement,
                    'rationale' => $finding->rationale,
                    'evidence' => $finding->evidence,
                    'ask_manufacturer' => $finding->ask_manufacturer,
                    'document_id' => $finding->product_document_id,
                    'document_name' => $finding->document_name,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * List the product's runs, newest first, without their findings.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function history(Product $product): array
    {
        return $product->assessments()
            ->withCount('findings')
            ->limit(self::HISTORY_LIMIT)
            ->get()
            ->map(fn (ProductAssessment $assessment): array => self::summary($assessment))
            ->all();
    }

    /**
     * The parts of a run that a list of runs shows.
     *
     * @return array{id: int, status: string, status_label: string, is_pending: bool, overall: string|null, overall_label: string|null, findings_count: int, created_at: string|null, completed_at: string|null}
     */
    private static function summary(ProductAssessment $assessment): array
    {
        return [
            'id' => $assessment->id,
            'status' => $assessment->status->value,
            'status_label' => $assessment->status->label(),
            'is_pending' => $assessment->status->isPending(),
            'overall' => $assessment->overall?->value,
            'overall_label' => $assessment->overall?->label(),
            'findings_count' => $assessment->findings_count ?? $assessment->findings->count(),
            'created_at' => $assessment->created_at?->toISOString(),
            'completed_at' => $assessment->completed_at?->toISOString(),
        ];
    }

    /**
     * Say why a paper was not read.
     */
    private static function skippedReasonLabel(string $reason): string
    {
        return match ($reason) {
            AssessProductDocuments::SKIPPED_UNSUPPORTED => __('Only PDFs and images can be read'),
            AssessProductDocuments::SKIPPED_OVER_BUDGET => __('Too large to send with the rest'),
            AssessProductDocuments::SKIPPED_MISSING => __('The file could not be found'),
            default => __('Not read'),
        };
    }
}
