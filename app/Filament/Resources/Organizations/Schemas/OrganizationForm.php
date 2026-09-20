<?php

namespace App\Filament\Resources\Organizations\Schemas;

use App\Enums\OrganizationType;
use App\Models\Organization;
use App\Rules\OrganizationName;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OrganizationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Organization')
                    ->description('Renaming the organization also changes its URL.')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->rule(new OrganizationName),
                        // The type is fixed at creation: the scoped route
                        // binding for products and the whole of the sidebar
                        // are derived from it, so changing it would strand an
                        // organization halfway between the two sets of
                        // screens. A company that does both keeps one of each.
                        TextInput::make('type')
                            ->label('Type')
                            ->disabled()
                            ->dehydrated(false)
                            // The form fills from the raw attributes, so the
                            // state arrives as the backing string.
                            ->formatStateUsing(fn (?string $state): ?string => $state === null
                                ? null
                                : OrganizationType::tryFrom($state)?->label())
                            ->helperText(fn (?Organization $record): string => $record
                                ? 'Fixed when the organization was created.'
                                : 'Chosen when the organization is created.'),
                    ]),
            ]);
    }
}
