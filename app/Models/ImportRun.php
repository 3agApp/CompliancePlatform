<?php

namespace App\Models;

use App\Enums\ImportRunStatus;
use Carbon\CarbonImmutable;
use Database\Factories\ImportRunFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One pass over the source's catalogue, and what came of it.
 *
 * Nothing here is mass assignable: every column is written by the import
 * itself, never from a request.
 *
 * @property int $id
 * @property int $organization_id
 * @property int|null $triggered_by
 * @property ImportRunStatus $status
 * @property int|null $total
 * @property int $processed
 * @property int $created
 * @property int $updated
 * @property int $skipped
 * @property int $failed
 * @property int $ai_assigned
 * @property list<array{article: string, reason: string}>|null $failures
 * @property string|null $failure_reason
 * @property CarbonImmutable|null $started_at
 * @property CarbonImmutable|null $finished_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Organization $organization
 * @property-read User|null $triggeredBy
 */
class ImportRun extends Model
{
    /** @use HasFactory<ImportRunFactory> */
    use HasFactory;

    /**
     * How many failed rows are kept with the run.
     *
     * Enough to see what went wrong and fix it; a catalogue that fails
     * wholesale says so in the count, not in ten thousand identical lines.
     */
    public const int MAX_FAILURES = 200;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ImportRunStatus::class,
            'failures' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /**
     * Get the organization the run imports into.
     *
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Get whoever started the run, while their account still exists.
     *
     * @return BelongsTo<User, $this>
     */
    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }

    /**
     * Scope the query to runs that have not finished.
     *
     * @param  Builder<ImportRun>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->whereIn('status', [ImportRunStatus::Queued, ImportRunStatus::Running]);
    }

    /**
     * Note a row that did not make it, keeping only the first few.
     */
    public function recordFailure(string $article, string $reason): void
    {
        $failures = $this->failures ?? [];

        if (count($failures) < self::MAX_FAILURES) {
            $failures[] = ['article' => $article, 'reason' => $reason];
        }

        $this->failures = $failures;
    }
}
