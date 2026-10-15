<?php

namespace App\Enums;

/**
 * Where one import from the source has got to.
 */
enum ImportRunStatus: string
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
            self::Running => __('Importing'),
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
