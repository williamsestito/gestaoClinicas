<?php

declare(strict_types=1);

namespace App\Filament\Resources\LegalEntities\Pages;

use App\Filament\Resources\LegalEntities\LegalEntityResource;
use Filament\Resources\Pages\ListRecords;

class ListLegalEntities extends ListRecords
{
    protected static string $resource = LegalEntityResource::class;

    protected function getHeaderActions(): array
    {
        // Sem CreateAction: entidades legais nascem pelo onboarding, ou
        // futuramente por uma ação dedicada de "adicionar entidade legal".
        return [];
    }
}
