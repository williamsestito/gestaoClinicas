<?php

namespace App\Filament\Resources\OrganizationMemberships\Pages;

use App\Filament\Resources\OrganizationMemberships\OrganizationMembershipResource;
use Filament\Resources\Pages\ViewRecord;

class ViewOrganizationMembership extends ViewRecord
{
    protected static string $resource = OrganizationMembershipResource::class;

    // Sem EditAction: o status só muda através das Actions de domínio
    // disponíveis na tabela (ver OrganizationMembershipsTable).
    protected function getHeaderActions(): array
    {
        return [];
    }
}
