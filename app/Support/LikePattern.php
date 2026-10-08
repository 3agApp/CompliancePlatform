<?php

namespace App\Support;

/**
 * A "contains" pattern for LIKE that matches what was typed, literally.
 *
 * Typed % and _ would otherwise be wildcards -- an article number such as
 * "AB_12" would match "ABX12", and "__" would match everything. Each is
 * escaped with "!", which the queries name in an explicit ESCAPE clause:
 * SQLite has no default escape character, and a backslash means
 * different things to different databases.
 */
class LikePattern
{
    /**
     * The character the queries pass as ESCAPE '!'.
     */
    public const string ESCAPE = '!';

    /**
     * Get the pattern that matches anything containing the term.
     */
    public static function contains(string $term): string
    {
        return '%'.strtr($term, ['!' => '!!', '%' => '!%', '_' => '!_']).'%';
    }
}
