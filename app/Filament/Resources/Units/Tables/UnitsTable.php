<?php

declare(strict_types=1);

namespace App\Filament\Resources\Units\Tables;

use App\Actions\Organization\ChangeUnitStatusAction;
use App\Enums\RecordStatus;
use App\Models\Unit;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class UnitsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('organization.name')
                    ->searchable(),
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('code')
                    ->searchable(),
                TextColumn::make('status')
                    ->badge(),
                IconColumn::make('is_headquarters')
                    ->label('Matriz')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(RecordStatus::class),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('activate')
                    ->label('Ativar')
                    ->visible(fn (Unit $record) => $record->status !== RecordStatus::Active)
                    ->requiresConfirmation()
                    ->action(fn (Unit $record) => app(ChangeUnitStatusAction::class)
                        ->handle($record, RecordStatus::Active)),
                Action::make('deactivate')
                    ->label('Inativar')
                    ->visible(fn (Unit $record) => $record->status === RecordStatus::Active)
                    ->requiresConfirmation()
                    ->action(fn (Unit $record) => app(ChangeUnitStatusAction::class)
                        ->handle($record, RecordStatus::Inactive)),
            ])
            ->toolbarActions([
                // Sem ações em massa: nenhuma exclusão física de dados de negócio.
            ]);
    }
}
