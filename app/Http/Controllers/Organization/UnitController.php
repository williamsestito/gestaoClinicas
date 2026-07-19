<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organization;

use App\Actions\Organization\ChangeUnitStatusAction;
use App\Actions\Organization\CreateUnitAction;
use App\Actions\Organization\DeleteUnitAction;
use App\Actions\Organization\RestoreUnitAction;
use App\Actions\Organization\SetHeadquartersUnitAction;
use App\Actions\Organization\UpdateUnitAction;
use App\Data\Organization\AddressData;
use App\Data\Organization\OpeningHourData;
use App\Enums\RecordStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\CreateUnitRequest;
use App\Http\Requests\Organization\UpdateUnitRequest;
use App\Models\Unit;
use App\Support\Documents\BrazilianState;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UnitController extends Controller
{
    public function index(TenantContext $tenant): Response
    {
        return Inertia::render('settings/units/Index', [
            'units' => $tenant->organization()->units()
                ->with(['address', 'openingHours'])
                ->withTrashed()
                ->orderBy('name')
                ->get(),
            'states' => BrazilianState::codes(),
        ]);
    }

    public function create(TenantContext $tenant): Response
    {
        $this->authorize('create', [Unit::class, $tenant->organization()]);

        return Inertia::render('settings/units/Create', [
            'legalEntity' => $tenant->organization()->primaryLegalEntity()->first(),
            'states' => BrazilianState::codes(),
        ]);
    }

    public function store(CreateUnitRequest $request, CreateUnitAction $action, TenantContext $tenant): RedirectResponse
    {
        $data = $request->validated();
        $organization = $tenant->organization();

        $openingHours = [];
        foreach ($data['opening_hours'] ?? [] as $index => $hour) {
            $openingHours[] = OpeningHourData::fromArray($hour, $index);
        }

        $action->handle(
            organization: $organization,
            legalEntity: $organization->primaryLegalEntity()->firstOrFail(),
            name: $data['name'],
            code: $data['code'] ?? null,
            phone: $data['phone'] ?? null,
            whatsapp: $data['whatsapp'] ?? null,
            address: AddressData::fromArray($data['address']),
            openingHours: $openingHours,
            grantAccessTo: $tenant->membership(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Unidade criada com sucesso.']);

        return to_route('settings.units.index');
    }

    public function edit(Unit $unit): Response
    {
        $this->authorize('view', $unit);

        return Inertia::render('settings/units/Edit', [
            'unit' => $unit->load(['address', 'openingHours']),
        ]);
    }

    public function update(UpdateUnitRequest $request, Unit $unit, UpdateUnitAction $action): RedirectResponse
    {
        $action->handle($unit, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Unidade atualizada com sucesso.']);

        return to_route('settings.units.index');
    }

    public function updateStatus(Request $request, Unit $unit, TenantContext $tenant, ChangeUnitStatusAction $action): RedirectResponse
    {
        if (! $tenant->organization() || $unit->organization_id !== $tenant->organization()->id) {
            abort(404);
        }

        $this->authorize('update', $unit);

        $status = $request->boolean('active') ? RecordStatus::Active : RecordStatus::Inactive;
        $action->handle($unit, $status);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $status === RecordStatus::Active
                ? 'Unidade ativada com sucesso.'
                : 'Unidade inativada com sucesso.',
        ]);

        return back();
    }

    public function makeHeadquarters(Unit $unit, SetHeadquartersUnitAction $action): RedirectResponse
    {
        $this->authorize('update', $unit);

        $action->handle($unit);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Unidade definida como matriz.']);

        return back();
    }

    public function destroy(Unit $unit, DeleteUnitAction $action): RedirectResponse
    {
        $this->authorize('delete', $unit);

        $action->handle($unit);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Unidade excluída com sucesso. Seu histórico foi preservado.']);

        return back();
    }

    public function restore(int|string $unit, TenantContext $tenant, RestoreUnitAction $action): RedirectResponse
    {
        $model = Unit::withTrashed()->findOrFail($unit);

        if (! $tenant->organization() || $model->organization_id !== $tenant->organization()->id) {
            abort(404);
        }

        $this->authorize('restore', $model);

        $action->handle($model);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Unidade restaurada com sucesso.']);

        return back();
    }
}
