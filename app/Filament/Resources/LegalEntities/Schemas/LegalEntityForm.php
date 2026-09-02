<?php

declare(strict_types=1);

namespace App\Filament\Resources\LegalEntities\Schemas;

use App\Enums\LegalEntityType;
use App\Models\LegalEntity;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class LegalEntityForm
{
    /**
     * organization_id, type, document e is_primary não são editáveis aqui:
     * são definidos na criação (onboarding) e uma mudança exigiria revalidar
     * o CPF/CNPJ e a regra de entidade principal única por organização.
     */
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('legal_name')
                    ->label(fn (?LegalEntity $record): string => $record?->type === LegalEntityType::Individual
                        ? 'Nome completo'
                        : 'Razão social')
                    ->required()
                    ->maxLength(255),
                TextInput::make('trade_name')
                    ->label('Nome fantasia')
                    ->maxLength(255),
                TextInput::make('state_registration')
                    ->label('Inscrição estadual')
                    ->maxLength(255),
                TextInput::make('municipal_registration')
                    ->label('Inscrição municipal')
                    ->maxLength(255),
                TextInput::make('email')
                    ->label('E-mail')
                    ->email(),
                TextInput::make('phone')
                    ->label('Telefone')
                    ->tel(),
            ]);
    }
}
