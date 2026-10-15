<?php

namespace App\Actions\Imports;

use App\Enums\ImportRunStatus;
use App\Jobs\ImportProductPage;
use App\Models\ImportRun;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Put an import from the organization's source system on the queue.
 */
class StartProductImport
{
    /**
     * How long a run may go without progress before it is given up on.
     *
     * A run older than this was lost -- a worker that died, a queue nobody
     * was running -- and left as it is, it would refuse every run after it.
     */
    public const int STALE_AFTER_MINUTES = 30;

    /**
     * @throws ValidationException when there is nothing to import from, or an import is already going.
     */
    public function handle(Organization $organization, User $actor): ImportRun
    {
        $source = $organization->importSource;

        if ($source === null) {
            throw ValidationException::withMessages(['import' => __('Connect a source before importing.')]);
        }

        if (! $source->manufacturers()->exists()) {
            throw ValidationException::withMessages(['import' => __('Choose at least one manufacturer to import.')]);
        }

        $run = DB::transaction(function () use ($organization, $actor): ?ImportRun {
            /**
             * Locked so two people pressing the button together start one
             * import between them rather than two racing each other.
             */
            Organization::query()->whereKey($organization->id)->lockForUpdate()->first();

            $organization->importRuns()
                ->pending()
                ->where('updated_at', '<', now()->subMinutes(self::STALE_AFTER_MINUTES))
                ->get()
                ->each(fn (ImportRun $stale) => $stale->forceFill([
                    'status' => ImportRunStatus::Failed,
                    'failure_reason' => __('The import stopped making progress and was given up on.'),
                    'finished_at' => now(),
                ])->save());

            if ($organization->importRuns()->pending()->exists()) {
                return null;
            }

            $run = new ImportRun;
            $run->forceFill([
                'organization_id' => $organization->id,
                'triggered_by' => $actor->id,
                'status' => ImportRunStatus::Queued,
            ])->save();

            return $run;
        });

        if ($run === null) {
            throw ValidationException::withMessages(['import' => __('An import is already running.')]);
        }

        ImportProductPage::dispatch($run);

        return $run;
    }
}
