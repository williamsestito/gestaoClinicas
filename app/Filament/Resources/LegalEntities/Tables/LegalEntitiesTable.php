<?php

declare(strict_types=1);

namespace App\Filament\Resources\LegalEntities\Tables;

use App\Enums\LegalEntityType;
use App\Enums\RecordStatus;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class LegalEntitiesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('organization.name')
                    ->searchable(),
                TextColumn::make('type')
                    ->badge(),
                TextColumn::make('document')
                    ->searchable(),
                TextColumn::make('legal_name')
                    ->searchable(),
                IconColumn::make('is_primary')
                    ->boolean(),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options(LegalEntityType::class),
                SelectFilter::make('status')
                    ->options(RecordStatus::class),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                // Sem ações em massa: nenhuma exclusão física de dados de negócio.
            ]);
    }
}
