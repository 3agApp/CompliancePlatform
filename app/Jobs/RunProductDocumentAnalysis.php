<?php

namespace App\Jobs;

use App\Actions\Products\AnalyzeProductDocument;
use App\Actions\Products\AssessProductDocuments;
use App\Models\ProductDocumentAnalysis;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Traits\Localizable;
use Throwable;

/**
 * Read one paper on the queue.
 *
 * Tried once. A second attempt would bill the organization twice for an
 * answer a reviewer can ask for again with one click.
 */
class RunProductDocumentAnalysis implements ShouldQueue
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
    public function __construct(public ProductDocumentAnalysis $analysis)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(AnalyzeProductDocument $analyze): void
    {
        $this->withLocale(
            $this->analysis->organization->locale->value,
            fn () => $analyze->run($this->analysis),
        );
    }

    /**
     * Handle a job failure.
     *
     * The worker was killed or the job timed out before the action could say
     * so itself. Without this the run would read as running forever and no
     * other run could be started for the paper.
     */
    public function failed(?Throwable $exception): void
    {
        $analysis = $this->analysis->fresh();

        if ($analysis === null || ! $analysis->status->isPending()) {
            return;
        }

        $this->withLocale(
            $analysis->organization->locale->value,
            fn () => app(AnalyzeProductDocument::class)->fail($analysis, __('The analysis took too long and was stopped. Try again.')),
        );
    }
}
