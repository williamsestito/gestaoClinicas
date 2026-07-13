<?php

namespace App\Filament\Resources\Units\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class UnitInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('id')
                    ->label('ID'),
                TextEntry::make('organization.name')
                    ->label('Clínica'),
                TextEntry::make('legalEntity.id')
                    ->label('Entidade legal'),
                TextEntry::make('name')
                    ->label('Nome'),
                TextEntry::make('code')
                    ->label('Código'),
                TextEntry::make('slug')
                    ->label('Slug'),
                TextEntry::make('status')
                    ->label('Status')
                    ->badge(),
                IconEntry::make('is_headquarters')
                    ->label('Matriz')
                    ->boolean(),
                TextEntry::make('timezone')
                    ->label('Fuso horário'),
                TextEntry::make('email')
                    ->label('E-mail')
                    ->placeholder('-'),
                TextEntry::make('phone')
                    ->label('Telefone')
                    ->placeholder('-'),
                TextEntry::make('whatsapp')
                    ->label('WhatsApp')
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
