<?php

namespace App\Jobs;

use App\Actions\Imports\ImportRowException;
use App\Actions\Imports\SuggestImportTemplates;
use App\Actions\Imports\UpsertImportedProduct;
use App\Data\ImportArticle;
use App\Enums\ImportOutcome;
use App\Enums\ImportRunStatus;
use App\Imports\ImportClientFactory;
use App\Imports\ImportRequestException;
use App\Models\ImportManufacturer;
use App\Models\ImportRun;
use App\Models\ImportSource;
use App\Models\ProductTemplate;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Traits\Localizable;
use Throwable;

/**
 * Import one page of the source's catalogue, then queue the next.
 *
 * A page per job keeps each one short, so a catalogue of thousands never
 * holds a worker past its timeout, and a crash costs one page rather than
 * the run. The run ends on the first empty page.
 */
class ImportProductPage implements ShouldQueue
{
    use Localizable, Queueable;

    /**
     * How many articles one page asks for.
     */
    public const int PAGE_SIZE = 200;

    /**
     * The most pages one run reads, as a brake on a pager that never moves
     * on. Far past any real catalogue.
     */
    public const int MAX_PAGES = 1000;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 1;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 600;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public ImportRun $run,
        public int $skip = 0,
        public int $page = 0,
    ) {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(ImportClientFactory $clients, UpsertImportedProduct $import, SuggestImportTemplates $suggest): void
    {
        $this->withLocale($this->run->organization->locale->value, function () use ($clients, $import, $suggest) {
            $source = $this->run->organization->importSource;

            if (! $source instanceof ImportSource) {
                $this->finish(ImportRunStatus::Failed, __('The import source was removed during the import.'));

                return;
            }

            if ($this->page >= self::MAX_PAGES) {
                $this->finish(ImportRunStatus::Failed, __('The source kept sending pages past any sensible end, so the import was stopped.'));

                return;
            }

            if ($this->run->status === ImportRunStatus::Queued) {
                $this->run->forceFill(['status' => ImportRunStatus::Running, 'started_at' => now()])->save();
            }

            $manufacturers = $source->manufacturers()->with(['supplierConnection', 'template'])->get()->keyBy('external_id');

            $page = $clients->for($source)->articles(array_values($manufacturers->keys()->map(fn (mixed $id): string => (string) $id)->all()), $this->skip, self::PAGE_SIZE);

            if ($page->isEmpty()) {
                $this->finish(ImportRunStatus::Completed);

                return;
            }

            $this->importPage($source, $manufacturers, $page->articles, $page->unusableRows(), $page->total, $import, $suggest);

            self::dispatch($this->run, $this->skip + $page->rowCount, $this->page + 1);
        });
    }

    /**
     * Import the page's articles one by one and add up what came of them.
     *
     * @param  Collection<string, ImportManufacturer>  $manufacturers
     * @param  list<ImportArticle>  $articles
     */
    private function importPage(
        ImportSource $source,
        Collection $manufacturers,
        array $articles,
        int $unusableRows,
        ?int $total,
        UpsertImportedProduct $import,
        SuggestImportTemplates $suggest,
    ): void {
        /**
         * Counted on a copy and added to the stored run under a lock, so an
         * image job noting a failure on the same run is never written over.
         */
        $tally = new ImportRun;
        $tally->forceFill(['created' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => $unusableRows, 'ai_assigned' => 0, 'failures' => []]);

        if ($unusableRows > 0) {
            $tally->recordFailure('—', trans_choice('{1} One row had no article number and was left out.|[2,*] :count rows had no article number and were left out.', $unusableRows));
        }

        $suggestions = $this->suggestTemplates($source, $manufacturers, $articles, $import, $suggest);

        foreach ($articles as $article) {
            $manufacturer = $manufacturers->get((string) $article->manufacturerId);

            if (! $manufacturer instanceof ImportManufacturer) {
                $tally->failed++;
                $tally->recordFailure($article->sku, __('The article has no manufacturer that was chosen for import.'));

                continue;
            }

            try {
                $outcome = DB::transaction(fn (): ImportOutcome => $import->handle(
                    $this->run,
                    $source,
                    $manufacturer,
                    $article,
                    $suggestions[$article->sku] ?? null,
                ));
            } catch (ImportRowException $exception) {
                $tally->failed++;
                $tally->recordFailure($article->sku, $exception->getMessage());

                continue;
            } catch (Throwable $exception) {
                report($exception);

                $tally->failed++;
                $tally->recordFailure($article->sku, __('The article could not be saved.'));

                continue;
            }

            match ($outcome) {
                ImportOutcome::Created => $tally->created++,
                ImportOutcome::CreatedByAi => [$tally->created++, $tally->ai_assigned++],
                ImportOutcome::Updated => $tally->updated++,
                ImportOutcome::Skipped => $tally->skipped++,
            };
        }

        $this->addToRun($tally, count($articles) + $unusableRows, $total);
    }

    /**
     * Ask the AI about the articles that will be new and that nothing else
     * says the template of.
     *
     * @param  Collection<string, ImportManufacturer>  $manufacturers
     * @param  list<ImportArticle>  $articles
     * @return array<string, ProductTemplate>
     */
    private function suggestTemplates(
        ImportSource $source,
        Collection $manufacturers,
        array $articles,
        UpsertImportedProduct $import,
        SuggestImportTemplates $suggest,
    ): array {
        if (! $source->use_ai_for_templates || $source->organization->aiSetting === null) {
            return [];
        }

        $needing = array_values(array_filter($articles, function (ImportArticle $article) use ($source, $manufacturers, $import): bool {
            $manufacturer = $manufacturers->get((string) $article->manufacturerId);

            if (! $manufacturer instanceof ImportManufacturer || $manufacturer->product_template_id !== null) {
                return false;
            }

            try {
                return $import->existingProduct($source, $article) === null;
            } catch (ImportRowException) {
                return false;
            }
        }));

        if ($needing === []) {
            return [];
        }

        $templates = $source->organization->productTemplates()->with('category')->get();

        return $suggest->handle($source->organization, $templates, $needing);
    }

    /**
     * Add one page's tally to the stored run.
     */
    private function addToRun(ImportRun $tally, int $processed, ?int $total): void
    {
        DB::transaction(function () use ($tally, $processed, $total) {
            $run = ImportRun::query()->lockForUpdate()->findOrFail($this->run->id);

            $failures = $run->failures ?? [];

            foreach ($tally->failures ?? [] as $failure) {
                if (count($failures) >= ImportRun::MAX_FAILURES) {
                    break;
                }

                $failures[] = $failure;
            }

            $run->forceFill([
                'total' => $run->total ?? $total,
                'processed' => $run->processed + $processed,
                'created' => $run->created + $tally->created,
                'updated' => $run->updated + $tally->updated,
                'skipped' => $run->skipped + $tally->skipped,
                'failed' => $run->failed + $tally->failed,
                'ai_assigned' => $run->ai_assigned + $tally->ai_assigned,
                'failures' => $failures,
            ])->save();
        });
    }

    /**
     * Close the run.
     */
    private function finish(ImportRunStatus $status, ?string $reason = null): void
    {
        ImportRun::query()->whereKey($this->run->id)->first()?->forceFill([
            'status' => $status,
            'failure_reason' => $reason,
            'started_at' => $this->run->started_at ?? now(),
            'finished_at' => now(),
        ])->save();
    }

    /**
     * Handle a job failure.
     *
     * The source refused, or the worker died, partway through. What was
     * imported stays imported; the run says it did not finish, so another
     * can be started.
     */
    public function failed(?Throwable $exception): void
    {
        $run = $this->run->fresh();

        if ($run === null || ! $run->status->isPending()) {
            return;
        }

        $this->withLocale($run->organization->locale->value, fn () => $run->forceFill([
            'status' => ImportRunStatus::Failed,
            'failure_reason' => $exception instanceof ImportRequestException
                ? $exception->getMessage()
                : __('The import stopped unexpectedly. Try again.'),
            'finished_at' => now(),
        ])->save());
    }
}
