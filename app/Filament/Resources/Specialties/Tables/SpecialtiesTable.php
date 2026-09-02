<?php

declare(strict_types=1);

namespace App\Filament\Resources\Specialties\Tables;

use App\Actions\Organization\ActivateSpecialtyAction;
use App\Actions\Organization\DeactivateSpecialtyAction;
use App\Actions\Organization\DeleteSpecialtyAction;
use App\Actions\Organization\RestoreSpecialtyAction;
use App\Enums\RecordStatus;
use App\Models\Specialty;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Validation\ValidationException;

class SpecialtiesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withoutGlobalScopes([SoftDeletingScope::class]))
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
                TextColumn::make('professional_links_count')
                    ->label('Profissionais')
                    ->counts('professionalLinks'),
                TextColumn::make('service_links_count')
                    ->label('Serviços')
                    ->counts('serviceLinks'),
                TextColumn::make('deleted_at')
                    ->label('Excluída em')
                    ->dateTime()
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(RecordStatus::class),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make()
                    ->visible(fn (Specialty $record) => ! $record->trashed()),
                Action::make('activate')
                    ->label('Ativar')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->visible(fn (Specialty $record) => ! $record->trashed() && $record->status !== RecordStatus::Active)
                    ->requiresConfirmation()
                    ->action(function (Specialty $record) {
                        app(ActivateSpecialtyAction::class)->handle($record);

                        Notification::make()->title('Especialidade ativada com sucesso.')->success()->send();
                    }),
                Action::make('deactivate')
                    ->label('Inativar')
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('danger')
                    ->visible(fn (Specialty $record) => ! $record->trashed() && $record->status === RecordStatus::Active)
                    ->requiresConfirmation()
                    ->action(function (Specialty $record) {
                        app(DeactivateSpecialtyAction::class)->handle($record);

                        Notification::make()->title('Especialidade inativada com sucesso.')->success()->send();
                    }),
                Action::make('delete')
                    ->label('Excluir')
                    ->icon(Heroicon::OutlinedTrash)
                    ->color('danger')
                    ->visible(fn (Specialty $record) => ! $record->trashed())
                    ->requiresConfirmation()
                    ->modalDescription('Este registro será removido da operação, mas seu histórico será preservado.')
                    ->action(function (Specialty $record) {
                        try {
                            app(DeleteSpecialtyAction::class)->handle($record);

                            Notification::make()->title('Especialidade excluída com sucesso.')->success()->send();
                        } catch (ValidationException $exception) {
                            Notification::make()
                                ->title(collect($exception->errors())->flatten()->first())
                                ->danger()
                                ->send();
                        }
                    }),
                Action::make('restore')
                    ->label('Restaurar')
                    ->icon(Heroicon::OutlinedArrowUturnLeft)
                    ->color('gray')
                    ->visible(fn (Specialty $record) => $record->trashed())
                    ->requiresConfirmation()
                    ->action(function (Specialty $record) {
                        try {
                            app(RestoreSpecialtyAction::class)->handle($record);

                            Notification::make()->title('Especialidade restaurada com sucesso.')->success()->send();
                        } catch (ValidationException $exception) {
                            Notification::make()
                                ->title(collect($exception->errors())->flatten()->first())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->toolbarActions([
                // Sem ações em massa: nenhuma exclusão física de dados de negócio.
            ]);
    }
}
