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
                TextEntry::make('name')
                    ->label('Nome'),
                TextEntry::make('slug')
                    ->label('Slug'),
                TextEntry::make('status')
                    ->label('Status')
                    ->badge(),
                TextEntry::make('default_timezone')
                    ->label('Fuso horário padrão'),
                TextEntry::make('default_currency')
                    ->label('Moeda padrão'),
                TextEntry::make('locale')
                    ->label('Idioma'),
                TextEntry::make('primary_color')
                    ->label('Cor primária')
                    ->placeholder('-'),
                TextEntry::make('secondary_color')
                    ->label('Cor secundária')
                    ->placeholder('-'),
                TextEntry::make('primaryLegalEntity.legal_name')
                    ->label('Entidade legal principal')
                    ->placeholder('-'),
                TextEntry::make('headquarters.name')
                    ->label('Unidade matriz')
                    ->placeholder('-'),
                TextEntry::make('created_at')
                    ->label('Criado em')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->label('Atualizado em')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
