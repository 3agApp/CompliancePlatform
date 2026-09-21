<?php

namespace App\Data;

/**
 * A file somebody has chosen but not yet uploaded.
 *
 * This is the whole of what is known about it, and the whole of what is ever
 * sent anywhere: a name, a type and a size. The bytes stay in the browser
 * until the kinds have been settled and the upload is pressed.
 */
readonly class ProductDocumentCandidate
{
    public function __construct(
        public string $name,
        public string $mimeType,
        public int $size,
    ) {
        //
    }

    /**
     * Build a candidate from one row of a validated request.
     *
     * @param  array{name: string, mime_type: string, size: int}  $row
     */
    public static function fromArray(array $row): self
    {
        return new self(
            name: $row['name'],
            mimeType: $row['mime_type'],
            size: (int) $row['size'],
        );
    }
}
