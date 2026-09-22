<?php

namespace App\Data;

use App\Enums\GuessConfidence;
use App\Enums\ProductDocumentType;

/**
 * What the AI made of one file name.
 *
 * A null type is an ordinary answer, not a failure: plenty of file names say
 * nothing at all, and admitting so is better than filling a dropdown with
 * something nobody checked.
 */
readonly class DocumentKindGuess
{
    public function __construct(
        public ?ProductDocumentType $type,
        public GuessConfidence $confidence,
    ) {
        //
    }

    /**
     * No guess at all, for a file the answer did not cover.
     */
    public static function none(): self
    {
        return new self(type: null, confidence: GuessConfidence::Low);
    }

    /**
     * @return array{type: string|null, confidence: string}
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type?->value,
            'confidence' => $this->confidence->value,
        ];
    }
}
