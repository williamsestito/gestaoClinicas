<?php

declare(strict_types=1);

namespace App\Filament\Resources\Services\Schemas;

use App\Enums\ServiceAvailabilityScope;
use App\Models\Service;
use App\Models\Specialty;
use App\Models\Unit;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ServiceForm
{
    /**
     * organization_id e status não são editáveis aqui. As opções de
     * especialidade/unidade são sempre filtradas pela mesma organização do
     * registro em edição — nunca confiamos no Select do Filament sem
     * revalidar (App\Actions\Organization\UpdateServiceAction revalida de
     * novo cada id recebido antes de gravar).
     */
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nome')
                    ->required()
                    ->maxLength(255),
                TextInput::make('code')
                    ->label('Código')
                    ->required()
                    ->maxLength(50),
                Textarea::make('description')
                    ->label('Descrição')
                    ->maxLength(2000),
                TextInput::make('default_duration_minutes')
                    ->label('Duração padrão (min)')
                    ->numeric()
                    ->required()
                    ->minValue(1),
                TextInput::make('buffer_before_minutes')
                    ->label('Intervalo antes (min)')
                    ->numeric()
                    ->default(0),
                TextInput::make('buffer_after_minutes')
                    ->label('Intervalo depois (min)')
                    ->numeric()
                    ->default(0),
                TextInput::make('default_price')
                    ->label('Preço praticado (R$)')
                    ->numeric()
                    ->prefix('R$')
                    ->afterStateHydrated(function (TextInput $component, ?Service $record) {
                        $component->state($record?->default_price_cents !== null ? $record->default_price_cents / 100 : null);
                    }),
                TextInput::make('cost')
                    ->label('Custo estimado (R$)')
                    ->numeric()
                    ->prefix('R$')
                    ->afterStateHydrated(function (TextInput $component, ?Service $record) {
                        $component->state($record?->cost_cents !== null ? $record->cost_cents / 100 : null);
                    }),
                TextInput::make('margin_percentage')
                    ->label('Margem desejada (%)')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(1000),
                TextInput::make('max_discount_percentage')
                    ->label('Desconto máximo sem aprovação (%)')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(100),
                TextInput::make('color')
                    ->label('Cor')
                    ->maxLength(20),
                Toggle::make('is_public')
                    ->label('Exibição pública habilitada'),
                Toggle::make('requires_manual_confirmation')
                    ->label('Exige confirmação manual'),
                Textarea::make('internal_notes')
                    ->label('Observações internas')
                    ->helperText('Nunca exibidas publicamente nem copiadas para o site.')
                    ->maxLength(2000),
                Select::make('unit_availability_scope')
                    ->label('Disponibilidade por unidade')
                    ->options(ServiceAvailabilityScope::class)
                    ->required()
                    ->live(),
                CheckboxList::make('specialty_ids')
                    ->label('Especialidades')
                    ->options(fn (?Service $record) => $record === null ? [] : Specialty::query()
                        ->where('organization_id', $record->organization_id)
                        ->orderBy('name')
                        ->pluck('name', 'id'))
                    ->afterStateHydrated(fn (CheckboxList $component, ?Service $record) => $component->state(
                        $record?->specialtyLinks()->pluck('specialty_id')->all() ?? []
                    )),
                CheckboxList::make('unit_ids')
                    ->label('Unidades selecionadas')
                    ->visible(fn ($get) => $get('unit_availability_scope') === 'selected_units')
                    ->options(fn (?Service $record) => $record === null ? [] : Unit::query()
                        ->where('organization_id', $record->organization_id)
                        ->orderBy('name')
                        ->pluck('name', 'id'))
                    ->afterStateHydrated(fn (CheckboxList $component, ?Service $record) => $component->state(
                        $record?->unitLinks()->pluck('unit_id')->all() ?? []
                    )),
            ]);
    }
}
