<?php

namespace App\Enums;

/**
 * Where a product's translated safety texts came from.
 *
 * Kept so a reader can tell a translation a person wrote or checked from
 * one the AI produced and nobody has changed since.
 */
enum TranslationSource: string
{
    case Ai = 'ai';
    case Manual = 'manual';
}
