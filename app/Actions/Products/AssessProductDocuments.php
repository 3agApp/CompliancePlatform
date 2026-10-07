<?php

namespace App\Actions\Products;

use App\Ai\Agents\DocumentAssessmentAgent;
use App\Ai\OrganizationProvider;
use App\Enums\AssessmentOverall;
use App\Enums\AssessmentStatus;
use App\Enums\FindingCategory;
use App\Enums\FindingSeverity;
use App\Enums\Locale;
use App\Enums\ProductEventType;
use App\Jobs\RunProductAssessment;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductAssessment;
use App\Models\ProductAssessmentFinding;
use App\Models\ProductDocument;
use App\Models\User;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Ai\Files\Document;
use Laravel\Ai\Files\Image;
use Throwable;

/**
 * Have an organization's own AI provider read a product's papers and say
 * where they fall short.
 *
 * Advisory from end to end. This writes runs and findings and a line of
 * history, and nothing else: it never touches the product's review status or
 * its seal, and it never calls ReviewProduct. Whatever the run concludes, a
 * person still makes the move.
 */
class AssessProductDocuments
{
    /**
     * How long to wait on the provider, in seconds.
     *
     * Long, because the model is reading every page of every paper, and
     * nobody is sitting watching a spinner: the run happens on the queue.
     */
    public const int TIMEOUT = 300;

    /**
     * The most file content one run sends, in bytes.
     *
     * Providers cap a request's inline content at around twenty megabytes,
     * and files travel base64-encoded, which adds a third. Papers past the
     * budget are listed as skipped rather than failing the whole run, so a
     * reviewer can see what was not read.
     */
    public const int SIZE_BUDGET = 14 * 1024 * 1024;

    /**
     * How long a run may sit queued or running before it is given up on.
     *
     * Well past the job's own timeout. A run older than this was lost -- a
     * worker that died, a queue nobody was running -- and left as it is, it
     * would refuse every run after it with "already running".
     */
    public const int STALE_AFTER_MINUTES = 15;

    /**
     * Why a document was not sent.
     */
    public const string SKIPPED_UNSUPPORTED = 'unsupported_type';

    public const string SKIPPED_OVER_BUDGET = 'over_size_budget';

    public const string SKIPPED_MISSING = 'missing_file';

    /**
     * Why a run could not be started.
     */
    public const string NOT_CONFIGURED = 'not_configured';

    public const string ANALYSIS_DISABLED = 'analysis_disabled';

    public const string ALREADY_RUNNING = 'already_running';

    public function __construct(private OrganizationProvider $provider)
    {
        //
    }

    /**
     * Get the reason a run cannot be started for this organization, or null
     * if one can.
     */
    public static function unavailableReason(Organization $organization): ?string
    {
        $setting = $organization->aiSetting;

        if ($setting === null) {
            return self::NOT_CONFIGURED;
        }

        if (! $setting->allow_document_analysis) {
            return self::ANALYSIS_DISABLED;
        }

        return null;
    }

    /**
     * Ask for a run, and put it on the queue.
     *
     * @throws ValidationException when the organization cannot run one, or one is already going.
     */
    public function start(Product $product, User $actor, Organization $organization): ProductAssessment
    {
        $reason = self::unavailableReason($organization);

        if ($reason !== null) {
            throw ValidationException::withMessages(['assessment' => $this->messageFor($reason)]);
        }

        $setting = $organization->aiSetting;

        $assessment = DB::transaction(function () use ($product, $actor, $organization, $setting): ?ProductAssessment {
            /**
             * Locked so two reviewers pressing the button together get one
             * run between them rather than two bills.
             */
            Product::query()->whereKey($product->id)->lockForUpdate()->first();

            $product->assessments()
                ->whereIn('status', [AssessmentStatus::Queued, AssessmentStatus::Running])
                ->where('created_at', '<', now()->subMinutes(self::STALE_AFTER_MINUTES))
                ->get()
                ->each(fn (ProductAssessment $stale) => $this->fail($stale, __('The check never finished and was given up on. Try again.')));

            $pending = $product->assessments()
                ->whereIn('status', [AssessmentStatus::Queued, AssessmentStatus::Running])
                ->exists();

            if ($pending) {
                return null;
            }

            /**
             * The run this one is read against: the last one that finished.
             * A failed run found nothing, so there is nothing to compare.
             */
            $previous = $product->assessments()
                ->where('status', AssessmentStatus::Completed)
                ->value('id');

            $assessment = $product->assessments()->create([
                'organization_id' => $organization->id,
                'requested_by' => $actor->id,
                'previous_assessment_id' => $previous,
                'status' => AssessmentStatus::Queued,
                'provider' => $setting->provider,
                'model' => $setting->model,
                'prompt_version' => DocumentAssessmentAgent::PROMPT_VERSION,
                'documents' => [],
                'skipped_documents' => [],
            ]);

            $product->recordEvent(ProductEventType::AssessmentRequested, $actor, $organization);

            return $assessment;
        });

        if ($assessment === null) {
            throw ValidationException::withMessages(['assessment' => $this->messageFor(self::ALREADY_RUNNING)]);
        }

        RunProductAssessment::dispatch($assessment);

        return $assessment;
    }

    /**
     * Read the papers and write down what was found.
     *
     * Every path ends with the run completed or failed. Nothing here throws:
     * a run left "running" forever would block every run after it.
     */
    public function run(ProductAssessment $assessment): void
    {
        if ($assessment->status !== AssessmentStatus::Queued) {
            return;
        }

        $assessment->forceFill(['status' => AssessmentStatus::Running, 'started_at' => now()])->save();

        $organization = $assessment->organization;
        $product = $assessment->product()->with(['documents', 'brand', 'category', 'template'])->firstOrFail();
        $setting = $organization->aiSetting;

        /**
         * Checked again here as well as when the run was asked for: the
         * organization may have withdrawn its agreement, or its key, while
         * the run sat on the queue.
         */
        $reason = self::unavailableReason($organization);

        if ($setting === null || $reason !== null) {
            $this->fail($assessment, $this->messageFor($reason ?? self::NOT_CONFIGURED));

            return;
        }

        [$sent, $attachments, $skipped] = $this->attachmentsFor($product);

        $previous = $assessment->previousAssessment?->findings()->get()->values()->all() ?? [];

        $assessment->forceFill([
            'documents' => $sent,
            'skipped_documents' => $skipped,
        ])->save();

        try {
            $response = $this->provider->using(
                $setting,
                fn (string $provider): mixed => (new DocumentAssessmentAgent($this->languageFor($organization)))->prompt(
                    $this->promptFor($product, $sent, $skipped, $assessment->previousAssessment, $previous),
                    attachments: $attachments,
                    provider: $provider,
                    model: $setting->model,
                    timeout: self::TIMEOUT,
                ),
            );
        } catch (DecryptException) {
            $this->fail($assessment, $this->messageFor(self::NOT_CONFIGURED));

            return;
        } catch (Throwable $exception) {
            report($exception);

            $this->fail($assessment, __('The AI provider did not answer. Try again in a few minutes.'));

            return;
        }

        $this->complete($assessment, $product, $sent, $previous, $response);
    }

    /**
     * Mark the run as failed, with a reason a reviewer can act on.
     */
    public function fail(ProductAssessment $assessment, string $reason): void
    {
        $assessment->forceFill([
            'status' => AssessmentStatus::Failed,
            'failure_reason' => Str::limit($reason, 250),
            'completed_at' => now(),
        ])->save();
    }

    /**
     * Write the answer down, lined up against the papers it was about.
     *
     * @param  array<int, array{id: int, name: string, type: string, mime_type: string, size: int}>  $sent
     * @param  array<int, ProductAssessmentFinding>  $previous
     */
    private function complete(ProductAssessment $assessment, Product $product, array $sent, array $previous, mixed $response): void
    {
        $overall = AssessmentOverall::tryFrom((string) ($response['overall'] ?? ''));
        $rows = $response['findings'] ?? [];

        DB::transaction(function () use ($assessment, $product, $sent, $previous, $overall, $rows, $response) {
            $position = 0;

            foreach (is_array($rows) ? $rows : [] as $row) {
                $finding = $this->toFinding($row, $sent, $previous);

                if ($finding === null) {
                    continue;
                }

                $assessment->findings()->create([...$finding, 'position' => $position++]);
            }

            $assessment->forceFill([
                'status' => AssessmentStatus::Completed,
                'overall' => $overall ?? ($position > 0 ? AssessmentOverall::GapsFound : AssessmentOverall::InsufficientDocuments),
                'summary' => $this->text($response['summary'] ?? null),
                'factory_request' => $this->text($response['factory_request'] ?? null),
                'completed_at' => now(),
            ])->save();

            /**
             * The history says that a run finished and what it concluded,
             * and nothing more: the findings live with the run.
             */
            $product->recordEvent(
                ProductEventType::AssessmentCompleted,
                $assessment->requester,
                $assessment->organization,
                note: $assessment->overall?->label(),
            );
        });
    }

    /**
     * Turn one row of the answer into a finding, or null if it is not one.
     *
     * Enums outside their cases are dropped rather than guessed at, and a
     * document number that is not on the list is read as "the product as a
     * whole" rather than pinned to whichever paper happens to have it.
     *
     * The same goes for the earlier finding it says it carries on: a number
     * that is not on the earlier list makes it a new finding, never a link to
     * some other gap.
     *
     * @param  array<int, array{id: int, name: string, type: string, mime_type: string, size: int}>  $sent
     * @param  array<int, ProductAssessmentFinding>  $previous
     * @return array<string, mixed>|null
     */
    private function toFinding(mixed $row, array $sent, array $previous): ?array
    {
        if (! is_array($row)) {
            return null;
        }

        $severity = FindingSeverity::tryFrom((string) ($row['severity'] ?? ''));
        $category = FindingCategory::tryFrom((string) ($row['category'] ?? ''));
        $requirement = $this->text($row['requirement'] ?? null);
        $rationale = $this->text($row['rationale'] ?? null);

        if ($severity === null || $category === null || $requirement === null || $rationale === null) {
            return null;
        }

        $index = filter_var($row['document_index'] ?? null, FILTER_VALIDATE_INT);
        $document = $index !== false ? ($sent[$index] ?? null) : null;

        $previousIndex = filter_var($row['previous_finding'] ?? null, FILTER_VALIDATE_INT);
        $previousFinding = $previousIndex !== false ? ($previous[$previousIndex] ?? null) : null;

        return [
            'product_document_id' => $document['id'] ?? null,
            'previous_finding_id' => $previousFinding?->id,
            'document_name' => $document['name'] ?? null,
            'severity' => $severity,
            'category' => $category,
            'requirement' => Str::limit($requirement, 250),
            'rationale' => $rationale,
            'evidence' => $this->text($row['evidence'] ?? null),
            'ask_manufacturer' => $this->text($row['ask_manufacturer'] ?? null),
        ];
    }

    /**
     * Sort the product's papers into those that can be read and those that
     * cannot.
     *
     * @return array{0: array<int, array{id: int, name: string, type: string, mime_type: string, size: int}>, 1: array<int, Document|Image>, 2: array<int, array{id: int, name: string, reason: string}>}
     */
    private function attachmentsFor(Product $product): array
    {
        $sent = [];
        $attachments = [];
        $skipped = [];
        $budget = self::SIZE_BUDGET;

        foreach ($product->documents->sortBy('id') as $document) {
            $attachment = $this->attachmentFor($document);

            $reason = match (true) {
                $attachment === null => self::SKIPPED_UNSUPPORTED,
                ! Storage::disk(ProductDocument::DISK)->exists($document->path) => self::SKIPPED_MISSING,
                $document->size > $budget => self::SKIPPED_OVER_BUDGET,
                default => null,
            };

            if ($reason !== null) {
                $skipped[] = ['id' => $document->id, 'name' => $document->name, 'reason' => $reason];

                continue;
            }

            $budget -= $document->size;

            $sent[] = [
                'id' => $document->id,
                'name' => $document->name,
                'type' => $document->type->value,
                'mime_type' => $document->mime_type,
                'size' => $document->size,
            ];

            $attachments[] = $attachment;
        }

        return [$sent, $attachments, $skipped];
    }

    /**
     * Get the paper as something the provider can read, or null if it is a
     * kind of file the providers do not take -- a Word or Excel file.
     */
    private function attachmentFor(ProductDocument $document): Document|Image|null
    {
        return match (true) {
            $document->mime_type === 'application/pdf' => Document::fromStorage($document->path, ProductDocument::DISK)
                ->withMimeType('application/pdf')
                ->as($document->name),
            in_array($document->mime_type, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true) => Image::fromStorage($document->path, ProductDocument::DISK)
                ->withMimeType($document->mime_type)
                ->as($document->name),
            default => null,
        };
    }

    /**
     * Describe the product and number its papers in the order they are
     * attached.
     *
     * @param  array<int, array{id: int, name: string, type: string, mime_type: string, size: int}>  $sent
     * @param  array<int, array{id: int, name: string, reason: string}>  $skipped
     * @param  array<int, ProductAssessmentFinding>  $previous
     */
    private function promptFor(Product $product, array $sent, array $skipped, ?ProductAssessment $previousAssessment, array $previous): string
    {
        $details = collect([
            'Name' => $product->name,
            'Brand' => $product->brand?->name,
            'Category' => $product->category->name,
            'Requirement template' => $product->template->name,
            'EAN' => $product->ean,
            'Supplier article number' => $product->supplier_article_number,
            'Internal article number' => $product->internal_article_number,
            'Country of origin' => $product->country_of_origin?->label(),
            'Age grading' => $product->age_grading,
            'Safety notice' => $product->safety_notice,
            'Warning text' => $product->warning_text,
            'Materials' => $product->material_information,
            'Usage restrictions' => $product->usage_restrictions,
            'Safety instructions' => $product->safety_instructions,
        ])
            ->map(fn (?string $value, string $label): string => "- {$label}: ".(filled($value) ? $value : '(not given)'))
            ->implode("\n");

        $documents = collect($sent)
            ->map(fn (array $document, int $index): string => sprintf(
                '%d. %s -- filed as: %s',
                $index,
                $document['name'],
                $document['type'],
            ))
            ->implode("\n");

        $prompt = "Product details:\n\n{$details}\n\nAttached documents, in this order:\n\n"
            .($documents !== '' ? $documents : '(none)');

        if ($skipped !== []) {
            $prompt .= "\n\nAlso filed but not attached, so not readable by you:\n\n"
                .collect($skipped)->map(fn (array $document): string => "- {$document['name']}")->implode("\n");
        }

        if ($previousAssessment !== null) {
            $prompt .= "\n\nFindings from the earlier check on {$previousAssessment->completed_at?->toDateString()}:\n\n"
                .($previous === [] ? '(none -- it found nothing to flag)' : collect($previous)
                    ->map(fn (ProductAssessmentFinding $finding, int $index): string => sprintf(
                        '%d. [%s] %s -- %s%s',
                        $index,
                        $finding->severity->value,
                        $finding->requirement,
                        $finding->rationale,
                        $finding->document_name !== null ? " (document: {$finding->document_name})" : '',
                    ))
                    ->implode("\n"));
        }

        return $prompt."\n\nAssess these documents.";
    }

    /**
     * Get the language the reviewer reads, named for the model.
     */
    private function languageFor(Organization $organization): string
    {
        return match ($organization->locale) {
            Locale::German => 'German',
            default => 'English',
        };
    }

    /**
     * Get the message shown for a reason a run could not happen.
     */
    private function messageFor(string $reason): string
    {
        return match ($reason) {
            self::NOT_CONFIGURED => __('Connect an AI provider in the organization settings first.'),
            self::ANALYSIS_DISABLED => __('An admin has to allow document analysis in the organization settings first.'),
            self::ALREADY_RUNNING => __('A check is already running for this product.'),
            default => __('The AI check is not available.'),
        };
    }

    /**
     * Get a trimmed string from the answer, or null for nothing.
     */
    private function text(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
