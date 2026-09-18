<?php

namespace App\Data;

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

    public function __construct(
        public ?int $connection = null,
        public ?string $search = null,
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
            search: self::value($request, 'search', self::MAX_SEARCH_LENGTH),
        );
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
