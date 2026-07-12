<?php

namespace App\Filament\Resources\Organizations\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class OrganizationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('id')
                    ->label('ID'),
                TextEntry::make('name'),
                TextEntry::make('slug'),
                TextEntry::make('status')
                    ->badge(),
                TextEntry::make('default_timezone'),
                TextEntry::make('default_currency'),
                TextEntry::make('locale'),
                TextEntry::make('primary_color')
                    ->placeholder('-'),
                TextEntry::make('secondary_color')
                    ->placeholder('-'),
                TextEntry::make('primaryLegalEntity.legal_name')
                    ->label('Entidade legal principal')
                    ->placeholder('-'),
                TextEntry::make('headquarters.name')
                    ->label('Unidade matriz')
                    ->placeholder('-'),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
