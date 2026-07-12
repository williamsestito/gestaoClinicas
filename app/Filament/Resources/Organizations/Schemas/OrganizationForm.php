<?php

declare(strict_types=1);

namespace App\Filament\Resources\Organizations\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class OrganizationForm
{
    /**
     * Slug e status não são editáveis diretamente aqui: slug é definido na
     * criação (via onboarding) e status é alterado pelas ações
     * Ativar/Suspender/Inativar da tabela (que reutilizam
     * ChangeOrganizationStatusAction e geram auditoria).
     */
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('default_timezone')
                    ->required(),
                TextInput::make('default_currency')
                    ->required()
                    ->maxLength(3),
                TextInput::make('locale')
                    ->required()
                    ->maxLength(10),
                TextInput::make('primary_color'),
                TextInput::make('secondary_color'),
            ]);
    }
}
