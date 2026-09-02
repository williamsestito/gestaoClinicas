<?php

declare(strict_types=1);

namespace App\Filament\Resources\LegalEntities\Tables;

use App\Enums\LegalEntityType;
use App\Enums\RecordStatus;
use App\Models\LegalEntity;
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
                    ->label('Clínica')
                    ->searchable(),
                TextColumn::make('type')
                    ->label('Tipo')
                    ->badge(),
                TextColumn::make('document')
                    ->label('CPF/CNPJ')
                    ->searchable()
                    ->formatStateUsing(function (string $state, LegalEntity $record): string {
                        $digits = preg_replace('/\D/', '', $state) ?? '';

                        if ($record->type === LegalEntityType::Individual && strlen($digits) === 11) {
                            return '***.***.***-'.substr($digits, 9);
                        }

                        if ($record->type === LegalEntityType::Company && strlen($digits) === 14) {
                            return '**.***.***/****-'.substr($digits, 12);
                        }

                        return '****';
                    }),
                TextColumn::make('legal_name')
                    ->label('Razão social/Nome completo')
                    ->searchable(),
                IconColumn::make('is_primary')
                    ->label('Principal')
                    ->boolean(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
                TextColumn::make('created_at')
                    ->label('Criado em')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Tipo')
                    ->options(LegalEntityType::class),
                SelectFilter::make('status')
                    ->label('Status')
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
