<?php

namespace App\Enums;

/**
 * What the public seal on a product says.
 *
 * The seal is the one thing about a product that a shopper, a retailer or an
 * inspector sees without an account, so it says only what can be stood
 * behind: that a check finished and passed, that one is under way, or that
 * none has got anywhere yet. It never says a product is unsafe -- an
 * unfinished check and a failed one are not the same thing, and the seal is
 * not the place to tell them apart.
 */
enum ProductSealStatus: string
{
    case Verified = 'verified';
    case InProgress = 'in_progress';
    case NotVerified = 'not_verified';

    /**
     * Get the display label for the seal.
     */
    public function label(): string
    {
        return match ($this) {
            self::Verified => __('Verified'),
            self::InProgress => __('In progress'),
            self::NotVerified => __('Not verified'),
        };
    }

    /**
     * Get the line the seal carries under its label.
     */
    public function message(): string
    {
        return match ($this) {
            self::Verified => __('This product meets Swiss compliance requirements.'),
            self::InProgress => __('The compliance check has not finished yet.'),
            self::NotVerified => __('No completed compliance check yet.'),
        };
    }

    /**
     * Get every seal an override may force a product to.
     *
     * @return array<array{value: string, label: string, message: string}>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->map(fn (self $status) => [
                'value' => $status->value,
                'label' => $status->label(),
                'message' => $status->message(),
            ])
            ->values()
            ->toArray();
    }
}
