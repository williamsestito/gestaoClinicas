<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organization;

use App\Actions\Organization\CreateUnitAction;
use App\Actions\Organization\UpdateUnitAction;
use App\Data\Organization\AddressData;
use App\Data\Organization\OpeningHourData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\CreateUnitRequest;
use App\Http\Requests\Organization\UpdateUnitRequest;
use App\Models\Unit;
use App\Support\Documents\BrazilianState;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class UnitController extends Controller
{
    public function index(TenantContext $tenant): Response
    {
        return Inertia::render('settings/units/Index', [
            'units' => $tenant->organization()->units()->orderBy('name')->get(),
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
        );

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

        return to_route('settings.units.index');
    }
}
