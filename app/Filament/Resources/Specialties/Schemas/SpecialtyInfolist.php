<?php

declare(strict_types=1);

namespace App\Filament\Resources\Specialties\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class SpecialtyInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('id')
                    ->label('ID'),
                TextEntry::make('organization.name')
                    ->label('Clínica'),
                TextEntry::make('name')
                    ->label('Nome'),
                TextEntry::make('code')
                    ->label('Código')
                    ->placeholder('-'),
                TextEntry::make('description')
                    ->label('Descrição')
                    ->placeholder('-'),
                TextEntry::make('display_order')
                    ->label('Ordem de exibição'),
                TextEntry::make('status')
                    ->label('Status')
                    ->badge(),
                TextEntry::make('professionalLinks_count')
                    ->label('Profissionais vinculados')
                    ->state(fn ($record) => $record->professionalLinks()->count()),
                TextEntry::make('serviceLinks_count')
                    ->label('Serviços vinculados')
                    ->state(fn ($record) => $record->serviceLinks()->count()),
                TextEntry::make('deleted_at')
                    ->label('Excluída em')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('created_at')
                    ->label('Criada em')
                    ->dateTime(),
                TextEntry::make('updated_at')
                    ->label('Atualizada em')
                    ->dateTime(),
            ]);
    }
}
