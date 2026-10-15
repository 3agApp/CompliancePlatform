<?php

namespace App\Imports;

use App\Data\ImportArticlePage;
use App\Data\ImportManufacturerData;

/**
 * What an import needs from a source system, whichever system it comes from.
 */
interface ImportClient
{
    /**
     * List the manufacturers the source knows.
     *
     * @return list<ImportManufacturerData>
     *
     * @throws ImportRequestException
     */
    public function manufacturers(): array;

    /**
     * Read one page of the catalogue, limited to the given manufacturers.
     *
     * An empty page is the end of the catalogue.
     *
     * @param  list<string>  $manufacturerIds
     *
     * @throws ImportRequestException
     */
    public function articles(array $manufacturerIds, int $skip, int $take): ImportArticlePage;
}
