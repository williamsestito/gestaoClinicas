<?php

declare(strict_types=1);

namespace App\Filament\Resources\Services\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class ServiceInfolist
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
                    ->label('Código'),
                TextEntry::make('default_duration_minutes')
                    ->label('Duração padrão (min)'),
                TextEntry::make('default_price_cents')
                    ->label('Preço padrão')
                    ->formatStateUsing(fn (?int $state) => $state === null ? '-' : 'R$ '.number_format($state / 100, 2, ',', '.')),
                IconEntry::make('is_public')
                    ->label('Exibição pública habilitada')
                    ->boolean(),
                TextEntry::make('unit_availability_scope')
                    ->label('Disponibilidade por unidade')
                    ->badge(),
                TextEntry::make('status')
                    ->label('Status')
                    ->badge(),
                TextEntry::make('specialtyLinks_count')
                    ->label('Especialidades vinculadas')
                    ->state(fn ($record) => $record->specialtyLinks()->count()),
                TextEntry::make('professionalLinks_count')
                    ->label('Profissionais vinculados')
                    ->state(fn ($record) => $record->professionalLinks()->count()),
                TextEntry::make('deleted_at')
                    ->label('Excluído em')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('created_at')
                    ->label('Criado em')
                    ->dateTime(),
                TextEntry::make('updated_at')
                    ->label('Atualizado em')
                    ->dateTime(),
            ]);
    }
}
