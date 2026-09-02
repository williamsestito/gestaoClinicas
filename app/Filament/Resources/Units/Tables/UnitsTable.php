<?php

declare(strict_types=1);

namespace App\Filament\Resources\Units\Tables;

use App\Actions\Organization\ChangeUnitStatusAction;
use App\Enums\RecordStatus;
use App\Models\Unit;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
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
                    ->label('Clínica')
                    ->searchable(),
                TextColumn::make('name')
                    ->label('Nome')
                    ->searchable(),
                TextColumn::make('code')
                    ->label('Código')
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
                IconColumn::make('is_headquarters')
                    ->label('Matriz')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->label('Criado em')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(RecordStatus::class),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('activate')
                    ->label('Ativar')
                    ->visible(fn (Unit $record) => $record->status !== RecordStatus::Active)
                    ->requiresConfirmation()
                    ->action(function (Unit $record) {
                        app(ChangeUnitStatusAction::class)->handle($record, RecordStatus::Active);

                        Notification::make()->title('Unidade ativada com sucesso.')->success()->send();
                    }),
                Action::make('deactivate')
                    ->label('Inativar')
                    ->visible(fn (Unit $record) => $record->status === RecordStatus::Active)
                    ->requiresConfirmation()
                    ->action(function (Unit $record) {
                        app(ChangeUnitStatusAction::class)->handle($record, RecordStatus::Inactive);

                        Notification::make()->title('Unidade inativada com sucesso.')->success()->send();
                    }),
            ])
            ->toolbarActions([
                // Sem ações em massa: nenhuma exclusão física de dados de negócio.
            ]);
    }
}
