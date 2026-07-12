<?php

declare(strict_types=1);

namespace App\Filament\Resources\AuditLogs\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class AuditLogInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('created_at')
                    ->label('Data')
                    ->dateTime(),
                TextEntry::make('actor.name')
                    ->label('Usuário')
                    ->placeholder('Sistema'),
                TextEntry::make('organization.name')
                    ->placeholder('—'),
                TextEntry::make('unit.name')
                    ->placeholder('—'),
                TextEntry::make('action')
                    ->badge(),
                TextEntry::make('auditable_type')
                    ->label('Recurso')
                    ->placeholder('—'),
                TextEntry::make('auditable_id')
                    ->label('ID do recurso')
                    ->placeholder('—'),
                TextEntry::make('before_data')
                    ->label('Antes')
                    ->placeholder('—'),
                TextEntry::make('after_data')
                    ->label('Depois')
                    ->placeholder('—'),
                TextEntry::make('ip_address')
                    ->placeholder('—'),
            ]);
    }
}
