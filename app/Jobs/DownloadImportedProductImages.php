<?php

namespace App\Jobs;

use App\Enums\ProductDocumentType;
use App\Enums\ProductEventType;
use App\Models\ImportRun;
use App\Models\Product;
use App\Models\ProductDocument;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Fetch an imported product's images from the source's file host and file
 * them as product images.
 *
 * An image that cannot be fetched is noted on the run and left out; the
 * product is already imported and stays so.
 */
class DownloadImportedProductImages implements ShouldQueue
{
    use Queueable;

    /**
     * How long one download may take, in seconds. The image host has been
     * seen to accept a connection and then never answer.
     */
    public const int TIMEOUT = 20;

    /**
     * The largest image kept, in bytes: the same as a person may upload.
     */
    public const int MAX_BYTES = 10 * 1024 * 1024;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 1;

    /**
     * Create a new job instance.
     *
     * @param  list<string>  $urls
     */
    public function __construct(
        public ImportRun $run,
        public Product $product,
        public array $urls,
    ) {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $known = $this->product->documents()
            ->whereIn('source_url', $this->urls)
            ->pluck('source_url')
            ->all();

        foreach (array_diff($this->urls, $known) as $url) {
            try {
                $this->download($url);
            } catch (Throwable $exception) {
                $this->noteFailure($url, $exception->getMessage());
            }
        }
    }

    /**
     * Fetch one image and file it.
     */
    private function download(string $url): void
    {
        $response = Http::timeout(self::TIMEOUT)->get($url);

        if (! $response->successful()) {
            $this->noteFailure($url, __('The image host answered HTTP :status.', ['status' => $response->status()]));

            return;
        }

        $mimeType = Str::before((string) $response->header('Content-Type'), ';');
        $body = $response->body();

        if (! Str::startsWith($mimeType, 'image/')) {
            $this->noteFailure($url, __('The file is not an image.'));

            return;
        }

        if (strlen($body) > self::MAX_BYTES) {
            $this->noteFailure($url, __('The image is larger than 10 MB.'));

            return;
        }

        $name = basename((string) parse_url($url, PHP_URL_PATH)) ?: 'image';
        $extension = pathinfo($name, PATHINFO_EXTENSION) ?: Str::after($mimeType, 'image/');

        /**
         * Named on disk by the platform, never by the source, the same as an
         * upload: the file name is something shown, not something that
         * decides a path.
         */
        $path = ProductDocument::directoryFor($this->product).'/'.Str::random(40).'.'.Str::lower($extension);

        Storage::disk(ProductDocument::DISK)->put($path, $body);

        try {
            DB::transaction(function () use ($url, $name, $path, $mimeType, $body) {
                $this->product->documents()->create([
                    'type' => ProductDocumentType::ProductImage,
                    'name' => $name,
                    'path' => $path,
                    'source_url' => $url,
                    'mime_type' => $mimeType,
                    'size' => strlen($body),
                    'uploaded_by' => $this->run->triggered_by,
                ]);

                $this->product->recordEvent(
                    ProductEventType::DocumentUploaded,
                    $this->run->triggeredBy,
                    $this->run->organization,
                    changes: ['document' => ['from' => null, 'to' => $name]],
                );
            });
        } catch (Throwable $exception) {
            Storage::disk(ProductDocument::DISK)->delete($path);

            throw $exception;
        }
    }

    /**
     * Keep a line on the run about an image that did not make it.
     *
     * Read fresh and written straight back, because the import itself may
     * be writing the same run's counters on another worker.
     */
    private function noteFailure(string $url, string $reason): void
    {
        DB::transaction(function () use ($url, $reason) {
            $run = ImportRun::query()->lockForUpdate()->find($this->run->id);

            if (! $run instanceof ImportRun) {
                return;
            }

            $run->recordFailure($this->product->internal_article_number ?? $this->product->name, __('Image :file: :reason', [
                'file' => basename((string) parse_url($url, PHP_URL_PATH)),
                'reason' => $reason,
            ]));
            $run->save();
        });
    }
}
