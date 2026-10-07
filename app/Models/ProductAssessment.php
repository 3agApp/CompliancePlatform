<?php

namespace App\Models;

use App\Enums\AiProvider;
use App\Enums\AssessmentOverall;
use App\Enums\AssessmentStatus;
use Carbon\CarbonImmutable;
use Database\Factories\ProductAssessmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One AI reading of a product's papers.
 *
 * Advisory and nothing more. A run is written to its own table and never to
 * the product, so it cannot move the review status or the seal: the person
 * who signs the product off reads it and decides.
 *
 * @property int $id
 * @property int $product_id
 * @property int $organization_id
 * @property int|null $requested_by
 * @property int|null $previous_assessment_id
 * @property AssessmentStatus $status
 * @property AiProvider $provider
 * @property string $model
 * @property string $prompt_version
 * @property array<int, array{id: int, name: string, type: string, mime_type: string, size: int}> $documents
 * @property array<int, array{id: int, name: string, reason: string}> $skipped_documents
 * @property AssessmentOverall|null $overall
 * @property string|null $summary
 * @property string|null $factory_request
 * @property string|null $failure_reason
 * @property CarbonImmutable|null $started_at
 * @property CarbonImmutable|null $completed_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Product $product
 * @property-read Organization $organization
 * @property-read User|null $requester
 * @property-read ProductAssessment|null $previousAssessment
 * @property-read Collection<int, ProductAssessmentFinding> $findings
 * @property-read int|null $findings_count
 */
#[Fillable([
    'organization_id',
    'requested_by',
    'previous_assessment_id',
    'status',
    'provider',
    'model',
    'prompt_version',
    'documents',
    'skipped_documents',
    'overall',
    'summary',
    'factory_request',
    'failure_reason',
    'started_at',
    'completed_at',
])]
class ProductAssessment extends Model
{
    /** @use HasFactory<ProductAssessmentFactory> */
    use HasFactory;

    /**
     * The product whose papers were read.
     *
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
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
     * The finished run this one was read against, if there was one.
     *
     * @return BelongsTo<ProductAssessment, $this>
     */
    public function previousAssessment(): BelongsTo
    {
        return $this->belongsTo(ProductAssessment::class, 'previous_assessment_id');
    }

    /**
     * The gaps the run found, in the order it gave them.
     *
     * @return HasMany<ProductAssessmentFinding, $this>
     */
    public function findings(): HasMany
    {
        return $this->hasMany(ProductAssessmentFinding::class)->orderBy('position');
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
            'documents' => 'array',
            'skipped_documents' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
