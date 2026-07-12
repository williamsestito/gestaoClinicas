<?php

namespace App\Filament\Resources\OrganizationMemberships\Pages;

use App\Filament\Resources\OrganizationMemberships\OrganizationMembershipResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewOrganizationMembership extends ViewRecord
{
    protected static string $resource = OrganizationMembershipResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
