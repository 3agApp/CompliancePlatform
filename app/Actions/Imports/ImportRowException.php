<?php

namespace App\Actions\Imports;

use RuntimeException;

/**
 * One article could not be imported, for a reason the distributor can act
 * on. The rest of the page carries on without it.
 */
class ImportRowException extends RuntimeException
{
    //
}
