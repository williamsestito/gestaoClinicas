<?php

namespace App\Filament\Resources\LegalEntities\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class LegalEntityInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('id')
                    ->label('ID'),
                TextEntry::make('organization.name')
                    ->label('Organization'),
                TextEntry::make('type')
                    ->badge(),
                TextEntry::make('document'),
                TextEntry::make('legal_name'),
                TextEntry::make('trade_name')
                    ->placeholder('-'),
                TextEntry::make('state_registration')
                    ->placeholder('-'),
                TextEntry::make('municipal_registration')
                    ->placeholder('-'),
                TextEntry::make('email')
                    ->label('Email address')
                    ->placeholder('-'),
                TextEntry::make('phone')
                    ->placeholder('-'),
                IconEntry::make('is_primary')
                    ->boolean(),
                TextEntry::make('status')
                    ->badge(),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
