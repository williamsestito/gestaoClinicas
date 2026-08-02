<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organization;

use App\Actions\Organization\ActivateServiceAction;
use App\Actions\Organization\CreateServiceAction;
use App\Actions\Organization\DeactivateServiceAction;
use App\Actions\Organization\DeleteServiceAction;
use App\Actions\Organization\RestoreServiceAction;
use App\Actions\Organization\UpdateServiceAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\CreateServiceRequest;
use App\Http\Requests\Organization\UpdateServiceRequest;
use App\Models\Service;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ServiceController extends Controller
{
    public function index(TenantContext $tenant): Response
    {
        $this->authorize('viewAny', [Service::class, $tenant->organization()]);

        $services = $tenant->organization()
            ->services()
            ->withTrashed()
            ->withCount('professionalLinks')
            ->with(['specialtyLinks.specialty:id,name'])
            ->orderBy('name')
            ->get()
            ->map(fn (Service $service) => [
                'id' => $service->id,
                'name' => $service->name,
                'code' => $service->code,
                'default_duration_minutes' => $service->default_duration_minutes,
                'default_price_cents' => $service->default_price_cents,
                'status' => $service->status->value,
                'is_public' => $service->is_public,
                'unit_availability_scope' => $service->unit_availability_scope->value,
                'specialties' => $service->specialtyLinks->map(fn ($link) => $link->specialty?->name)->filter()->values(),
                'professionals_count' => $service->professional_links_count,
                'deleted_at' => $service->deleted_at,
                'updated_at' => $service->updated_at,
            ]);

        return Inertia::render('settings/services/Index', [
            'services' => $services,
            'specialties' => $this->activeSpecialtyOptions($tenant),
            'units' => $this->activeUnitOptions($tenant),
        ]);
    }

    public function create(TenantContext $tenant): Response
    {
        $this->authorize('create', [Service::class, $tenant->organization()]);

        return Inertia::render('settings/services/Create', [
            'specialties' => $this->activeSpecialtyOptions($tenant),
            'units' => $this->activeUnitOptions($tenant),
        ]);
    }

    public function store(CreateServiceRequest $request, CreateServiceAction $action, TenantContext $tenant): RedirectResponse
    {
        $action->handle($tenant->organization(), $request->attributesForAction());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Serviço cadastrado com sucesso.']);

        return to_route('settings.services.index');
    }

    public function edit(Service $service, TenantContext $tenant): Response
    {
        $this->authorize('view', $service);

        $service->load('specialtyLinks', 'unitLinks');

        return Inertia::render('settings/services/Edit', [
            'service' => [
                'id' => $service->id,
                'name' => $service->name,
                'code' => $service->code,
                'description' => $service->description,
                'default_duration_minutes' => $service->default_duration_minutes,
                'buffer_before_minutes' => $service->buffer_before_minutes,
                'buffer_after_minutes' => $service->buffer_after_minutes,
                'default_price' => $service->default_price_cents !== null ? $service->default_price_cents / 100 : null,
                'color' => $service->color,
                'is_public' => $service->is_public,
                'requires_manual_confirmation' => $service->requires_manual_confirmation,
                'internal_notes' => $service->internal_notes,
                'unit_availability_scope' => $service->unit_availability_scope->value,
                'status' => $service->status->value,
                'specialty_ids' => $service->specialtyLinks->pluck('specialty_id')->values(),
                'unit_ids' => $service->unitLinks->pluck('unit_id')->values(),
            ],
            'specialties' => $this->activeSpecialtyOptions($tenant),
            'units' => $this->activeUnitOptions($tenant),
        ]);
    }

    public function update(UpdateServiceRequest $request, Service $service, UpdateServiceAction $action): RedirectResponse
    {
        $action->handle($service, $request->attributesForAction());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Serviço alterado com sucesso.']);

        return to_route('settings.services.index');
    }

    public function activate(Service $service, ActivateServiceAction $action): RedirectResponse
    {
        $this->authorize('activate', $service);

        $action->handle($service);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Serviço ativado com sucesso.']);

        return back();
    }

    public function deactivate(Service $service, DeactivateServiceAction $action): RedirectResponse
    {
        $this->authorize('deactivate', $service);

        $action->handle($service);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Serviço inativado com sucesso. Ele deixará de estar disponível para novas operações.']);

        return back();
    }

    public function destroy(Service $service, DeleteServiceAction $action): RedirectResponse
    {
        $this->authorize('delete', $service);

        $action->handle($service);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Serviço excluído com sucesso. Seu histórico foi preservado.']);

        return back();
    }

    public function restore(string $service, TenantContext $tenant, RestoreServiceAction $action): RedirectResponse
    {
        $entity = Service::withTrashed()->findOrFail($service);

        if (! $tenant->organization() || $entity->organization_id !== $tenant->organization()->id) {
            abort(404);
        }

        $this->authorize('restore', $entity);

        $action->handle($entity);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Serviço restaurado com sucesso.']);

        return back();
    }

    /** @return array<int, array{id: string, name: string}> */
    private function activeSpecialtyOptions(TenantContext $tenant): array
    {
        return $tenant->organization()
            ->specialties()
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($specialty) => ['id' => $specialty->id, 'name' => $specialty->name])
            ->all();
    }

    /** @return array<int, array{id: string, name: string, is_active: bool}> */
    private function activeUnitOptions(TenantContext $tenant): array
    {
        return $tenant->organization()
            ->units()
            ->orderBy('name')
            ->get(['id', 'name', 'status'])
            ->map(fn ($unit) => ['id' => $unit->id, 'name' => $unit->name, 'is_active' => $unit->status->value === 'active'])
            ->all();
    }
}
