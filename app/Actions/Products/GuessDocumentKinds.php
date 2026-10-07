<?php

namespace App\Actions\Products;

use App\Ai\Agents\DocumentKindAgent;
use App\Ai\OrganizationProvider;
use App\Data\DocumentKindGuess;
use App\Data\DocumentKindGuesses;
use App\Data\ProductDocumentCandidate;
use App\Enums\GuessConfidence;
use App\Enums\ProductDocumentType;
use App\Models\Organization;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Number;
use Throwable;

/**
 * Ask an organization's own AI provider what kind of paper each file is.
 *
 * Every path through this returns one guess per file. Nothing here throws
 * and nothing here is allowed to stop an upload: the guess is a convenience,
 * and a person who was going to pick the kinds by hand anyway must always
 * still be able to.
 */
class GuessDocumentKinds
{
    /**
     * How long to wait on the provider, in seconds.
     *
     * Short on purpose. This fills in a dropdown that somebody is sitting
     * and looking at, and past about ten seconds it is faster to pick the
     * kinds by hand than to keep waiting.
     */
    private const int TIMEOUT = 10;

    public function __construct(private OrganizationProvider $provider)
    {
        //
    }

    /**
     * @param  array<int, ProductDocumentCandidate>  $candidates
     */
    public function handle(Organization $organization, array $candidates): DocumentKindGuesses
    {
        $candidates = array_values($candidates);
        $setting = $organization->aiSetting;

        if ($setting === null) {
            return DocumentKindGuesses::unavailable(count($candidates), DocumentKindGuesses::NOT_CONFIGURED);
        }

        try {
            $response = $this->provider->using(
                $setting,
                fn (string $provider): mixed => (new DocumentKindAgent)->prompt(
                    $this->promptFor($candidates),
                    provider: $provider,
                    model: $setting->model,
                    timeout: self::TIMEOUT,
                ),
            );
        } catch (DecryptException) {
            /**
             * APP_KEY was rotated without the stored keys being re-encrypted,
             * so what is in the database is no longer a key. There is nothing
             * to prompt with until somebody saves a new one.
             */
            return DocumentKindGuesses::unavailable(count($candidates), DocumentKindGuesses::NOT_CONFIGURED);
        } catch (Throwable $exception) {
            report($exception);

            return DocumentKindGuesses::unavailable(count($candidates), DocumentKindGuesses::UNAVAILABLE);
        }

        return new DocumentKindGuesses($this->reconcile($candidates, $response['guesses'] ?? []));
    }

    /**
     * Render the files as the numbered list the instructions describe.
     *
     * @param  array<int, ProductDocumentCandidate>  $candidates
     */
    private function promptFor(array $candidates): string
    {
        $lines = collect($candidates)
            ->map(fn (ProductDocumentCandidate $candidate, int $index): string => sprintf(
                '%d. name: %s | type: %s | size: %s',
                $index,
                $candidate->name,
                $candidate->mimeType,
                Number::fileSize($candidate->size),
            ))
            ->implode("\n");

        return "Sort these files:\n\n".$lines;
    }

    /**
     * Line the answer back up with the files that were asked about.
     *
     * Walks the files rather than the answer, so an answer that is short,
     * long, reordered or partly nonsense degrades into "some rows have no
     * guess" instead of "every row is labelled as its neighbour". A
     * confidently misaligned answer is the worst thing this could produce,
     * because nobody would think to check it.
     *
     * @param  array<int, ProductDocumentCandidate>  $candidates
     * @param  array<int, mixed>  $rows
     * @return array<int, DocumentKindGuess>
     */
    private function reconcile(array $candidates, array $rows): array
    {
        $byIndex = collect($rows)
            ->filter(fn (mixed $row): bool => is_array($row) && isset($row['index']))
            ->keyBy(fn (array $row): int => (int) $row['index']);

        return collect($candidates)
            ->map(function (ProductDocumentCandidate $candidate, int $index) use ($byIndex): DocumentKindGuess {
                $row = $byIndex->get($index);

                if (! is_array($row)) {
                    return DocumentKindGuess::none();
                }

                /**
                 * A kind outside the enum -- the sentinel, or something the
                 * model made up -- reads as no guess. tryFrom is the last
                 * word on what may reach the page, whatever the schema said.
                 */
                $type = ProductDocumentType::tryFrom((string) ($row['type'] ?? ''));

                if ($type === null) {
                    return DocumentKindGuess::none();
                }

                return new DocumentKindGuess(
                    type: $type,
                    confidence: GuessConfidence::tryFrom((string) ($row['confidence'] ?? '')) ?? GuessConfidence::Low,
                );
            })
            ->all();
    }
}
