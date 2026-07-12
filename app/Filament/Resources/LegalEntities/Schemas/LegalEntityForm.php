<?php

declare(strict_types=1);

namespace App\Filament\Resources\LegalEntities\Schemas;

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
                    ->required()
                    ->maxLength(255),
                TextInput::make('trade_name')
                    ->maxLength(255),
                TextInput::make('state_registration')
                    ->maxLength(255),
                TextInput::make('municipal_registration')
                    ->maxLength(255),
                TextInput::make('email')
                    ->email(),
                TextInput::make('phone')
                    ->tel(),
            ]);
    }
}
