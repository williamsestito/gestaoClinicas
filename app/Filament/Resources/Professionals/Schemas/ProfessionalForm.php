<?php

declare(strict_types=1);

namespace App\Filament\Resources\Professionals\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ProfessionalForm
{
    /**
     * organization_id, user_id e status não são editáveis aqui — vínculo
     * com usuário e ativação/inativação continuam pelas telas/ações
     * próprias, para não duplicar aquela regra de negócio no Filament.
     */
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nome completo')
                    ->required()
                    ->maxLength(255),
                TextInput::make('social_name')
                    ->label('Nome social')
                    ->maxLength(255),
                TextInput::make('display_name')
                    ->label('Nome de exibição')
                    ->required()
                    ->maxLength(255),
                TextInput::make('email')
                    ->label('E-mail')
                    ->email()
                    ->maxLength(255),
                TextInput::make('phone')
                    ->label('Telefone')
                    ->tel()
                    ->maxLength(20),
                TextInput::make('document')
                    ->label('CPF')
                    ->maxLength(20),
                DatePicker::make('birth_date')
                    ->label('Data de nascimento'),
                Textarea::make('bio')
                    ->label('Biografia')
                    ->maxLength(2000),
            ]);
    }
}
