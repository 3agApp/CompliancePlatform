<?php

namespace App\Enums;

/**
 * The sources a distributor may import its catalogue from.
 */
enum ImportProvider: string
{
    case Kontor = 'kontor';

    /**
     * Get the display label for the provider.
     */
    public function label(): string
    {
        return match ($this) {
            self::Kontor => 'Kontor',
        };
    }

    /**
     * Get the providers as options for a select.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->map(fn (self $provider) => ['value' => $provider->value, 'label' => $provider->label()])
            ->values()
            ->toArray();
    }
}
