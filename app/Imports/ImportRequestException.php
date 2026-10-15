<?php

namespace App\Imports;

use RuntimeException;

/**
 * The source would not give an answer that can be used.
 *
 * The message is the source's own where it gave one, because that is what the
 * distributor can take to whoever runs their source system.
 */
class ImportRequestException extends RuntimeException
{
    //
}
