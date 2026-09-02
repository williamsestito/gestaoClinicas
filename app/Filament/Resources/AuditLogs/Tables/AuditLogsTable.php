<?php

declare(strict_types=1);

namespace App\Filament\Resources\AuditLogs\Tables;

use App\Enums\AuditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AuditLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Data')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('actor.name')
                    ->label('Usuário')
                    ->placeholder('Sistema'),
                TextColumn::make('organization.name')
                    ->label('Clínica')
                    ->placeholder('—'),
                TextColumn::make('unit.name')
                    ->label('Unidade')
                    ->placeholder('—'),
                TextColumn::make('action')
                    ->label('Ação')
                    ->badge(),
                TextColumn::make('auditable_type')
                    ->label('Tipo do registro'),
                TextColumn::make('ip_address')
                    ->label('Endereço IP'),
            ])
            ->filters([
                SelectFilter::make('action')
                    ->label('Ação')
                    ->options(AuditAction::class),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([
                // Somente leitura: sem ações em massa, edição ou exclusão.
            ]);
    }
}
