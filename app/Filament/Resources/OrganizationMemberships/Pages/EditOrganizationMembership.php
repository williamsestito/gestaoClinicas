<?php

declare(strict_types=1);

namespace App\Filament\Resources\OrganizationMemberships\Pages;

use App\Filament\Resources\OrganizationMemberships\OrganizationMembershipResource;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditOrganizationMembership extends EditRecord
{
    protected static string $resource = OrganizationMembershipResource::class;

    protected function getHeaderActions(): array
    {
        // Sem DeleteAction: nenhuma exclusão física de dados de negócio.
        return [
            ViewAction::make(),
        ];
    }
}
