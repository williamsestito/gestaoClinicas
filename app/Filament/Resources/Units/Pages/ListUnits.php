<?php

declare(strict_types=1);

namespace App\Filament\Resources\Units\Pages;

use App\Filament\Resources\Units\UnitResource;
use Filament\Resources\Pages\ListRecords;

class ListUnits extends ListRecords
{
    protected static string $resource = UnitResource::class;

    protected function getHeaderActions(): array
    {
        // Sem CreateAction: unidades são criadas via onboarding ou pela
        // tela de settings/units (Inertia), que também cadastram endereço
        // e horários — evitando duplicar essa regra aqui.
        return [];
    }
}
