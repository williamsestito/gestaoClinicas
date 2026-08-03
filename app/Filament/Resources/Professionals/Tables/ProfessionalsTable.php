<?php

declare(strict_types=1);

namespace App\Filament\Resources\Professionals\Tables;

use App\Actions\Organization\ActivateProfessionalAction;
use App\Actions\Organization\DeactivateProfessionalAction;
use App\Actions\Organization\DeleteProfessionalAction;
use App\Actions\Organization\RestoreProfessionalAction;
use App\Enums\LegalEntityType;
use App\Enums\RecordStatus;
use App\Models\Professional;
use App\Support\Documents\Document;
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
use InvalidArgumentException;

class ProfessionalsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withoutGlobalScopes([SoftDeletingScope::class]))
            ->columns([
                TextColumn::make('organization.name')
                    ->label('Clínica')
                    ->searchable(),
                TextColumn::make('display_name')
                    ->label('Nome de exibição')
                    ->searchable(),
                TextColumn::make('document')
                    ->label('CPF')
                    ->formatStateUsing(function (?string $state) {
                        if ($state === null || $state === '') {
                            return '-';
                        }

                        try {
                            return Document::fromType(LegalEntityType::Individual, $state)->masked();
                        } catch (InvalidArgumentException) {
                            return '****';
                        }
                    }),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
                TextColumn::make('unit_links_count')
                    ->label('Unidades')
                    ->counts('unitLinks'),
                TextColumn::make('deleted_at')
                    ->label('Excluído em')
                    ->dateTime()
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('organization_id')
                    ->label('Clínica')
                    ->relationship('organization', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(RecordStatus::class),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make()
                    ->visible(fn (Professional $record) => ! $record->trashed()),
                Action::make('activate')
                    ->label('Ativar')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->visible(fn (Professional $record) => ! $record->trashed() && $record->status !== RecordStatus::Active)
                    ->requiresConfirmation()
                    ->action(function (Professional $record) {
                        app(ActivateProfessionalAction::class)->handle($record);

                        Notification::make()->title('Profissional ativado com sucesso.')->success()->send();
                    }),
                Action::make('deactivate')
                    ->label('Inativar')
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('danger')
                    ->visible(fn (Professional $record) => ! $record->trashed() && $record->status === RecordStatus::Active)
                    ->requiresConfirmation()
                    ->action(function (Professional $record) {
                        app(DeactivateProfessionalAction::class)->handle($record);

                        Notification::make()->title('Profissional inativado com sucesso.')->success()->send();
                    }),
                Action::make('delete')
                    ->label('Excluir')
                    ->icon(Heroicon::OutlinedTrash)
                    ->color('danger')
                    ->visible(fn (Professional $record) => ! $record->trashed())
                    ->requiresConfirmation()
                    ->modalDescription('Este registro será removido da operação, mas seu histórico será preservado. O usuário vinculado, se houver, não é afetado.')
                    ->action(function (Professional $record) {
                        app(DeleteProfessionalAction::class)->handle($record);

                        Notification::make()->title('Profissional excluído com sucesso.')->success()->send();
                    }),
                Action::make('restore')
                    ->label('Restaurar')
                    ->icon(Heroicon::OutlinedArrowUturnLeft)
                    ->color('gray')
                    ->visible(fn (Professional $record) => $record->trashed())
                    ->requiresConfirmation()
                    ->action(function (Professional $record) {
                        try {
                            app(RestoreProfessionalAction::class)->handle($record);

                            Notification::make()->title('Profissional restaurado com sucesso.')->success()->send();
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
