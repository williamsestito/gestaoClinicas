<?php

declare(strict_types=1);

namespace App\Filament\Resources\Organizations\Pages;

use App\Filament\Resources\Organizations\OrganizationResource;
use Filament\Resources\Pages\ListRecords;

class ListOrganizations extends ListRecords
{
    protected static string $resource = OrganizationResource::class;

    protected function getHeaderActions(): array
    {
        // Sem CreateAction: organizações só nascem pelo onboarding
        // (organização + entidade legal + unidade matriz + proprietário
        // em uma única transação — ver OnboardOrganizationAction).
        return [];
    }
}
