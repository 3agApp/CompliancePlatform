<?php

namespace App\Enums;

enum CountryOfOrigin: string
{
    case Germany = 'DE';
    case Switzerland = 'CH';

    /**
     * Get the display label for the country.
     */
    public function label(): string
    {
        return match ($this) {
            self::Germany => 'Germany',
            self::Switzerland => 'Switzerland',
        };
    }

    /**
     * Get all the countries that can be assigned to a product.
     *
     * @return array<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->map(fn (self $country) => ['value' => $country->value, 'label' => $country->label()])
            ->values()
            ->toArray();
    }
}
