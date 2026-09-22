<?php

namespace App\Enums;

/**
 * How much weight to put on a guessed document kind.
 *
 * Two levels rather than a number, because the only decision it drives is
 * whether the page marks the row as worth a second look.
 */
enum GuessConfidence: string
{
    case High = 'high';
    case Low = 'low';
}
