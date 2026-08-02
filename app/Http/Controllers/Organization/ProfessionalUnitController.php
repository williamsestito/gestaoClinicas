<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organization;

use App\Actions\Organization\ActivateProfessionalUnitAction;
use App\Actions\Organization\AssignProfessionalToUnitAction;
use App\Actions\Organization\DeactivateProfessionalUnitAction;
use App\Actions\Organization\RemoveProfessionalFromUnitAction;
use App\Actions\Organization\RestoreProfessionalUnitAction;
use App\Actions\Organization\SetPrimaryProfessionalUnitAction;
use App\Actions\Organization\UpdateProfessionalUnitAssignmentAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\AssignProfessionalUnitRequest;
use App\Http\Requests\Organization\UpdateProfessionalUnitRequest;
use App\Models\Professional;
use App\Models\ProfessionalUnit;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class ProfessionalUnitController extends Controller
{
    public function store(AssignProfessionalUnitRequest $request, Professional $professional, AssignProfessionalToUnitAction $action): RedirectResponse
    {
        $action->handle($professional, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Profissional vinculado à unidade com sucesso.']);

        return back();
    }

    public function update(UpdateProfessionalUnitRequest $request, Professional $professional, ProfessionalUnit $professionalUnit, UpdateProfessionalUnitAssignmentAction $action): RedirectResponse
    {
        $this->authorizeBelongsToProfessional($professional, $professionalUnit);
        $this->authorize('manageUnits', $professional);

        $action->handle($professionalUnit, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Vigência atualizada com sucesso.']);

        return back();
    }

    public function setPrimary(Professional $professional, ProfessionalUnit $professionalUnit, SetPrimaryProfessionalUnitAction $action): RedirectResponse
    {
        $this->authorizeBelongsToProfessional($professional, $professionalUnit);
        $this->authorize('manageUnits', $professional);

        $action->handle($professionalUnit);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Unidade principal alterada com sucesso.']);

        return back();
    }

    public function activate(Professional $professional, ProfessionalUnit $professionalUnit, ActivateProfessionalUnitAction $action): RedirectResponse
    {
        $this->authorizeBelongsToProfessional($professional, $professionalUnit);
        $this->authorize('manageUnits', $professional);

        $action->handle($professionalUnit);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Vínculo ativado com sucesso.']);

        return back();
    }

    public function deactivate(Professional $professional, ProfessionalUnit $professionalUnit, DeactivateProfessionalUnitAction $action): RedirectResponse
    {
        $this->authorizeBelongsToProfessional($professional, $professionalUnit);
        $this->authorize('manageUnits', $professional);

        $action->handle($professionalUnit);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Vínculo inativado com sucesso.']);

        return back();
    }

    public function destroy(Professional $professional, ProfessionalUnit $professionalUnit, RemoveProfessionalFromUnitAction $action): RedirectResponse
    {
        $this->authorizeBelongsToProfessional($professional, $professionalUnit);
        $this->authorize('manageUnits', $professional);

        $action->handle($professionalUnit);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Vínculo removido. Seu histórico foi preservado.']);

        return back();
    }

    public function restore(Professional $professional, string $professionalUnit, RestoreProfessionalUnitAction $action): RedirectResponse
    {
        $link = ProfessionalUnit::withTrashed()->findOrFail($professionalUnit);
        $this->authorizeBelongsToProfessional($professional, $link);
        $this->authorize('manageUnits', $professional);

        $action->handle($link);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Vínculo restaurado com sucesso.']);

        return back();
    }

    private function authorizeBelongsToProfessional(Professional $professional, ProfessionalUnit $link): void
    {
        if ($link->professional_id !== $professional->id) {
            abort(404);
        }
    }
}
