<?php

namespace App\Data;

/**
 * One guess per file, always, however badly the asking went.
 *
 * The length is a property of this type rather than something each failure
 * branch has to remember, which is what lets the page treat "the AI is off",
 * "the AI failed" and "the AI answered" as the same shape and render the
 * same table for all three.
 */
readonly class DocumentKindGuesses
{
    /**
     * The provider was never reached, and why.
     *
     * One of: not_configured, when the organization has connected nobody;
     * rejected, when the provider refused the key; unavailable, when it
     * errored, timed out or throttled. Null when the guesses are real.
     */
    public const string NOT_CONFIGURED = 'not_configured';

    public const string REJECTED = 'rejected';

    public const string UNAVAILABLE = 'unavailable';

    /**
     * @param  array<int, DocumentKindGuess>  $guesses
     */
    public function __construct(
        public array $guesses,
        public ?string $unavailable = null,
    ) {
        //
    }

    /**
     * A full set of empty guesses, for when nothing was asked.
     */
    public static function unavailable(int $count, string $reason): self
    {
        return new self(
            guesses: array_fill(0, $count, DocumentKindGuess::none()),
            unavailable: $reason,
        );
    }

    /**
     * @return array{guesses: array<int, array{type: string|null, confidence: string}>, unavailable: string|null}
     */
    public function toArray(): array
    {
        return [
            'guesses' => array_map(
                fn (DocumentKindGuess $guess): array => $guess->toArray(),
                $this->guesses,
            ),
            'unavailable' => $this->unavailable,
        ];
    }
}
