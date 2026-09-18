<?php

namespace App\Enums;

/**
 * The lifecycle of the link between a distributor and one of its suppliers.
 *
 * A distributor and a supplier organization share exactly one connection row
 * forever. Ending the relationship revokes the row rather than deleting it, so
 * the products already assigned to it keep their assignment and the
 * relationship can be restored.
 */
enum SupplierConnectionStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Declined = 'declined';
    case Revoked = 'revoked';

    /**
     * Get the display label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Active => 'Active',
            self::Declined => 'Declined',
            self::Revoked => 'Revoked',
        };
    }

    /**
     * Get the statuses a product may be assigned to.
     *
     * A product can be assigned to a supplier that has not claimed its
     * invitation yet, so pending connections are assignable too.
     *
     * @return array<self>
     */
    public static function assignable(): array
    {
        return [self::Pending, self::Active];
    }

    /**
     * Get the assignable statuses as their backing values.
     *
     * @return array<string>
     */
    public static function assignableValues(): array
    {
        return array_map(fn (self $status) => $status->value, self::assignable());
    }
}
