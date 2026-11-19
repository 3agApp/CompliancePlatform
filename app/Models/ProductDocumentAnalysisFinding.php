<?php

namespace App\Models;

use App\Enums\FindingCategory;
use App\Enums\FindingSeverity;
use Carbon\CarbonImmutable;
use Database\Factories\ProductDocumentAnalysisFindingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One gap an AI reading found in a single paper, with why it was flagged
 * and what to ask the manufacturer for.
 *
 * @property int $id
 * @property int $product_document_analysis_id
 * @property FindingSeverity $severity
 * @property FindingCategory $category
 * @property string $requirement
 * @property string $rationale
 * @property string|null $evidence
 * @property string|null $ask_manufacturer
 * @property int $position
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read ProductDocumentAnalysis $analysis
 */
#[Fillable([
    'severity',
    'category',
    'requirement',
    'rationale',
    'evidence',
    'ask_manufacturer',
    'position',
])]
class ProductDocumentAnalysisFinding extends Model
{
    /** @use HasFactory<ProductDocumentAnalysisFindingFactory> */
    use HasFactory;

    /**
     * The run that found it.
     *
     * @return BelongsTo<ProductDocumentAnalysis, $this>
     */
    public function analysis(): BelongsTo
    {
        return $this->belongsTo(ProductDocumentAnalysis::class, 'product_document_analysis_id');
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
