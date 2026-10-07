<?php

namespace App\Jobs;

use App\Actions\Products\AssessProductDocuments;
use App\Models\ProductAssessment;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Traits\Localizable;
use Throwable;

/**
 * Read one product's papers on the queue.
 *
 * Tried once. A second attempt would bill the organization twice for an
 * answer a reviewer can ask for again with one click.
 */
class RunProductAssessment implements ShouldQueue
{
    use Localizable, Queueable;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 1;

    /**
     * The number of seconds the job can run before timing out.
     *
     * A little longer than the provider is given, so the action's own
     * timeout is the one that fires and the run is marked failed properly.
     */
    public int $timeout = AssessProductDocuments::TIMEOUT + 30;

    /**
     * Create a new job instance.
     */
    public function __construct(public ProductAssessment $assessment)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(AssessProductDocuments $assess): void
    {
        /**
         * In the organization's language, because a worker has none of its
         * own: the failure reason and the line in the product's history are
         * written here and read by the organization's reviewers.
         */
        $this->withLocale(
            $this->assessment->organization->locale->value,
            fn () => $assess->run($this->assessment),
        );
    }

    /**
     * Handle a job failure.
     *
     * The worker was killed or the job timed out before the action could say
     * so itself. Without this the run would read as running forever and no
     * other run could be started.
     */
    public function failed(?Throwable $exception): void
    {
        $assessment = $this->assessment->fresh();

        if ($assessment === null || ! $assessment->status->isPending()) {
            return;
        }

        $this->withLocale(
            $assessment->organization->locale->value,
            fn () => app(AssessProductDocuments::class)->fail($assessment, __('The check took too long and was stopped. Try again.')),
        );
    }
}
