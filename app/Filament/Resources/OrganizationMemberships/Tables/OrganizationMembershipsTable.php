<?php

declare(strict_types=1);

namespace App\Filament\Resources\OrganizationMemberships\Tables;

use App\Actions\Organization\ActivateOrganizationMembershipAction;
use App\Actions\Organization\DeactivateOrganizationMembershipAction;
use App\Enums\OrganizationMembershipStatus;
use App\Models\OrganizationMembership;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Validation\ValidationException;

class OrganizationMembershipsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('organization.name')
                    ->label('Clínica')
                    ->searchable(),
                TextColumn::make('user.name')
                    ->label('Usuário')
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
                IconColumn::make('is_owner')
                    ->label('Proprietário')
                    ->boolean(),
                TextColumn::make('joined_at')
                    ->label('Vinculado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(OrganizationMembershipStatus::class),
            ])
            ->recordActions([
                ViewAction::make()->label('Visualizar'),
                Action::make('activate')
                    ->label('Ativar')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (OrganizationMembership $record) => $record->status !== OrganizationMembershipStatus::Active)
                    ->action(function (OrganizationMembership $record) {
                        app(ActivateOrganizationMembershipAction::class)->handle($record);

                        Notification::make()->title('Vínculo ativado com sucesso.')->success()->send();
                    }),
                Action::make('deactivate')
                    ->label('Inativar')
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('O usuário perderá acesso a esta clínica. O histórico é preservado.')
                    ->visible(fn (OrganizationMembership $record) => $record->status === OrganizationMembershipStatus::Active)
                    ->action(function (OrganizationMembership $record) {
                        try {
                            app(DeactivateOrganizationMembershipAction::class)->handle($record);

                            Notification::make()->title('Vínculo inativado com sucesso.')->success()->send();
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
