<?php

namespace App\Enums;

/**
 * Which identifier an imported article is matched to an existing product by.
 */
enum ImportMatchKey: string
{
    case Sku = 'sku';
    case Ean = 'ean';
    case Mpn = 'mpn';

    /**
     * Get the display label for the key.
     */
    public function label(): string
    {
        return match ($this) {
            self::Sku => __('SKU (internal article number)'),
            self::Ean => __('EAN'),
            self::Mpn => __('MPN (supplier article number)'),
        };
    }

    /**
     * Get the product column the key is read from.
     */
    public function column(): string
    {
        return match ($this) {
            self::Sku => 'internal_article_number',
            self::Ean => 'ean',
            self::Mpn => 'supplier_article_number',
        };
    }

    /**
     * Get the keys as options for a select.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->map(fn (self $key) => ['value' => $key->value, 'label' => $key->label()])
            ->values()
            ->toArray();
    }
}
