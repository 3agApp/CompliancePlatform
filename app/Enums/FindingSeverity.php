<?php

namespace App\Enums;

/**
 * How much a gap in a product's papers matters.
 *
 * Cases are listed most serious first; rank() is what findings are sorted by.
 */
enum FindingSeverity: string
{
    case Critical = 'critical';
    case Major = 'major';
    case Minor = 'minor';
    case Info = 'info';

    /**
     * Get the display label for the severity.
     */
    public function label(): string
    {
        return match ($this) {
            self::Critical => __('Critical'),
            self::Major => __('Major'),
            self::Minor => __('Minor'),
            self::Info => __('Note'),
        };
    }

    /**
     * Get the position of the severity, most serious first.
     */
    public function rank(): int
    {
        return match ($this) {
            self::Critical => 0,
            self::Major => 1,
            self::Minor => 2,
            self::Info => 3,
        };
    }
}
