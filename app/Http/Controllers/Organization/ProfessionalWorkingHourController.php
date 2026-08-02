<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organization;

use App\Actions\Organization\ActivateProfessionalWorkingHourAction;
use App\Actions\Organization\CopyProfessionalWorkingHoursAction;
use App\Actions\Organization\CreateProfessionalWorkingHourAction;
use App\Actions\Organization\DeactivateProfessionalWorkingHourAction;
use App\Actions\Organization\DeleteProfessionalWorkingHourAction;
use App\Actions\Organization\RestoreProfessionalWorkingHourAction;
use App\Actions\Organization\UpdateProfessionalWorkingHourAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\AssignProfessionalWorkingHourRequest;
use App\Http\Requests\Organization\CopyProfessionalWorkingHoursRequest;
use App\Http\Requests\Organization\UpdateProfessionalWorkingHourRequest;
use App\Models\Professional;
use App\Models\ProfessionalUnit;
use App\Models\ProfessionalWorkingHour;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class ProfessionalWorkingHourController extends Controller
{
    public function store(AssignProfessionalWorkingHourRequest $request, Professional $professional, ProfessionalUnit $professionalUnit, CreateProfessionalWorkingHourAction $action): RedirectResponse
    {
        $this->authorizeBelongsToProfessional($professional, $professionalUnit);

        $action->handle($professionalUnit, $request->attributesForAction());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Jornada cadastrada com sucesso.']);

        return back();
    }

    public function update(UpdateProfessionalWorkingHourRequest $request, Professional $professional, ProfessionalWorkingHour $workingHour, UpdateProfessionalWorkingHourAction $action): RedirectResponse
    {
        $this->authorizeBelongsToProfessional($professional, $workingHour->professionalUnit);

        $action->handle($workingHour, $request->attributesForAction());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Horário alterado com sucesso.']);

        return back();
    }

    public function activate(Professional $professional, ProfessionalWorkingHour $workingHour, ActivateProfessionalWorkingHourAction $action): RedirectResponse
    {
        $this->authorizeBelongsToProfessional($professional, $workingHour->professionalUnit);
        $this->authorize('manageAvailability', [$professional, $workingHour->professionalUnit->unit]);

        $action->handle($workingHour);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Horário ativado com sucesso.']);

        return back();
    }

    public function deactivate(Professional $professional, ProfessionalWorkingHour $workingHour, DeactivateProfessionalWorkingHourAction $action): RedirectResponse
    {
        $this->authorizeBelongsToProfessional($professional, $workingHour->professionalUnit);
        $this->authorize('manageAvailability', [$professional, $workingHour->professionalUnit->unit]);

        $action->handle($workingHour);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Horário inativado com sucesso.']);

        return back();
    }

    public function destroy(Professional $professional, ProfessionalWorkingHour $workingHour, DeleteProfessionalWorkingHourAction $action): RedirectResponse
    {
        $this->authorizeBelongsToProfessional($professional, $workingHour->professionalUnit);
        $this->authorize('manageAvailability', [$professional, $workingHour->professionalUnit->unit]);

        $action->handle($workingHour);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Horário excluído. Seu histórico foi preservado.']);

        return back();
    }

    public function restore(Professional $professional, string $workingHour, RestoreProfessionalWorkingHourAction $action): RedirectResponse
    {
        $entity = ProfessionalWorkingHour::withTrashed()->findOrFail($workingHour);
        $this->authorizeBelongsToProfessional($professional, $entity->professionalUnit);
        $this->authorize('manageAvailability', [$professional, $entity->professionalUnit->unit]);

        $action->handle($entity);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Horário restaurado com sucesso.']);

        return back();
    }

    public function copy(CopyProfessionalWorkingHoursRequest $request, Professional $professional, ProfessionalUnit $professionalUnit, CopyProfessionalWorkingHoursAction $action): RedirectResponse
    {
        $this->authorizeBelongsToProfessional($professional, $professionalUnit);

        $action->handle($professionalUnit, $request->attributesForAction());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Os horários foram copiados com sucesso.']);

        return back();
    }

    private function authorizeBelongsToProfessional(Professional $professional, ProfessionalUnit $professionalUnit): void
    {
        if ($professionalUnit->professional_id !== $professional->id) {
            abort(404);
        }
    }
}
