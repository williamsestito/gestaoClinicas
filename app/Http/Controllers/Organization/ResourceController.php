<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organization;

use App\Actions\Organization\ActivateResourceAction;
use App\Actions\Organization\CreateResourceAction;
use App\Actions\Organization\DeactivateResourceAction;
use App\Actions\Organization\DeleteResourceAction;
use App\Actions\Organization\RestoreResourceAction;
use App\Actions\Organization\UpdateResourceAction;
use App\Enums\RecordStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\CreateResourceRequest;
use App\Http\Requests\Organization\UpdateResourceRequest;
use App\Models\SharedResource;
use App\Models\Unit;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ResourceController extends Controller
{
    public function index(TenantContext $tenant): Response
    {
        $this->authorize('viewAny', [SharedResource::class, $tenant->organization()]);

        $resources = $tenant->organization()
            ->resources()
            ->withTrashed()
            ->with('unit:id,name')
            ->withCount('appointments')
            ->orderBy('name')
            ->get()
            ->map(fn (SharedResource $resource) => [
                'id' => $resource->id,
                'unit_id' => $resource->unit_id,
                'unit_name' => $resource->unit->name,
                'name' => $resource->name,
                'type' => $resource->type,
                'status' => $resource->status->value,
                'appointments_count' => $resource->appointments_count,
                'deleted_at' => $resource->deleted_at,
                'updated_at' => $resource->updated_at,
            ]);

        return Inertia::render('settings/resources/Index', [
            'resources' => $resources,
            'units' => $tenant->organization()->units()->where('status', RecordStatus::Active)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function create(TenantContext $tenant): Response
    {
        $this->authorize('create', [SharedResource::class, $tenant->organization()]);

        return Inertia::render('settings/resources/Create', [
            'units' => $tenant->organization()->units()->where('status', RecordStatus::Active)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(CreateResourceRequest $request, CreateResourceAction $action, TenantContext $tenant): RedirectResponse
    {
        $unit = Unit::query()->where('organization_id', $tenant->organization()->id)->findOrFail((string) $request->validated('unit_id'));

        $action->handle($tenant->organization(), $unit, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Recurso cadastrado com sucesso.']);

        return to_route('settings.resources.index');
    }

    public function edit(SharedResource $resource, TenantContext $tenant): Response
    {
        $this->authorize('view', $resource);

        return Inertia::render('settings/resources/Edit', [
            'resource' => [
                'id' => $resource->id,
                'unit_id' => $resource->unit_id,
                'name' => $resource->name,
                'type' => $resource->type,
                'status' => $resource->status->value,
            ],
            'units' => $tenant->organization()->units()->where('status', RecordStatus::Active)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(UpdateResourceRequest $request, SharedResource $resource, UpdateResourceAction $action): RedirectResponse
    {
        $action->handle($resource, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Recurso alterado com sucesso.']);

        return to_route('settings.resources.index');
    }

    public function activate(SharedResource $resource, ActivateResourceAction $action): RedirectResponse
    {
        $this->authorize('activate', $resource);

        $action->handle($resource);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Recurso ativado com sucesso.']);

        return back();
    }

    public function deactivate(SharedResource $resource, DeactivateResourceAction $action): RedirectResponse
    {
        $this->authorize('deactivate', $resource);

        $action->handle($resource);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Recurso inativado com sucesso.']);

        return back();
    }

    public function destroy(SharedResource $resource, DeleteResourceAction $action): RedirectResponse
    {
        $this->authorize('delete', $resource);

        $action->handle($resource);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Recurso excluído com sucesso. Seu histórico foi preservado.']);

        return back();
    }

    public function restore(string $resource, TenantContext $tenant, RestoreResourceAction $action): RedirectResponse
    {
        $entity = SharedResource::withTrashed()->findOrFail($resource);

        if (! $tenant->organization() || $entity->organization_id !== $tenant->organization()->id) {
            abort(404);
        }

        $this->authorize('restore', $entity);

        $action->handle($entity);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Recurso restaurado com sucesso.']);

        return back();
    }
}
