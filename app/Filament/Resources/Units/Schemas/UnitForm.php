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
                    ->label('Nome')
                    ->required()
                    ->maxLength(255),
                TextInput::make('timezone')
                    ->label('Fuso horário')
                    ->required(),
                TextInput::make('email')
                    ->label('E-mail')
                    ->email(),
                TextInput::make('phone')
                    ->label('Telefone')
                    ->tel(),
                TextInput::make('whatsapp')
                    ->label('WhatsApp'),
            ]);
    }
}
