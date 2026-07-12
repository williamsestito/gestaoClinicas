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
                    ->placeholder('—'),
                TextColumn::make('unit.name')
                    ->placeholder('—'),
                TextColumn::make('action')
                    ->badge(),
                TextColumn::make('auditable_type')
                    ->label('Recurso'),
                TextColumn::make('ip_address'),
            ])
            ->filters([
                SelectFilter::make('action')
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
