<?php

declare(strict_types=1);

namespace App\Filament\Resources\Professionals\Schemas;

use App\Enums\LegalEntityType;
use App\Support\Documents\Document;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use InvalidArgumentException;

class ProfessionalInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('id')
                    ->label('ID'),
                TextEntry::make('organization.name')
                    ->label('Clínica'),
                TextEntry::make('display_name')
                    ->label('Nome de exibição'),
                TextEntry::make('user.name')
                    ->label('Usuário vinculado')
                    ->placeholder('Nenhum'),
                TextEntry::make('email')
                    ->label('E-mail')
                    ->placeholder('-'),
                TextEntry::make('phone')
                    ->label('Telefone')
                    ->placeholder('-')
                    ->formatStateUsing(function (?string $state) {
                        if ($state === null || $state === '') {
                            return '-';
                        }

                        $digits = preg_replace('/\D/', '', $state) ?? '';

                        return strlen($digits) <= 4
                            ? str_repeat('•', strlen($digits))
                            : str_repeat('•', strlen($digits) - 4).substr($digits, -4);
                    }),
                TextEntry::make('document')
                    ->label('CPF')
                    ->placeholder('-')
                    ->formatStateUsing(function (?string $state) {
                        if ($state === null || $state === '') {
                            return '-';
                        }

                        try {
                            return Document::fromType(LegalEntityType::Individual, $state)->masked();
                        } catch (InvalidArgumentException) {
                            return '****';
                        }
                    }),
                TextEntry::make('status')
                    ->label('Status')
                    ->badge(),
                TextEntry::make('unitLinks_count')
                    ->label('Unidades vinculadas')
                    ->state(fn ($record) => $record->unitLinks()->count()),
                TextEntry::make('specialtyLinks_count')
                    ->label('Especialidades vinculadas')
                    ->state(fn ($record) => $record->specialtyLinks()->count()),
                TextEntry::make('serviceLinks_count')
                    ->label('Serviços vinculados')
                    ->state(fn ($record) => $record->serviceLinks()->count()),
                TextEntry::make('registrations_count')
                    ->label('Registros profissionais')
                    ->state(fn ($record) => $record->registrations()->count()),
                TextEntry::make('working_hours_count')
                    ->label('Intervalos de jornada configurados')
                    ->state(fn ($record) => $record->workingHours()->count()),
                TextEntry::make('time_blocks_count')
                    ->label('Ausências e bloqueios registrados')
                    ->state(fn ($record) => $record->timeBlocks()->count()),
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
