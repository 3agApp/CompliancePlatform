<?php

namespace App\Data;

use App\Actions\Products\AssessProductDocuments;
use App\Enums\AssessmentStatus;
use App\Enums\FindingSeverity;
use App\Enums\ProductDocumentType;
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
        $assessment->loadMissing(['findings.previousFinding', 'requester', 'previousAssessment.findings']);

        $compared = self::isCompared($assessment);

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
                    'change' => $compared ? ($finding->previous_finding_id !== null ? 'still_open' : 'new') : null,
                    /**
                     * Only when it moved: "was critical" is worth a glance,
                     * "was major" on a gap still rated major is noise.
                     */
                    'previous_severity_label' => $finding->previousFinding !== null && $finding->previousFinding->severity !== $finding->severity
                        ? $finding->previousFinding->severity->label()
                        : null,
                ])
                ->values()
                ->all(),
            'comparison' => $compared ? self::comparison($assessment) : null,
        ];
    }

    /**
     * Determine whether the run has an earlier one to be read against.
     *
     * Only a finished run: one still going has nothing to compare yet.
     */
    private static function isCompared(ProductAssessment $assessment): bool
    {
        return $assessment->status === AssessmentStatus::Completed
            && $assessment->previousAssessment !== null;
    }

    /**
     * Say what changed since the run before: what the factory fixed, what is
     * new, and what is still open.
     *
     * A gap from the earlier run counts as resolved when no finding in this
     * run says it carries it on. The model makes that link, so a reworded gap
     * it failed to recognise shows as one resolved and one new -- which the
     * page says, rather than presenting the list as certain.
     *
     * @return array{previous_id: int, previous_completed_at: string|null, new_count: int, still_open_count: int, resolved_count: int, resolved: array<int, array<string, mixed>>}
     */
    private static function comparison(ProductAssessment $assessment): array
    {
        $previous = $assessment->previousAssessment;
        $carried = $assessment->findings->pluck('previous_finding_id')->filter()->unique();

        $resolved = $previous->findings
            ->reject(fn (ProductAssessmentFinding $finding): bool => $carried->contains($finding->id))
            ->sortBy(fn (ProductAssessmentFinding $finding): array => [$finding->severity->rank(), $finding->position])
            ->map(fn (ProductAssessmentFinding $finding): array => [
                'id' => $finding->id,
                'severity' => $finding->severity->value,
                'severity_label' => $finding->severity->label(),
                'requirement' => $finding->requirement,
                'rationale' => $finding->rationale,
                'document_name' => $finding->document_name,
            ])
            ->values()
            ->all();

        $stillOpen = $assessment->findings->whereNotNull('previous_finding_id')->count();

        return [
            'previous_id' => $previous->id,
            'previous_completed_at' => $previous->completed_at?->toISOString(),
            'new_count' => $assessment->findings->count() - $stillOpen,
            'still_open_count' => $stillOpen,
            'resolved_count' => count($resolved),
            'resolved' => $resolved,
        ];
    }

    /**
     * Gather what the printed report shows: the run, and enough about the
     * product to know which one it was about without the platform at hand.
     *
     * The report is a record to hand on -- to an auditor, a colleague, the
     * file -- so it carries the names and numbers as they stand when it is
     * printed, and says plainly that a person made the decision.
     *
     * @return array<string, mixed>
     */
    public static function report(ProductAssessment $assessment): array
    {
        $product = $assessment->product;
        $product->loadMissing(['organization', 'supplierConnection.supplierOrganization']);

        $connection = $product->supplierConnection;
        $detail = self::detail($assessment);

        $documentTypes = collect($assessment->documents)
            ->mapWithKeys(fn (array $document): array => [
                $document['id'] => ProductDocumentType::tryFrom($document['type'])?->label(),
            ]);

        return [
            'assessment' => [
                ...$detail,
                'documents' => array_map(fn (array $document): array => [
                    ...$document,
                    'type_label' => $documentTypes->get($document['id']),
                ], $detail['documents']),
            ],
            'severityCounts' => collect(FindingSeverity::cases())
                ->map(fn (FindingSeverity $severity): array => [
                    'label' => $severity->label(),
                    'count' => $assessment->findings->where('severity', $severity)->count(),
                ])
                ->all(),
            'product' => [
                'name' => $product->name,
                'ean' => $product->ean,
                'supplier_article_number' => $product->supplier_article_number,
                'internal_article_number' => $product->internal_article_number,
                'age_grading' => $product->age_grading,
                'review_status_label' => $product->review_status->label(),
                'distributor' => $product->organization->name,
                'supplier' => $connection === null
                    ? null
                    : ($connection->supplierOrganization !== null ? $connection->supplierOrganization->name : $connection->company_name),
            ],
            'generatedAt' => now(),
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
