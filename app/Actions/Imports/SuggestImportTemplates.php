<?php

namespace App\Actions\Imports;

use App\Ai\Agents\ProductTemplateAgent;
use App\Ai\OrganizationProvider;
use App\Data\ImportArticle;
use App\Models\Organization;
use App\Models\ProductTemplate;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Throwable;

/**
 * Ask an organization's own AI provider which template each new article
 * belongs under.
 *
 * Nothing here throws and nothing here may stop an import: an article the
 * AI cannot place falls through to the source's default template, and a
 * provider that is down only means more of them do.
 */
class SuggestImportTemplates
{
    /**
     * How many articles go in one prompt.
     */
    public const int BATCH_SIZE = 25;

    /**
     * How long to wait on the provider, in seconds.
     */
    private const int TIMEOUT = 60;

    /**
     * How much of a description is sent. The first lines say what a thing
     * is; the rest is care instructions and marketing.
     */
    private const int DESCRIPTION_LIMIT = 400;

    public function __construct(private OrganizationProvider $provider)
    {
        //
    }

    /**
     * @param  Collection<int, ProductTemplate>  $templates  The organization's templates, with their categories.
     * @param  list<ImportArticle>  $articles
     * @return array<string, ProductTemplate> The chosen template, keyed by article number.
     */
    public function handle(Organization $organization, Collection $templates, array $articles): array
    {
        $setting = $organization->aiSetting;

        if ($setting === null || $templates->isEmpty() || $articles === []) {
            return [];
        }

        $byId = $templates->keyBy('id');
        $chosen = [];

        foreach (array_chunk($articles, self::BATCH_SIZE) as $batch) {
            try {
                $response = $this->provider->using(
                    $setting,
                    fn (string $provider): mixed => (new ProductTemplateAgent(array_values($byId->keys()->map(fn (mixed $id): int => (int) $id)->all())))->prompt(
                        $this->promptFor($templates, $batch),
                        provider: $provider,
                        model: $setting->model,
                        timeout: self::TIMEOUT,
                    ),
                );
            } catch (DecryptException) {
                return $chosen;
            } catch (Throwable $exception) {
                report($exception);

                continue;
            }

            foreach ($response['choices'] ?? [] as $row) {
                if (! is_array($row) || ! isset($row['index'], $row['template'])) {
                    continue;
                }

                $article = $batch[(int) $row['index']] ?? null;
                $template = $byId->get((int) $row['template']);

                /**
                 * The sentinel, an id from somewhere else, or an index for
                 * an article never asked about: all read as no answer.
                 */
                if ($article === null || ! $template instanceof ProductTemplate || ! ctype_digit((string) $row['template'])) {
                    continue;
                }

                $chosen[$article->sku] = $template;
            }
        }

        return $chosen;
    }

    /**
     * Render the templates and the articles as the instructions describe.
     *
     * @param  Collection<int, ProductTemplate>  $templates
     * @param  list<ImportArticle>  $articles
     */
    private function promptFor(Collection $templates, array $articles): string
    {
        $templateLines = $templates
            ->map(fn (ProductTemplate $template): string => sprintf(
                '- id: %d | family: %s | template: %s',
                $template->id,
                $template->category->name,
                $template->name,
            ))
            ->implode("\n");

        $articleLines = collect($articles)
            ->map(fn (ImportArticle $article, int $index): string => sprintf(
                '%d. name: %s | manufacturer: %s | description: %s',
                $index,
                self::oneLine($article->name),
                self::oneLine($article->manufacturerName ?? ''),
                self::oneLine(Str::limit($article->shortDescription ?? $article->longDescription ?? '', self::DESCRIPTION_LIMIT)),
            ))
            ->implode("\n");

        return "Templates:\n\n{$templateLines}\n\nArticles:\n\n{$articleLines}";
    }

    /**
     * Fold a value onto one line, so it cannot pass itself off as the next
     * entry in the list.
     */
    private static function oneLine(string $value): string
    {
        return trim((string) preg_replace('/\s+/', ' ', $value));
    }
}
