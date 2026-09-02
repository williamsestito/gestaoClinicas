<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organization;

use App\Actions\Organization\CreatePatientEmergencyContactAction;
use App\Actions\Organization\DeletePatientEmergencyContactAction;
use App\Actions\Organization\UpdatePatientEmergencyContactAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\CreatePatientEmergencyContactRequest;
use App\Http\Requests\Organization\UpdatePatientEmergencyContactRequest;
use App\Models\Patient;
use App\Models\PatientEmergencyContact;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class PatientEmergencyContactController extends Controller
{
    public function store(
        CreatePatientEmergencyContactRequest $request,
        Patient $patient,
        CreatePatientEmergencyContactAction $action,
    ): RedirectResponse {
        $action->handle($patient, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Contato de emergência adicionado com sucesso.']);

        return back();
    }

    public function update(
        UpdatePatientEmergencyContactRequest $request,
        Patient $patient,
        PatientEmergencyContact $emergencyContact,
        UpdatePatientEmergencyContactAction $action,
    ): RedirectResponse {
        $this->authorizeBelongsToPatient($patient, $emergencyContact);

        $action->handle($emergencyContact, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Contato de emergência atualizado com sucesso.']);

        return back();
    }

    public function destroy(
        Patient $patient,
        PatientEmergencyContact $emergencyContact,
        DeletePatientEmergencyContactAction $action,
    ): RedirectResponse {
        $this->authorizeBelongsToPatient($patient, $emergencyContact);
        $this->authorize('manageEmergencyContacts', $patient);

        $action->handle($emergencyContact);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Contato de emergência removido com sucesso.']);

        return back();
    }

    private function authorizeBelongsToPatient(Patient $patient, PatientEmergencyContact $emergencyContact): void
    {
        if ($emergencyContact->patient_id !== $patient->id) {
            abort(404);
        }
    }
}
