<?php

namespace App\Data;

use App\Enums\ImportMatchKey;

/**
 * One article as the source describes it, already read into this platform's
 * terms.
 *
 * Whatever the source calls its fields, an import only ever deals with this,
 * so a second source is a second client and nothing else.
 */
readonly class ImportArticle
{
    /**
     * @param  list<string>  $imageFiles  File names, in the source's order, the first being the main image.
     */
    public function __construct(
        public string $sku,
        public string $name,
        public ?string $ean,
        public ?string $mpn,
        public ?string $manufacturerId,
        public ?string $manufacturerName,
        public ?string $shortDescription,
        public ?string $longDescription,
        public bool $isActive,
        public array $imageFiles,
    ) {
        //
    }

    /**
     * Get the value the article is matched to a product by.
     */
    public function valueFor(ImportMatchKey $key): ?string
    {
        return match ($key) {
            ImportMatchKey::Sku => $this->sku,
            ImportMatchKey::Ean => $this->ean,
            ImportMatchKey::Mpn => $this->mpn,
        };
    }
}
