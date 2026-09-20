<?php

namespace App\Filament\Resources\Organizations\Schemas;

use App\Enums\OrganizationType;
use App\Filament\Resources\Users\UserResource;
use App\Models\Organization;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OrganizationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Organization')
                    ->columns(3)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('name'),
                        TextEntry::make('slug'),
                        TextEntry::make('type')
                            ->badge()
                            ->formatStateUsing(fn (OrganizationType $state): string => $state->label()),
                        TextEntry::make('ownerMembership.user.email')
                            ->label('Owner')
                            ->placeholder('No owner')
                            ->url(fn (Organization $record): ?string => $record->ownerMembership
                                ? UserResource::getUrl('view', ['record' => $record->ownerMembership->user_id])
                                : null),
                        TextEntry::make('memberships_count')
                            ->label('Members')
                            ->state(fn (Organization $record): int => $record->memberships()->count()),
                        TextEntry::make('products_count')
                            ->label('Products')
                            ->state(fn (Organization $record): int => $record->products()->count()),
                        TextEntry::make('connections_count')
                            ->label(fn (Organization $record): string => $record->isDistributor() ? 'Suppliers' : 'Distributors')
                            ->state(fn (Organization $record): int => $record->isDistributor()
                                ? $record->supplierConnections()->count()
                                : $record->distributorConnections()->count()),
                        TextEntry::make('created_at')
                            ->label('Created')
                            ->dateTime(),
                        TextEntry::make('deleted_at')
                            ->label('Deleted')
                            ->dateTime()
                            ->color('danger')
                            ->visible(fn (Organization $record): bool => $record->trashed()),
                    ]),
            ]);
    }
}
