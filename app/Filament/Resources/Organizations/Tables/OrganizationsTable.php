<?php

declare(strict_types=1);

namespace App\Filament\Resources\Organizations\Tables;

use App\Actions\Organization\ChangeOrganizationStatusAction;
use App\Actions\Organization\SetActiveOrganizationAction;
use App\Actions\Organization\SetActiveUnitAction;
use App\Actions\Organization\SetOrganizationOwnerAction;
use App\Enums\OrganizationStatus;
use App\Models\Organization;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

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
                IconColumn::make('without_active_owner')
                    ->label('Sem administrador')
                    ->state(fn (Organization $record) => Organization::query()
                        ->whereKey($record->id)
                        ->withoutActiveOwner()
                        ->exists())
                    ->boolean()
                    ->trueIcon(Heroicon::ExclamationTriangle)
                    ->falseIcon(Heroicon::CheckCircle)
                    ->trueColor('danger')
                    ->falseColor('success'),
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
                Action::make('enter')
                    ->label('Acessar')
                    ->icon(Heroicon::ArrowRightCircle)
                    ->visible(fn (Organization $record) => $record->status === OrganizationStatus::Active)
                    ->action(function (Organization $record) {
                        // Reaproveita as mesmas Actions do seletor de
                        // organização comum (SetActiveOrganizationAction já
                        // concede um vínculo real ao platform admin na
                        // primeira vez, sem exigir convite prévio).
                        $user = Auth::guard('web')->user();
                        $request = request();

                        app(SetActiveOrganizationAction::class)->handle($request, $user, $record);

                        $membership = $user->organizationMemberships()
                            ->where('organization_id', $record->id)
                            ->firstOrFail();

                        $headquarters = $record->headquarters()->first();

                        if ($headquarters) {
                            app(SetActiveUnitAction::class)->handle($request, $membership, $headquarters);
                        }
                    })
                    ->successRedirectUrl(fn () => route('dashboard')),
                Action::make('setOwner')
                    ->label('Definir administrador')
                    ->icon(Heroicon::UserPlus)
                    ->visible(fn (Organization $record) => Organization::query()
                        ->whereKey($record->id)
                        ->withoutActiveOwner()
                        ->exists())
                    ->schema([
                        Radio::make('owner_mode')
                            ->label('Como definir o administrador')
                            ->options([
                                'invite' => 'Convidar por e-mail (define a própria senha)',
                                'existing' => 'Vincular um usuário já existente',
                            ])
                            ->default('invite')
                            ->required()
                            ->live(),
                        TextInput::make('invite_email')
                            ->label('E-mail do administrador')
                            ->email()
                            ->visible(fn ($get) => $get('owner_mode') === 'invite')
                            ->required(fn ($get) => $get('owner_mode') === 'invite'),
                        Select::make('existing_owner_user_id')
                            ->label('Usuário existente')
                            ->visible(fn ($get) => $get('owner_mode') === 'existing')
                            ->required(fn ($get) => $get('owner_mode') === 'existing')
                            ->searchable()
                            ->getSearchResultsUsing(fn (string $search) => User::query()
                                ->where(fn ($query) => $query->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"))
                                ->limit(50)
                                ->pluck('name', 'id'))
                            ->getOptionLabelUsing(fn (string $value) => User::query()->find($value)?->name),
                    ])
                    ->action(function (Organization $record, array $data) {
                        app(SetOrganizationOwnerAction::class)->handle(
                            $record,
                            Auth::guard('web')->user(),
                            $data['owner_mode'] === 'existing' ? $data['existing_owner_user_id'] : null,
                            $data['owner_mode'] === 'invite' ? $data['invite_email'] : null,
                        );

                        Notification::make()->title('Administrador definido com sucesso.')->success()->send();
                    }),
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
            ])
            ->emptyStateHeading('Nenhuma organização cadastrada')
            ->emptyStateDescription('Crie a primeira organização para iniciar a operação da plataforma.')
            ->emptyStateActions([
                CreateAction::make()->label('Criar organização'),
            ]);
    }
}
