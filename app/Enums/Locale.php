<?php

namespace App\Enums;

/**
 * The languages the application speaks.
 *
 * English is the language the source is written in, so its strings are their
 * own translation and only German needs a file.
 */
enum Locale: string
{
    case English = 'en';
    case German = 'de';

    /**
     * Get the language's name in that language.
     *
     * A language is always offered under its own name, so a person who
     * cannot read the current one can still find theirs.
     */
    public function label(): string
    {
        return match ($this) {
            self::English => 'English',
            self::German => 'Deutsch',
        };
    }

    /**
     * Get the language to fall back on.
     */
    public static function default(): self
    {
        return self::English;
    }

    /**
     * Get every language as an option for a select.
     *
     * @return array<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $locale) => ['value' => $locale->value, 'label' => $locale->label()],
            self::cases(),
        );
    }

    /**
     * Get the languages as their backing values.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
