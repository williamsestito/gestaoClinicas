<?php

declare(strict_types=1);

namespace App\Filament\Resources\Specialties\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class SpecialtyForm
{
    /**
     * organization_id e status não são editáveis aqui — status muda pelas
     * ações Ativar/Inativar da tabela, sempre via
     * App\Actions\Organization\{Activate,Deactivate}SpecialtyAction.
     */
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nome')
                    ->required()
                    ->maxLength(255),
                TextInput::make('code')
                    ->label('Código')
                    ->maxLength(50),
                Textarea::make('description')
                    ->label('Descrição')
                    ->maxLength(1000),
                TextInput::make('display_order')
                    ->label('Ordem de exibição')
                    ->numeric()
                    ->default(0),
            ]);
    }
}
