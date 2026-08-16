<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organization;

use App\Actions\Organization\CreatePatientResponsibleAction;
use App\Actions\Organization\DeletePatientResponsibleAction;
use App\Actions\Organization\UpdatePatientResponsibleAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\CreatePatientResponsibleRequest;
use App\Http\Requests\Organization\UpdatePatientResponsibleRequest;
use App\Models\Patient;
use App\Models\PatientResponsible;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class PatientResponsibleController extends Controller
{
    public function store(CreatePatientResponsibleRequest $request, Patient $patient, CreatePatientResponsibleAction $action): RedirectResponse
    {
        $action->handle($patient, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Responsável vinculado ao paciente com sucesso.']);

        return back();
    }

    public function update(
        UpdatePatientResponsibleRequest $request,
        Patient $patient,
        PatientResponsible $responsible,
        UpdatePatientResponsibleAction $action,
    ): RedirectResponse {
        $this->authorizeBelongsToPatient($patient, $responsible);

        $action->handle($responsible, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Responsável atualizado com sucesso.']);

        return back();
    }

    public function destroy(Patient $patient, PatientResponsible $responsible, DeletePatientResponsibleAction $action): RedirectResponse
    {
        $this->authorizeBelongsToPatient($patient, $responsible);
        $this->authorize('manageResponsibles', $patient);

        $action->handle($responsible);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Responsável removido com sucesso.']);

        return back();
    }

    private function authorizeBelongsToPatient(Patient $patient, PatientResponsible $responsible): void
    {
        if ($responsible->patient_id !== $patient->id) {
            abort(404);
        }
    }
}
