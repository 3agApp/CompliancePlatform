<?php

namespace App\Actions\Products;

use App\Ai\Agents\SingleDocumentAnalysisAgent;
use App\Ai\OrganizationProvider;
use App\Enums\AssessmentOverall;
use App\Enums\AssessmentStatus;
use App\Enums\FindingCategory;
use App\Enums\FindingSeverity;
use App\Enums\ProductDocumentType;
use App\Enums\ProductEventType;
use App\Jobs\RunProductDocumentAnalysis;
use App\Models\Organization;
use App\Models\ProductDocument;
use App\Models\ProductDocumentAnalysis;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Have an organization's own AI provider read one paper and say what it is,
 * what it covers and where it falls short.
 *
 * Advisory from end to end, like AssessProductDocuments: it writes a run, its
 * findings and a line of history, and never touches the product's review
 * status or its seal.
 */
class AnalyzeProductDocument
{
    /**
     * Why a run could not be started, beyond the organization-wide reasons
     * AssessProductDocuments gives.
     */
    public const string UNSUPPORTED = 'unsupported_type';

    public const string TOO_LARGE = 'over_size_budget';

    public const string MISSING = 'missing_file';

    public const string ALREADY_RUNNING = 'already_running';

    public function __construct(private OrganizationProvider $provider)
    {
        //
    }

    /**
     * Determine if the provider can be sent this paper at all: a PDF or an
     * image, within the size a request can carry.
     *
     * Says nothing about the organization -- that is
     * AssessProductDocuments::unavailableReason().
     */
    public static function canRead(ProductDocument $document): bool
    {
        return AssessProductDocuments::attachmentFor($document) !== null
            && $document->size <= AssessProductDocuments::SIZE_BUDGET;
    }

    /**
     * Ask for a run, and put it on the queue.
     *
     * @throws ValidationException when the paper cannot be read, the organization cannot run one, or one is already going.
     */
    public function start(ProductDocument $document, User $actor, Organization $organization): ProductDocumentAnalysis
    {
        $reason = AssessProductDocuments::unavailableReason($organization) ?? $this->unreadableReason($document);

        if ($reason !== null) {
            throw ValidationException::withMessages(['analysis' => $this->messageFor($reason)]);
        }

        $setting = $organization->aiSetting;

        $analysis = DB::transaction(function () use ($document, $actor, $organization, $setting): ?ProductDocumentAnalysis {
            /**
             * Locked so two reviewers pressing the button together get one
             * run between them rather than two bills.
             */
            ProductDocument::query()->whereKey($document->id)->lockForUpdate()->first();

            $document->analyses()
                ->whereIn('status', [AssessmentStatus::Queued, AssessmentStatus::Running])
                ->where('created_at', '<', now()->subMinutes(AssessProductDocuments::STALE_AFTER_MINUTES))
                ->get()
                ->each(fn (ProductDocumentAnalysis $stale) => $this->fail($stale, __('The analysis never finished and was given up on. Try again.')));

            $pending = $document->analyses()
                ->whereIn('status', [AssessmentStatus::Queued, AssessmentStatus::Running])
                ->exists();

            if ($pending) {
                return null;
            }

            $analysis = $document->analyses()->create([
                'organization_id' => $organization->id,
                'requested_by' => $actor->id,
                'status' => AssessmentStatus::Queued,
                'provider' => $setting->provider,
                'model' => $setting->model,
                'prompt_version' => SingleDocumentAnalysisAgent::PROMPT_VERSION,
            ]);

            $document->product->recordEvent(ProductEventType::DocumentAnalysisRequested, $actor, $organization, note: $document->name);

            return $analysis;
        });

        if ($analysis === null) {
            throw ValidationException::withMessages(['analysis' => $this->messageFor(self::ALREADY_RUNNING)]);
        }

        RunProductDocumentAnalysis::dispatch($analysis);

        return $analysis;
    }

    /**
     * Read the paper and write down what was found.
     *
     * Every path ends with the run completed or failed. Nothing here throws:
     * a run left "running" forever would block every run after it.
     */
    public function run(ProductDocumentAnalysis $analysis): void
    {
        if ($analysis->status !== AssessmentStatus::Queued) {
            return;
        }

        $analysis->forceFill(['status' => AssessmentStatus::Running, 'started_at' => now()])->save();

        $organization = $analysis->organization;
        $document = $analysis->document;
        $product = $document->product()->with(['brand', 'category', 'template'])->firstOrFail();
        $setting = $organization->aiSetting;

        /**
         * Checked again here as well as when the run was asked for: the
         * organization may have withdrawn its agreement or its key, or the
         * file may have gone, while the run sat on the queue.
         */
        $reason = AssessProductDocuments::unavailableReason($organization) ?? $this->unreadableReason($document);

        if ($setting === null || $reason !== null) {
            $this->fail($analysis, $this->messageFor($reason ?? AssessProductDocuments::NOT_CONFIGURED));

            return;
        }

        $attachment = AssessProductDocuments::attachmentFor($document);

        $prompt = "Product details:\n\n".AssessProductDocuments::productDetailsFor($product)
            ."\n\nThe attached document is \"{$document->name}\", filed as: {$document->type->value}."
            ."\n\nAnalyze this document.";

        try {
            $response = $this->provider->using(
                $setting,
                fn (string $provider): mixed => (new SingleDocumentAnalysisAgent(AssessProductDocuments::languageFor($organization)))->prompt(
                    $prompt,
                    attachments: [$attachment],
                    provider: $provider,
                    model: $setting->model,
                    timeout: AssessProductDocuments::TIMEOUT,
                ),
            );
        } catch (DecryptException) {
            $this->fail($analysis, $this->messageFor(AssessProductDocuments::NOT_CONFIGURED));

            return;
        } catch (Throwable $exception) {
            report($exception);

            $this->fail($analysis, __('The AI provider did not answer. Try again in a few minutes.'));

            return;
        }

        $this->complete($analysis, $document, $response);
    }

    /**
     * Mark the run as failed, with a reason a reviewer can act on.
     */
    public function fail(ProductDocumentAnalysis $analysis, string $reason): void
    {
        $analysis->forceFill([
            'status' => AssessmentStatus::Failed,
            'failure_reason' => Str::limit($reason, 250),
            'completed_at' => now(),
        ])->save();
    }

    /**
     * Write the answer down.
     */
    private function complete(ProductDocumentAnalysis $analysis, ProductDocument $document, mixed $response): void
    {
        $overall = AssessmentOverall::tryFrom((string) ($response['overall'] ?? ''));
        $rows = $response['findings'] ?? [];

        DB::transaction(function () use ($analysis, $document, $overall, $rows, $response) {
            $position = 0;

            foreach (is_array($rows) ? $rows : [] as $row) {
                $finding = $this->toFinding($row);

                if ($finding === null) {
                    continue;
                }

                $analysis->findings()->create([...$finding, 'position' => $position++]);
            }

            $analysis->forceFill([
                'status' => AssessmentStatus::Completed,
                'overall' => $overall ?? ($position > 0 ? AssessmentOverall::GapsFound : AssessmentOverall::InsufficientDocuments),
                'summary' => AssessProductDocuments::text($response['summary'] ?? null),
                'facts' => $this->toFacts($response['facts'] ?? null, $document),
                'completed_at' => now(),
            ])->save();

            $document->product->recordEvent(
                ProductEventType::DocumentAnalysisCompleted,
                $analysis->requester,
                $analysis->organization,
                note: "{$document->name}: {$analysis->overall?->label()}",
            );
        });
    }

    /**
     * Turn one row of the answer into a finding, or null if it is not one.
     *
     * Enums outside their cases are dropped rather than guessed at.
     *
     * @return array<string, mixed>|null
     */
    private function toFinding(mixed $row): ?array
    {
        if (! is_array($row)) {
            return null;
        }

        $severity = FindingSeverity::tryFrom((string) ($row['severity'] ?? ''));
        $category = FindingCategory::tryFrom((string) ($row['category'] ?? ''));
        $requirement = AssessProductDocuments::text($row['requirement'] ?? null);
        $rationale = AssessProductDocuments::text($row['rationale'] ?? null);

        if ($severity === null || $category === null || $requirement === null || $rationale === null) {
            return null;
        }

        return [
            'severity' => $severity,
            'category' => $category,
            'requirement' => Str::limit($requirement, 250),
            'rationale' => $rationale,
            'evidence' => AssessProductDocuments::text($row['evidence'] ?? null),
            'ask_manufacturer' => AssessProductDocuments::text($row['ask_manufacturer'] ?? null),
        ];
    }

    /**
     * Keep only the facts that hold up: real dates, known document types,
     * short strings.
     *
     * A suggested type is only kept when the model says the filed one is
     * wrong; one that is the filed type after all is read as a match.
     *
     * @return array{issuer: string|null, document_number: string|null, issued_on: string|null, expires_on: string|null, standards: array<int, string>, covered_product: string|null, type_matches: bool, suggested_type: string|null}|null
     */
    private function toFacts(mixed $facts, ProductDocument $document): ?array
    {
        if (! is_array($facts)) {
            return null;
        }

        $typeMatches = ($facts['type_matches'] ?? true) !== false;
        $suggested = ProductDocumentType::tryFrom((string) ($facts['suggested_type'] ?? ''));

        if ($suggested === $document->type) {
            $typeMatches = true;
        }

        if ($typeMatches) {
            $suggested = null;
        }

        $standards = collect(is_array($facts['standards'] ?? null) ? $facts['standards'] : [])
            ->map(fn (mixed $standard): ?string => AssessProductDocuments::text($standard))
            ->filter()
            ->map(fn (string $standard): string => Str::limit($standard, 120))
            ->unique()
            ->take(30)
            ->values()
            ->all();

        return [
            'issuer' => $this->short($facts['issuer'] ?? null),
            'document_number' => $this->short($facts['document_number'] ?? null),
            'issued_on' => $this->date($facts['issued_on'] ?? null),
            'expires_on' => $this->date($facts['expires_on'] ?? null),
            'standards' => $standards,
            'covered_product' => $this->short($facts['covered_product'] ?? null),
            'type_matches' => $typeMatches,
            'suggested_type' => $suggested?->value,
        ];
    }

    /**
     * Get the reason this paper cannot be sent, or null if it can.
     */
    private function unreadableReason(ProductDocument $document): ?string
    {
        return match (true) {
            AssessProductDocuments::attachmentFor($document) === null => self::UNSUPPORTED,
            $document->size > AssessProductDocuments::SIZE_BUDGET => self::TOO_LARGE,
            ! Storage::disk(ProductDocument::DISK)->exists($document->path) => self::MISSING,
            default => null,
        };
    }

    /**
     * Get the message shown for a reason a run could not happen, falling
     * back on the whole-product check for the organization-wide ones.
     */
    private function messageFor(string $reason): string
    {
        return match ($reason) {
            self::UNSUPPORTED => __('Only PDFs and images can be analyzed.'),
            self::TOO_LARGE => __('This file is too large to analyze.'),
            self::MISSING => __('The file could not be found.'),
            self::ALREADY_RUNNING => __('An analysis is already running for this document.'),
            default => AssessProductDocuments::messageFor($reason),
        };
    }

    /**
     * Get a date the answer gave as YYYY-MM-DD, or null if it is not one.
     */
    private function date(mixed $value): ?string
    {
        $value = AssessProductDocuments::text($value);

        if ($value === null || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        $date = CarbonImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== null && $date->format('Y-m-d') === $value ? $value : null;
    }

    /**
     * Get a trimmed string, cut to fit a line of the facts grid.
     */
    private function short(mixed $value): ?string
    {
        $value = AssessProductDocuments::text($value);

        return $value === null ? null : Str::limit($value, 250);
    }
}
