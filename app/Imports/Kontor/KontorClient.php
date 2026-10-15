<?php

namespace App\Imports\Kontor;

use App\Data\ImportArticle;
use App\Data\ImportArticlePage;
use App\Data\ImportManufacturerData;
use App\Imports\ImportClient;
use App\Imports\ImportRequestException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Reads products and manufacturers from Kontor.
 *
 * Kontor has one read endpoint, POST {base}/search, and the body's entity
 * says what is being read. Every answer, error or not, comes in the same
 * envelope -- {success, message, meta: {totalCount}, data, errorCode} -- and
 * a 200 can still say success: false, so the status alone proves nothing.
 *
 * What is known about it was learned by probing the live API for the
 * WooCommerce sync plugin; there is no published documentation.
 */
class KontorClient implements ImportClient
{
    /**
     * The most rows Kontor hands back per page, whatever is asked for.
     */
    public const int MAX_PAGE_SIZE = 2000;

    /**
     * How long one request may take, in seconds.
     */
    public const int TIMEOUT = 30;

    /**
     * How many times a request is tried before giving up.
     */
    public const int ATTEMPTS = 3;

    /**
     * The image fields, in the order they are shown, the main one first.
     *
     * @var list<string>
     */
    private const array IMAGE_FIELDS = [
        'MainImageURL',
        'ImageURL_1', 'ImageURL_2', 'ImageURL_3', 'ImageURL_4', 'ImageURL_5',
        'ImageURL_6', 'ImageURL_7', 'ImageURL_8', 'ImageURL_9',
    ];

    /**
     * The only values that hold an article back from the webshop. Anything
     * else, a missing field included, counts as active.
     *
     * @var list<string>
     */
    private const array INACTIVE_VALUES = ['0', 'false', 'no', 'nein'];

    public function __construct(
        private string $baseUrl,
        private string $apiKey,
    ) {
        //
    }

    /**
     * {@inheritDoc}
     */
    public function manufacturers(): array
    {
        [$rows] = $this->searchWithTotal(['entity' => 'manufacturer']);

        return array_values(collect($rows)
            ->filter(fn (mixed $row): bool => is_array($row) && filled($row['Herstellerid'] ?? null))
            ->map(fn (array $row): ImportManufacturerData => new ImportManufacturerData(
                id: trim((string) $row['Herstellerid']),
                name: self::text($row['Hersteller'] ?? null) ?? trim((string) $row['Herstellerid']),
            ))
            ->unique(fn (ImportManufacturerData $manufacturer): string => $manufacturer->id)
            ->sortBy(fn (ImportManufacturerData $manufacturer): string => mb_strtolower($manufacturer->name))
            ->all());
    }

    /**
     * {@inheritDoc}
     */
    public function articles(array $manufacturerIds, int $skip, int $take): ImportArticlePage
    {
        $body = [
            'entity' => 'products',
            'paging' => ['skip' => $skip, 'take' => min($take, self::MAX_PAGE_SIZE)],
        ];

        /**
         * One comma-joined string, not an array, and the ids are sent as
         * they came: "084" is not "84".
         */
        if ($manufacturerIds !== []) {
            $body['filter'] = ['herstellerids' => implode(',', $manufacturerIds)];
        }

        [$rows, $total] = $this->searchWithTotal($body);

        $articles = array_values(collect($rows)
            ->filter(fn (mixed $row): bool => is_array($row))
            ->map(fn (array $row): ?ImportArticle => $this->toArticle($row))
            ->filter()
            ->all());

        return new ImportArticlePage($articles, count($rows), $total);
    }

    /**
     * Read the rows of one search.
     *
     * Retried only where trying again can help: the API being briefly down,
     * busy, or out of reach. A rejected key or a malformed request will be
     * rejected again.
     *
     * @param  array<string, mixed>  $body
     * @return array{0: array<int, mixed>, 1: int|null}
     *
     * @throws ImportRequestException
     */
    private function searchWithTotal(array $body): array
    {
        try {
            $response = $this->request()->post(rtrim($this->baseUrl, '/').'/search', $body);
        } catch (ConnectionException $exception) {
            throw new ImportRequestException(__('Kontor could not be reached: :reason', ['reason' => $exception->getMessage()]), previous: $exception);
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw new ImportRequestException(__('Kontor answered with something that is not JSON (HTTP :status).', ['status' => $response->status()]));
        }

        if (! $response->successful() || ($payload['success'] ?? false) !== true) {
            throw new ImportRequestException($this->errorMessage($response, $payload));
        }

        $total = $payload['meta']['totalCount'] ?? null;

        return [
            is_array($payload['data'] ?? null) ? array_values($payload['data']) : [],
            is_numeric($total) ? (int) $total : null,
        ];
    }

    /**
     * Build the request every search is sent with.
     *
     * The key goes in as it is, never trimmed of anything but whitespace:
     * Kontor keys can hold percent signs, and a key that loses a few
     * characters on the way fails with a 401 nobody can explain.
     */
    private function request(): PendingRequest
    {
        return Http::acceptJson()
            ->asJson()
            ->withHeaders(['x-api-key' => trim($this->apiKey)])
            ->timeout(self::TIMEOUT)
            ->retry(
                self::ATTEMPTS,
                fn (int $attempt): int => 2000 * $attempt,
                fn (Throwable $exception): bool => $exception instanceof ConnectionException
                    || ($exception instanceof RequestException && self::isRetryable($exception->response)),
                throw: false,
            );
    }

    /**
     * Determine if a failed answer is worth asking for again.
     */
    private static function isRetryable(Response $response): bool
    {
        return $response->status() === 429 || $response->serverError();
    }

    /**
     * Say what went wrong in Kontor's own words where it gave any.
     *
     * @param  array<string, mixed>  $payload
     */
    private function errorMessage(Response $response, array $payload): string
    {
        $message = self::text($payload['message'] ?? null);
        $code = self::text($payload['errorCode'] ?? null);

        if ($message === null) {
            return __('Kontor refused the request (HTTP :status).', ['status' => $response->status()]);
        }

        return $code !== null ? "{$message} ({$code})" : $message;
    }

    /**
     * Read one product row, or null for a row without an article number.
     *
     * @param  array<string, mixed>  $row
     */
    private function toArticle(array $row): ?ImportArticle
    {
        $sku = self::text($row['Artnr'] ?? null);

        if ($sku === null) {
            return null;
        }

        $images = array_values(collect(self::IMAGE_FIELDS)
            ->map(fn (string $field): ?string => is_string($row[$field] ?? null) ? trim($row[$field]) : null)
            ->filter()
            ->unique()
            ->all());

        return new ImportArticle(
            sku: $sku,
            name: self::text($row['Shoptitel'] ?? null) ?? self::text($row['Bez1'] ?? null) ?? $sku,
            ean: self::text($row['Artean'] ?? null),
            mpn: self::text($row['Mpn'] ?? null),
            manufacturerId: self::text($row['Herstellerid'] ?? null),
            manufacturerName: self::text($row['Hersteller'] ?? null),
            shortDescription: self::paragraphs($row['Kurztext'] ?? null),
            longDescription: self::paragraphs($row['Langtext'] ?? null),
            isActive: self::isActive($row['Ws_aktiv'] ?? null),
            imageFiles: $images,
        );
    }

    /**
     * Determine if the article is still sold, holding it back only on an
     * answer that cannot be read any other way.
     */
    private static function isActive(mixed $value): bool
    {
        if ($value === false || $value === 0) {
            return false;
        }

        return ! (is_string($value) && in_array(mb_strtolower(trim($value)), self::INACTIVE_VALUES, true));
    }

    /**
     * Read a one-line value, or null when there is nothing in it.
     */
    private static function text(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $text = trim(html_entity_decode(strip_tags((string) $value), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        return $text === '' ? null : $text;
    }

    /**
     * Read a block of copy as plain text, keeping its paragraphs.
     *
     * The descriptions may hold HTML meant for a webshop. Line breaks and
     * paragraphs survive as new lines; everything else is reduced to text.
     */
    private static function paragraphs(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $text = preg_replace('/<\s*br\s*\/?>|<\/\s*(p|div|li|h[1-6])\s*>/i', "\n", $value) ?? $value;
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace("/[ \t]+\n/", "\n", $text) ?? $text;
        $text = trim(preg_replace("/\n{3,}/", "\n\n", $text) ?? $text);

        return $text === '' ? null : $text;
    }
}
