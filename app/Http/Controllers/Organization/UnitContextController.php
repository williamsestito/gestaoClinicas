<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organization;

use App\Actions\Organization\SetActiveUnitAction;
use App\Enums\RecordStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\SetActiveUnitRequest;
use App\Models\Unit;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class UnitContextController extends Controller
{
    public function edit(TenantContext $tenant): Response
    {
        // Nunca lista unidades inativas ou excluídas, mesmo com vínculo ativo.
        $units = $tenant->membership()
            ?->unitMemberships()
            ->with('unit')
            ->where('status', RecordStatus::Active)
            ->get()
            ->pluck('unit')
            ->filter(fn (?Unit $unit) => $unit?->status === RecordStatus::Active)
            ->values() ?? collect();

        return Inertia::render('context/UnitSelector', [
            'units' => $units,
        ]);
    }

    public function update(SetActiveUnitRequest $request, SetActiveUnitAction $action, TenantContext $tenant): RedirectResponse
    {
        $membership = $tenant->membership() ?? abort(403);
        $unit = Unit::query()->findOrFail((string) $request->validated('unit_id'));

        $action->handle($request, $membership, $unit);

        return to_route('dashboard');
    }
}
