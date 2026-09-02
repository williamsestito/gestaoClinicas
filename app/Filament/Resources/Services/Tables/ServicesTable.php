<?php

declare(strict_types=1);

namespace App\Filament\Resources\Services\Tables;

use App\Actions\Organization\ActivateServiceAction;
use App\Actions\Organization\DeactivateServiceAction;
use App\Actions\Organization\DeleteServiceAction;
use App\Actions\Organization\RestoreServiceAction;
use App\Enums\RecordStatus;
use App\Models\Service;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Validation\ValidationException;

class ServicesTable
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
                TextColumn::make('default_duration_minutes')
                    ->label('Duração (min)'),
                IconColumn::make('is_public')
                    ->label('Público')
                    ->boolean(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
                TextColumn::make('professional_links_count')
                    ->label('Profissionais')
                    ->counts('professionalLinks'),
                TextColumn::make('deleted_at')
                    ->label('Excluído em')
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
                    ->visible(fn (Service $record) => ! $record->trashed()),
                Action::make('activate')
                    ->label('Ativar')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->visible(fn (Service $record) => ! $record->trashed() && $record->status !== RecordStatus::Active)
                    ->requiresConfirmation()
                    ->action(function (Service $record) {
                        app(ActivateServiceAction::class)->handle($record);

                        Notification::make()->title('Serviço ativado com sucesso.')->success()->send();
                    }),
                Action::make('deactivate')
                    ->label('Inativar')
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('danger')
                    ->visible(fn (Service $record) => ! $record->trashed() && $record->status === RecordStatus::Active)
                    ->requiresConfirmation()
                    ->action(function (Service $record) {
                        app(DeactivateServiceAction::class)->handle($record);

                        Notification::make()->title('Serviço inativado com sucesso.')->success()->send();
                    }),
                Action::make('delete')
                    ->label('Excluir')
                    ->icon(Heroicon::OutlinedTrash)
                    ->color('danger')
                    ->visible(fn (Service $record) => ! $record->trashed())
                    ->requiresConfirmation()
                    ->modalDescription('Este registro será removido da operação, mas seu histórico será preservado.')
                    ->action(function (Service $record) {
                        try {
                            app(DeleteServiceAction::class)->handle($record);

                            Notification::make()->title('Serviço excluído com sucesso.')->success()->send();
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
                    ->visible(fn (Service $record) => $record->trashed())
                    ->requiresConfirmation()
                    ->action(function (Service $record) {
                        try {
                            app(RestoreServiceAction::class)->handle($record);

                            Notification::make()->title('Serviço restaurado com sucesso.')->success()->send();
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
