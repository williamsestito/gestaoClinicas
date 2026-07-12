<?php

declare(strict_types=1);

namespace App\Filament\Resources\Organizations\Tables;

use App\Actions\Organization\ChangeOrganizationStatusAction;
use App\Enums\OrganizationStatus;
use App\Models\Organization;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
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
                    ->searchable(),
                TextColumn::make('slug')
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                TextColumn::make('primaryLegalEntity.legal_name')
                    ->label('Entidade principal')
                    ->placeholder('—'),
                TextColumn::make('headquarters.name')
                    ->label('Unidade matriz')
                    ->placeholder('—'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(OrganizationStatus::class),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('activate')
                    ->label('Ativar')
                    ->visible(fn (Organization $record) => $record->status !== OrganizationStatus::Active)
                    ->requiresConfirmation()
                    ->action(fn (Organization $record) => app(ChangeOrganizationStatusAction::class)
                        ->handle($record, OrganizationStatus::Active)),
                Action::make('suspend')
                    ->label('Suspender')
                    ->visible(fn (Organization $record) => $record->status !== OrganizationStatus::Suspended)
                    ->requiresConfirmation()
                    ->action(fn (Organization $record) => app(ChangeOrganizationStatusAction::class)
                        ->handle($record, OrganizationStatus::Suspended)),
            ])
            ->toolbarActions([
                // Sem ações em massa: nenhuma exclusão física de dados de negócio.
            ]);
    }
}
