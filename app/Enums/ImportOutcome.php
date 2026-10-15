<?php

namespace App\Enums;

/**
 * What one article came to in an import.
 */
enum ImportOutcome: string
{
    case Created = 'created';

    /**
     * Created under a template the AI chose, which somebody should check.
     */
    case CreatedByAi = 'created_by_ai';

    case Updated = 'updated';
    case Skipped = 'skipped';
}
