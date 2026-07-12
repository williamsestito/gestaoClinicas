<?php

declare(strict_types=1);

namespace App\Filament\Resources\OrganizationMemberships\Pages;

use App\Filament\Resources\OrganizationMemberships\OrganizationMembershipResource;
use Filament\Resources\Pages\ListRecords;

class ListOrganizationMemberships extends ListRecords
{
    protected static string $resource = OrganizationMembershipResource::class;

    protected function getHeaderActions(): array
    {
        // Sem CreateAction: vínculos nascem pelo onboarding (proprietário
        // inicial). Convite de novos membros é um fluxo de fase futura.
        return [];
    }
}
