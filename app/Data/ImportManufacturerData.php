<?php

namespace App\Data;

/**
 * One manufacturer as the source lists it.
 */
readonly class ImportManufacturerData
{
    public function __construct(
        public string $id,
        public string $name,
    ) {
        //
    }

    /**
     * @return array{id: string, name: string}
     */
    public function toArray(): array
    {
        return ['id' => $this->id, 'name' => $this->name];
    }
}
