<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organization;

use App\Actions\Organization\ActivateProfessionalSpecialtyAction;
use App\Actions\Organization\AssignSpecialtyToProfessionalAction;
use App\Actions\Organization\DeactivateProfessionalSpecialtyAction;
use App\Actions\Organization\RemoveProfessionalSpecialtyAction;
use App\Actions\Organization\RestoreProfessionalSpecialtyAction;
use App\Actions\Organization\SetPrimaryProfessionalSpecialtyAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\AssignProfessionalSpecialtyRequest;
use App\Models\Professional;
use App\Models\ProfessionalSpecialty;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class ProfessionalSpecialtyController extends Controller
{
    public function store(AssignProfessionalSpecialtyRequest $request, Professional $professional, AssignSpecialtyToProfessionalAction $action): RedirectResponse
    {
        $action->handle($professional, (string) $request->validated('specialty_id'));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Especialidade vinculada ao profissional com sucesso.']);

        return back();
    }

    public function setPrimary(Professional $professional, ProfessionalSpecialty $professionalSpecialty, SetPrimaryProfessionalSpecialtyAction $action): RedirectResponse
    {
        $this->authorizeBelongsToProfessional($professional, $professionalSpecialty);
        $this->authorize('manageSpecialties', $professional);

        $action->handle($professionalSpecialty);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Especialidade principal alterada com sucesso.']);

        return back();
    }

    public function activate(Professional $professional, ProfessionalSpecialty $professionalSpecialty, ActivateProfessionalSpecialtyAction $action): RedirectResponse
    {
        $this->authorizeBelongsToProfessional($professional, $professionalSpecialty);
        $this->authorize('manageSpecialties', $professional);

        $action->handle($professionalSpecialty);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Vínculo ativado com sucesso.']);

        return back();
    }

    public function deactivate(Professional $professional, ProfessionalSpecialty $professionalSpecialty, DeactivateProfessionalSpecialtyAction $action): RedirectResponse
    {
        $this->authorizeBelongsToProfessional($professional, $professionalSpecialty);
        $this->authorize('manageSpecialties', $professional);

        $action->handle($professionalSpecialty);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Vínculo inativado com sucesso.']);

        return back();
    }

    public function destroy(Professional $professional, ProfessionalSpecialty $professionalSpecialty, RemoveProfessionalSpecialtyAction $action): RedirectResponse
    {
        $this->authorizeBelongsToProfessional($professional, $professionalSpecialty);
        $this->authorize('manageSpecialties', $professional);

        $action->handle($professionalSpecialty);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Vínculo removido. Seu histórico foi preservado.']);

        return back();
    }

    public function restore(Professional $professional, string $professionalSpecialty, RestoreProfessionalSpecialtyAction $action): RedirectResponse
    {
        $link = ProfessionalSpecialty::withTrashed()->findOrFail($professionalSpecialty);
        $this->authorizeBelongsToProfessional($professional, $link);
        $this->authorize('manageSpecialties', $professional);

        $action->handle($link);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Vínculo restaurado com sucesso.']);

        return back();
    }

    private function authorizeBelongsToProfessional(Professional $professional, ProfessionalSpecialty $link): void
    {
        if ($link->professional_id !== $professional->id) {
            abort(404);
        }
    }
}
