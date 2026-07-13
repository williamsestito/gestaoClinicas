<?php

namespace App\Filament\Resources\OrganizationMemberships\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class OrganizationMembershipInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('id')
                    ->label('ID'),
                TextEntry::make('organization.name')
                    ->label('Clínica'),
                TextEntry::make('user.name')
                    ->label('Usuário'),
                TextEntry::make('status')
                    ->label('Status')
                    ->badge(),
                IconEntry::make('is_owner')
                    ->label('Proprietário')
                    ->boolean(),
                TextEntry::make('joined_at')
                    ->label('Vinculado em')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('created_by')
                    ->label('Criado por')
                    ->numeric()
                    ->placeholder('-'),
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
