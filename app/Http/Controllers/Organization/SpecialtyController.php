<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organization;

use App\Actions\Organization\ActivateSpecialtyAction;
use App\Actions\Organization\CreateSpecialtyAction;
use App\Actions\Organization\DeactivateSpecialtyAction;
use App\Actions\Organization\DeleteSpecialtyAction;
use App\Actions\Organization\RestoreSpecialtyAction;
use App\Actions\Organization\UpdateSpecialtyAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\CreateSpecialtyRequest;
use App\Http\Requests\Organization\UpdateSpecialtyRequest;
use App\Models\Specialty;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SpecialtyController extends Controller
{
    public function index(TenantContext $tenant): Response
    {
        $this->authorize('viewAny', [Specialty::class, $tenant->organization()]);

        $specialties = $tenant->organization()
            ->specialties()
            ->withTrashed()
            ->withCount(['professionalLinks', 'serviceLinks'])
            ->orderBy('display_order')
            ->orderBy('name')
            ->get()
            ->map(fn (Specialty $specialty) => [
                'id' => $specialty->id,
                'name' => $specialty->name,
                'code' => $specialty->code,
                'description' => $specialty->description,
                'status' => $specialty->status->value,
                'display_order' => $specialty->display_order,
                'professionals_count' => $specialty->professional_links_count,
                'services_count' => $specialty->service_links_count,
                'deleted_at' => $specialty->deleted_at,
                'updated_at' => $specialty->updated_at,
            ]);

        return Inertia::render('settings/specialties/Index', [
            'specialties' => $specialties,
        ]);
    }

    public function create(TenantContext $tenant): Response
    {
        $this->authorize('create', [Specialty::class, $tenant->organization()]);

        return Inertia::render('settings/specialties/Create');
    }

    public function store(CreateSpecialtyRequest $request, CreateSpecialtyAction $action, TenantContext $tenant): RedirectResponse
    {
        $action->handle($tenant->organization(), $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Especialidade cadastrada com sucesso.']);

        return to_route('settings.specialties.index');
    }

    public function edit(Specialty $specialty): Response
    {
        $this->authorize('view', $specialty);

        return Inertia::render('settings/specialties/Edit', [
            'specialty' => [
                'id' => $specialty->id,
                'name' => $specialty->name,
                'code' => $specialty->code,
                'description' => $specialty->description,
                'status' => $specialty->status->value,
                'display_order' => $specialty->display_order,
            ],
        ]);
    }

    public function update(UpdateSpecialtyRequest $request, Specialty $specialty, UpdateSpecialtyAction $action): RedirectResponse
    {
        $action->handle($specialty, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Especialidade alterada com sucesso.']);

        return to_route('settings.specialties.index');
    }

    public function activate(Specialty $specialty, ActivateSpecialtyAction $action): RedirectResponse
    {
        $this->authorize('activate', $specialty);

        $action->handle($specialty);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Especialidade ativada com sucesso.']);

        return back();
    }

    public function deactivate(Specialty $specialty, DeactivateSpecialtyAction $action): RedirectResponse
    {
        $this->authorize('deactivate', $specialty);

        $action->handle($specialty);

        $impact = $specialty->professionalLinks()->count() + $specialty->serviceLinks()->count();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $impact > 0
                ? 'Especialidade inativada com sucesso. Os vínculos existentes foram preservados, mas ela não poderá ser usada em novos cadastros.'
                : 'Especialidade inativada com sucesso.',
        ]);

        return back();
    }

    public function destroy(Specialty $specialty, DeleteSpecialtyAction $action): RedirectResponse
    {
        $this->authorize('delete', $specialty);

        $action->handle($specialty);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Especialidade excluída com sucesso. Seu histórico foi preservado.']);

        return back();
    }

    public function restore(string $specialty, TenantContext $tenant, RestoreSpecialtyAction $action): RedirectResponse
    {
        $entity = Specialty::withTrashed()->findOrFail($specialty);

        if (! $tenant->organization() || $entity->organization_id !== $tenant->organization()->id) {
            abort(404);
        }

        $this->authorize('restore', $entity);

        $action->handle($entity);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Especialidade restaurada com sucesso.']);

        return back();
    }
}
