<?php

declare(strict_types=1);

namespace App\Filament\Resources\Professionals\Pages;

use App\Filament\Resources\Professionals\ProfessionalResource;
use Filament\Resources\Pages\ListRecords;

class ListProfessionals extends ListRecords
{
    protected static string $resource = ProfessionalResource::class;

    protected function getHeaderActions(): array
    {
        // Sem CreateAction: profissionais são criados pela tela
        // settings/professionals (Inertia), que já reaproveita
        // CreateProfessionalAction e trata vínculos com usuário/documento.
        return [];
    }
}
