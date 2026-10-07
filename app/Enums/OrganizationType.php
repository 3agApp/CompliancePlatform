<?php

namespace App\Enums;

/**
 * The side of the supply chain an organization works on.
 *
 * The type is chosen when the organization is created and is immutable
 * afterwards: both the scoped route binding for products and the dashboard
 * navigation are derived from it. A company that both imports and supplies
 * creates one organization of each type.
 */
enum OrganizationType: string
{
    case Distributor = 'distributor';
    case Supplier = 'supplier';

    /**
     * Get the display label for the type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Distributor => __('Distributor'),
            self::Supplier => __('Supplier'),
        };
    }

    /**
     * Get the description shown while choosing a type during onboarding.
     */
    public function description(): string
    {
        return match ($this) {
            self::Distributor => __('We place products on the market and collect compliance data from our suppliers.'),
            self::Supplier => __('We manufacture or supply products and provide compliance data to distributors.'),
        };
    }

    /**
     * Get all the types an organization can be created with.
     *
     * @return array<array{value: string, label: string, description: string}>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->map(fn (self $type) => [
                'value' => $type->value,
                'label' => $type->label(),
                'description' => $type->description(),
            ])
            ->values()
            ->toArray();
    }
}
