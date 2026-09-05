<?php

declare(strict_types=1);

namespace App\Filament\Resources\Organizations\Pages;

use App\Actions\Organization\BootstrapOrganizationAction;
use App\Data\Organization\BootstrapOrganizationData;
use App\Enums\LegalEntityType;
use App\Filament\Resources\Organizations\OrganizationResource;
use App\Models\Organization;
use App\Models\User;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Wizard\Step;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Bootstrap de produção de uma organização real pelo platform admin — nunca
 * usa dados fake/seeders demo (ver App\Actions\Organization\
 * BootstrapOrganizationAction, que reaproveita as mesmas Actions de domínio
 * do onboarding self-service).
 */
class CreateOrganization extends CreateRecord
{
    use CreateRecord\Concerns\HasWizard;

    protected static string $resource = OrganizationResource::class;

    /** @return array<int, Step> */
    protected function getSteps(): array
    {
        return [
            Step::make('organization')
                ->label('Organização')
                ->schema([
                    TextInput::make('organization_name')
                        ->label('Nome da organização')
                        ->required()
                        ->maxLength(255),
                ]),

            Step::make('legal_entity')
                ->label('Entidade legal')
                ->schema([
                    Radio::make('legal_entity_type')
                        ->label('Tipo')
                        ->options(LegalEntityType::class)
                        ->default(LegalEntityType::Individual)
                        ->required()
                        ->live(),
                    TextInput::make('document')
                        ->label('CPF/CNPJ')
                        ->required()
                        ->maxLength(18),
                    TextInput::make('legal_name')
                        ->label(fn ($get) => $get('legal_entity_type') === LegalEntityType::Company->value
                            ? 'Razão social'
                            : 'Nome completo')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('trade_name')
                        ->label('Nome fantasia')
                        ->maxLength(255),
                    TextInput::make('legal_entity_email')
                        ->label('E-mail')
                        ->email(),
                    TextInput::make('legal_entity_phone')
                        ->label('Telefone')
                        ->tel(),
                ]),

            Step::make('headquarters')
                ->label('Unidade matriz')
                ->schema([
                    TextInput::make('unit_name')
                        ->label('Nome da unidade')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('unit_phone')
                        ->label('Telefone')
                        ->tel(),
                    TextInput::make('unit_whatsapp')
                        ->label('WhatsApp'),
                    TextInput::make('address.postal_code')
                        ->label('CEP')
                        ->required()
                        ->maxLength(9),
                    TextInput::make('address.street')
                        ->label('Logradouro')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('address.number')
                        ->label('Número')
                        ->required()
                        ->maxLength(20),
                    TextInput::make('address.complement')
                        ->label('Complemento')
                        ->maxLength(255),
                    TextInput::make('address.neighborhood')
                        ->label('Bairro')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('address.city')
                        ->label('Cidade')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('address.state')
                        ->label('UF')
                        ->required()
                        ->maxLength(2),
                ]),

            Step::make('administrator')
                ->label('Administrador')
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
                    TextInput::make('invite_name')
                        ->label('Nome do administrador')
                        ->visible(fn ($get) => $get('owner_mode') === 'invite')
                        ->required(fn ($get) => $get('owner_mode') === 'invite')
                        ->maxLength(255),
                    TextInput::make('invite_email')
                        ->label('E-mail do administrador')
                        ->email()
                        ->visible(fn ($get) => $get('owner_mode') === 'invite')
                        ->required(fn ($get) => $get('owner_mode') === 'invite')
                        ->maxLength(255),
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
                ]),
        ];
    }

    /** @param  array<string, mixed>  $data */
    protected function handleRecordCreation(array $data): Model
    {
        /** @var Organization $organization */
        $organization = app(BootstrapOrganizationAction::class)->handle(
            Auth::guard('web')->user(),
            BootstrapOrganizationData::fromArray($data),
        );

        return $organization;
    }
}
