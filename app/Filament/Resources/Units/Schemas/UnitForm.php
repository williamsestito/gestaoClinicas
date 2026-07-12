<?php

declare(strict_types=1);

namespace App\Filament\Resources\Units\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class UnitForm
{
    /**
     * organization_id, legal_entity_id, code, slug, status e is_headquarters
     * não são editáveis aqui — ver ações Ativar/Inativar na tabela e
     * UpdateUnitAction para os campos permitidos.
     */
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('timezone')
                    ->required(),
                TextInput::make('email')
                    ->email(),
                TextInput::make('phone')
                    ->tel(),
                TextInput::make('whatsapp'),
            ]);
    }
}
