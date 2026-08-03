<?php

declare(strict_types=1);

namespace App\Filament\Resources\Specialties\Pages;

use App\Filament\Resources\Specialties\SpecialtyResource;
use Filament\Resources\Pages\ListRecords;

class ListSpecialties extends ListRecords
{
    protected static string $resource = SpecialtyResource::class;

    protected function getHeaderActions(): array
    {
        // Sem CreateAction: especialidades são criadas pela tela
        // settings/specialties (Inertia), que já reaproveita
        // CreateSpecialtyAction — evita duplicar a regra aqui.
        return [];
    }
}
