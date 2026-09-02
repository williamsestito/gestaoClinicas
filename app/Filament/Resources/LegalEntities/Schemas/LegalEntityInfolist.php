<?php

namespace App\Filament\Resources\LegalEntities\Schemas;

use App\Enums\LegalEntityType;
use App\Models\LegalEntity;
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
                    ->label('Clínica'),
                TextEntry::make('type')
                    ->label('Tipo')
                    ->badge(),
                TextEntry::make('document')
                    ->label('CPF/CNPJ')
                    ->formatStateUsing(function (string $state, LegalEntity $record): string {
                        $digits = preg_replace('/\D/', '', $state) ?? '';

                        if ($record->type === LegalEntityType::Individual && strlen($digits) === 11) {
                            return '***.***.***-'.substr($digits, 9);
                        }

                        if ($record->type === LegalEntityType::Company && strlen($digits) === 14) {
                            return '**.***.***/****-'.substr($digits, 12);
                        }

                        return '****';
                    }),
                TextEntry::make('legal_name')
                    ->label(fn (LegalEntity $record): string => $record->type === LegalEntityType::Individual
                        ? 'Nome completo'
                        : 'Razão social'),
                TextEntry::make('trade_name')
                    ->label('Nome fantasia')
                    ->placeholder('-'),
                TextEntry::make('state_registration')
                    ->label('Inscrição estadual')
                    ->placeholder('-'),
                TextEntry::make('municipal_registration')
                    ->label('Inscrição municipal')
                    ->placeholder('-'),
                TextEntry::make('email')
                    ->label('E-mail')
                    ->placeholder('-'),
                TextEntry::make('phone')
                    ->label('Telefone')
                    ->placeholder('-'),
                IconEntry::make('is_primary')
                    ->label('Principal')
                    ->boolean(),
                TextEntry::make('status')
                    ->label('Status')
                    ->badge(),
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
