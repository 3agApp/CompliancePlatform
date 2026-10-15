<?php

namespace App\Enums;

/**
 * What an import does with an article that is already a product here.
 */
enum ImportExistingProductAction: string
{
    case Update = 'update';
    case Skip = 'skip';

    /**
     * Get the display label for the action.
     */
    public function label(): string
    {
        return match ($this) {
            self::Update => __('Update it with the imported data'),
            self::Skip => __('Leave it as it is'),
        };
    }

    /**
     * Get the actions as options for a select.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->map(fn (self $action) => ['value' => $action->value, 'label' => $action->label()])
            ->values()
            ->toArray();
    }
}
