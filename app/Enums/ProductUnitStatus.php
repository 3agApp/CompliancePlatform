<?php

namespace App\Enums;

/**
 * What a check of one serial found.
 *
 * Only ever the answer to a check somebody asked for: scanning a label
 * shows nothing about it, so the answer cannot be read off a shelf by
 * anyone who did not press Check.
 */
enum ProductUnitStatus: string
{
    /**
     * Nobody issued this serial for this product. Either it was mistyped,
     * or the label was made by somebody other than the distributor.
     */
    case Unknown = 'unknown';

    /**
     * Withdrawn by the distributor: a roll that went missing, a misprint.
     */
    case Revoked = 'revoked';

    /**
     * Genuine, and this was the first time anybody checked it: what a new
     * packet reads.
     */
    case FirstCheck = 'first_check';

    /**
     * Genuine, and checked before -- but only ever from this device.
     */
    case CheckedBefore = 'checked_before';

    /**
     * Genuine, and checked before from some other device, which for whoever
     * is holding it now means a used packet or a copied label.
     */
    case CheckedElsewhere = 'checked_elsewhere';
}
