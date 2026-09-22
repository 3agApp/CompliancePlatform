<?php

namespace App\Data;

use App\Enums\ProductReviewStatus;
use Illuminate\Http\Request;

/**
 * The state of the product list filters, read from the query string.
 *
 * The URL is the source of truth so a filtered list can be shared, bookmarked
 * and reached by a link from the suppliers or distributors page. A filter is
 * either set or absent; there is nothing here to reject, so a blank or
 * unparseable value is simply not a filter.
 */
readonly class ProductFilters
{
    /**
     * The longest search term accepted, which bounds a pathological LIKE.
     */
    protected const int MAX_SEARCH_LENGTH = 100;

    /**
     * The page sizes the list offers, the first of which is the default.
     *
     * Anything else in the query string is not a size the list can render,
     * so it falls back rather than being honoured.
     *
     * @var list<int>
     */
    public const array PAGE_SIZES = [25, 50, 100];

    public function __construct(
        public ?int $connection = null,
        public ?int $category = null,
        public ?int $brand = null,
        public ?string $search = null,
        public ?ProductReviewStatus $status = null,
        public int $perPage = self::PAGE_SIZES[0],
    ) {
        //
    }

    /**
     * Read the filters off the query string.
     */
    public static function fromRequest(Request $request): self
    {
        return new self(
            connection: self::id($request, 'connection'),
            category: self::id($request, 'category'),
            brand: self::id($request, 'brand'),
            search: self::value($request, 'search', self::MAX_SEARCH_LENGTH),
            status: self::status($request),
            perPage: self::perPage($request),
        );
    }

    /**
     * Read the state of review being filtered for.
     *
     * A word that names no state is not a filter, the same way an id that
     * names no row is not one: a stale bookmark or a hand-edited URL leaves
     * the whole list showing rather than an empty one.
     */
    protected static function status(Request $request): ?ProductReviewStatus
    {
        return ProductReviewStatus::tryFrom(trim((string) $request->query('status', '')));
    }

    /**
     * Read the page size, which is a choice from a fixed list rather than a
     * filter: an unrecognised one leaves the list rendering its default
     * instead of an arbitrary number of rows.
     */
    protected static function perPage(Request $request): int
    {
        $value = $request->integer('per_page');

        return in_array($value, self::PAGE_SIZES, true) ? $value : self::PAGE_SIZES[0];
    }

    /**
     * Read one filter that names a row by its key.
     *
     * Anything that is not a plain positive integer cannot name a row, so it
     * is not a filter either. That covers a blank value, a hand-edited URL
     * and a uuid left over from an old bookmark alike.
     */
    protected static function id(Request $request, string $key): ?int
    {
        $value = trim((string) $request->query($key, ''));

        return ctype_digit($value) && (int) $value > 0 ? (int) $value : null;
    }

    /**
     * Read one filter, treating a blank value as the absence of a filter.
     *
     * A hand-typed "?search=" then behaves exactly like no query string at
     * all, which is also what the filter bar sends when it is cleared.
     */
    protected static function value(Request $request, string $key, int $limit = 64): ?string
    {
        $value = mb_substr(trim((string) $request->query($key, '')), 0, $limit);

        return $value === '' ? null : $value;
    }
}
