<?php

namespace App\Models;

use App\Enums\FindingCategory;
use App\Enums\FindingSeverity;
use Carbon\CarbonImmutable;
use Database\Factories\ProductAssessmentFindingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One gap an AI reading found in a product's papers, with why it was
 * flagged and what to ask the manufacturer for.
 *
 * @property int $id
 * @property int $product_assessment_id
 * @property int|null $product_document_id
 * @property string|null $document_name
 * @property FindingSeverity $severity
 * @property FindingCategory $category
 * @property string $requirement
 * @property string $rationale
 * @property string|null $evidence
 * @property string|null $ask_manufacturer
 * @property int $position
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read ProductAssessment $assessment
 * @property-read ProductDocument|null $document
 */
#[Fillable([
    'product_document_id',
    'document_name',
    'severity',
    'category',
    'requirement',
    'rationale',
    'evidence',
    'ask_manufacturer',
    'position',
])]
class ProductAssessmentFinding extends Model
{
    /** @use HasFactory<ProductAssessmentFindingFactory> */
    use HasFactory;

    /**
     * The run that found it.
     *
     * @return BelongsTo<ProductAssessment, $this>
     */
    public function assessment(): BelongsTo
    {
        return $this->belongsTo(ProductAssessment::class, 'product_assessment_id');
    }

    /**
     * The paper it is about, while that paper is still filed.
     *
     * @return BelongsTo<ProductDocument, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(ProductDocument::class, 'product_document_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'severity' => FindingSeverity::class,
            'category' => FindingCategory::class,
        ];
    }
}
