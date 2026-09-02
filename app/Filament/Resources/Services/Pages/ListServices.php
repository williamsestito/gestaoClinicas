<?php

declare(strict_types=1);

namespace App\Filament\Resources\Services\Pages;

use App\Filament\Resources\Services\ServiceResource;
use Filament\Resources\Pages\ListRecords;

class ListServices extends ListRecords
{
    protected static string $resource = ServiceResource::class;

    protected function getHeaderActions(): array
    {
        // Sem CreateAction: serviços são criados pela tela settings/services
        // (Inertia), que já reaproveita CreateServiceAction.
        return [];
    }
}
