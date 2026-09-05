<?php

declare(strict_types=1);

namespace App\Filament\Resources\Organizations\Pages;

use App\Filament\Resources\Organizations\OrganizationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListOrganizations extends ListRecords
{
    protected static string $resource = OrganizationResource::class;

    protected function getHeaderActions(): array
    {
        // Organizações nascem pelo onboarding self-service (o próprio dono
        // se cadastra) OU pelo bootstrap do platform admin aqui (ver
        // App\Actions\Organization\BootstrapOrganizationAction) — nunca por
        // um Organization::create() genérico do Filament.
        return [
            CreateAction::make()->label('Criar organização'),
        ];
    }
}
