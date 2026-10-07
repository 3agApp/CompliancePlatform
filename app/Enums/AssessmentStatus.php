<?php

namespace App\Enums;

/**
 * Where one AI reading of a product's papers has got to.
 */
enum AssessmentStatus: string
{
    case Queued = 'queued';
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';

    /**
     * Get the display label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Queued => __('Waiting to start'),
            self::Running => __('Reading the documents'),
            self::Completed => __('Finished'),
            self::Failed => __('Could not finish'),
        };
    }

    /**
     * Determine if the run is still going, so another may not be started
     * beside it and the page should keep checking on it.
     */
    public function isPending(): bool
    {
        return $this === self::Queued || $this === self::Running;
    }
}
