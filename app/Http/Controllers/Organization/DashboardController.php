<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organization;

use App\Http\Controllers\Controller;
use App\Support\Tenancy\TenantContext;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(TenantContext $tenant): Response
    {
        $organization = $tenant->organization();
        $primaryLegalEntity = $organization?->primaryLegalEntity()->first();

        return Inertia::render('Dashboard', [
            'appEnvironment' => app()->environment(),
            'unitsCount' => $organization?->units()->count() ?? 0,
            'primaryLegalEntity' => $primaryLegalEntity ? [
                'legal_name' => $primaryLegalEntity->legal_name,
                'trade_name' => $primaryLegalEntity->trade_name,
            ] : null,
        ]);
    }
}
