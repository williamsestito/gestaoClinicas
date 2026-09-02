<?php

declare(strict_types=1);

namespace App\Filament\Resources\Organizations\Tables;

use App\Actions\Organization\ChangeOrganizationStatusAction;
use App\Enums\OrganizationStatus;
use App\Models\Organization;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class OrganizationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nome')
                    ->searchable(),
                TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->sortable(),
                TextColumn::make('primaryLegalEntity.legal_name')
                    ->label('Entidade principal')
                    ->placeholder('—'),
                TextColumn::make('headquarters.name')
                    ->label('Unidade matriz')
                    ->placeholder('—'),
                TextColumn::make('created_at')
                    ->label('Criado em')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(OrganizationStatus::class),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('activate')
                    ->label('Ativar')
                    ->visible(fn (Organization $record) => $record->status !== OrganizationStatus::Active)
                    ->requiresConfirmation()
                    ->action(function (Organization $record) {
                        app(ChangeOrganizationStatusAction::class)->handle($record, OrganizationStatus::Active);

                        Notification::make()->title('Clínica ativada com sucesso.')->success()->send();
                    }),
                Action::make('suspend')
                    ->label('Suspender')
                    ->visible(fn (Organization $record) => $record->status !== OrganizationStatus::Suspended)
                    ->requiresConfirmation()
                    ->action(function (Organization $record) {
                        app(ChangeOrganizationStatusAction::class)->handle($record, OrganizationStatus::Suspended);

                        Notification::make()->title('Clínica suspensa com sucesso.')->success()->send();
                    }),
            ])
            ->toolbarActions([
                // Sem ações em massa: nenhuma exclusão física de dados de negócio.
            ]);
    }
}
