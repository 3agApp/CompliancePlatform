<?php

namespace App\Models;

use App\Enums\AiProvider;
use App\Enums\AssessmentOverall;
use App\Enums\AssessmentStatus;
use Carbon\CarbonImmutable;
use Database\Factories\ProductDocumentAnalysisFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One AI reading of a single paper filed against a product.
 *
 * Advisory, like a ProductAssessment: written to its own table, never to the
 * document or the product, so it cannot move the review or the seal. Where an
 * assessment reads every paper together, this one reads one file and says
 * what it is, what it covers and where it falls short.
 *
 * @property int $id
 * @property int $product_document_id
 * @property int $organization_id
 * @property int|null $requested_by
 * @property AssessmentStatus $status
 * @property AiProvider $provider
 * @property string $model
 * @property string $prompt_version
 * @property AssessmentOverall|null $overall
 * @property string|null $summary
 * @property array{issuer: string|null, document_number: string|null, issued_on: string|null, expires_on: string|null, standards: array<int, string>, covered_product: string|null, type_matches: bool, suggested_type: string|null}|null $facts
 * @property string|null $failure_reason
 * @property CarbonImmutable|null $started_at
 * @property CarbonImmutable|null $completed_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read ProductDocument $document
 * @property-read Organization $organization
 * @property-read User|null $requester
 * @property-read Collection<int, ProductDocumentAnalysisFinding> $findings
 * @property-read int|null $findings_count
 */
#[Fillable([
    'organization_id',
    'requested_by',
    'status',
    'provider',
    'model',
    'prompt_version',
    'overall',
    'summary',
    'facts',
    'failure_reason',
    'started_at',
    'completed_at',
])]
class ProductDocumentAnalysis extends Model
{
    /** @use HasFactory<ProductDocumentAnalysisFactory> */
    use HasFactory;

    /**
     * The paper that was read.
     *
     * @return BelongsTo<ProductDocument, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(ProductDocument::class, 'product_document_id');
    }

    /**
     * The organization whose provider was asked.
     *
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * The person who asked for the run.
     *
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * The gaps the run found, in the order it gave them.
     *
     * @return HasMany<ProductDocumentAnalysisFinding, $this>
     */
    public function findings(): HasMany
    {
        return $this->hasMany(ProductDocumentAnalysisFinding::class)->orderBy('position');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AssessmentStatus::class,
            'provider' => AiProvider::class,
            'overall' => AssessmentOverall::class,
            'facts' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
