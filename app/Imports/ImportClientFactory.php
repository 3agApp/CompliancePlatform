<?php

namespace App\Imports;

use App\Enums\ImportProvider;
use App\Imports\Kontor\KontorClient;
use App\Models\ImportSource;

/**
 * Build the client for whichever system a source points at.
 */
class ImportClientFactory
{
    public function for(ImportSource $source): ImportClient
    {
        return match ($source->provider) {
            ImportProvider::Kontor => new KontorClient($source->api_url, $source->api_key),
        };
    }
}
