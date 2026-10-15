<?php

namespace App\Data;

/**
 * One page of the source's catalogue.
 *
 * The row count is kept apart from the articles because they can differ: a
 * row the import cannot use is dropped from the articles, but the next page
 * still starts after it.
 */
readonly class ImportArticlePage
{
    /**
     * @param  list<ImportArticle>  $articles
     */
    public function __construct(
        public array $articles,
        public int $rowCount,
        public ?int $total,
    ) {
        //
    }

    /**
     * Determine if this page is past the end of the catalogue.
     */
    public function isEmpty(): bool
    {
        return $this->rowCount === 0;
    }

    /**
     * Get how many rows were dropped for want of an article number.
     */
    public function unusableRows(): int
    {
        return $this->rowCount - count($this->articles);
    }
}
