<?php

namespace App\Data;

use App\Enums\ProductDocumentType;
use App\Models\Product;
use App\Models\ProductDocument;
use App\Models\ProductDocumentAnalysis;
use App\Models\ProductDocumentAnalysisFinding;

/**
 * AI readings of single papers, as the documents tab shows them.
 */
class ProductDocumentAnalysisView
{
    /**
     * Get the latest reading of every paper on the product, keyed by the
     * paper's id. Papers never read are left out.
     *
     * Reuses the papers the page has already loaded rather than asking for
     * them again.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function latestFor(Product $product): array
    {
        return $product->documents
            ->load(['latestAnalysis.findings', 'latestAnalysis.requester'])
            ->filter(fn (ProductDocument $document): bool => $document->latestAnalysis !== null)
            ->mapWithKeys(fn (ProductDocument $document): array => [
                $document->id => self::detail($document->latestAnalysis),
            ])
            ->all();
    }

    /**
     * Transform one run, with its findings and facts, for the frontend.
     *
     * @return array<string, mixed>
     */
    public static function detail(ProductDocumentAnalysis $analysis): array
    {
        $analysis->loadMissing(['findings', 'requester']);

        return [
            'id' => $analysis->id,
            'status' => $analysis->status->value,
            'status_label' => $analysis->status->label(),
            'is_pending' => $analysis->status->isPending(),
            'overall' => $analysis->overall?->value,
            'overall_label' => $analysis->overall?->label(),
            'summary' => $analysis->summary,
            'facts' => self::facts($analysis),
            'failure_reason' => $analysis->failure_reason,
            'provider_label' => $analysis->provider->label(),
            'model_label' => $analysis->provider->modelLabel($analysis->model),
            'requested_by' => $analysis->requester?->name,
            'created_at' => $analysis->created_at?->toISOString(),
            'completed_at' => $analysis->completed_at?->toISOString(),
            'findings' => $analysis->findings
                ->sortBy(fn (ProductDocumentAnalysisFinding $finding): array => [$finding->severity->rank(), $finding->position])
                ->map(fn (ProductDocumentAnalysisFinding $finding): array => [
                    'id' => $finding->id,
                    'severity' => $finding->severity->value,
                    'severity_label' => $finding->severity->label(),
                    'category' => $finding->category->value,
                    'category_label' => $finding->category->label(),
                    'requirement' => $finding->requirement,
                    'rationale' => $finding->rationale,
                    'evidence' => $finding->evidence,
                    'ask_manufacturer' => $finding->ask_manufacturer,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * Transform the facts, naming the suggested document type and saying
     * whether the paper has run out.
     *
     * @return array<string, mixed>|null
     */
    private static function facts(ProductDocumentAnalysis $analysis): ?array
    {
        $facts = $analysis->facts;

        if ($facts === null) {
            return null;
        }

        $expiresOn = $facts['expires_on'] ?? null;

        return [
            ...$facts,
            'is_expired' => $expiresOn !== null && $expiresOn < now()->toDateString(),
            'suggested_type_label' => ProductDocumentType::tryFrom((string) ($facts['suggested_type'] ?? ''))?->label(),
        ];
    }
}
