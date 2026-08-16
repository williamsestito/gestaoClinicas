<?php

declare(strict_types=1);

namespace App\Http\Controllers\PatientPortal;

use App\Http\Controllers\Controller;
use App\Models\PatientUser;
use App\Models\PatientUserLink;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PatientPortalDashboardController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var PatientUser $patientUser */
        $patientUser = $request->user('patient');

        // whereHas('patient'): um Patient soft-deletado pela recepção
        // (DeletePatientAction não bloqueia isso, nem desfaz o vínculo)
        // faria $link->patient resolver null e o acesso a ->id explodir —
        // achado de security-review desta etapa.
        $patients = $patientUser->links()->whereHas('patient')->with('patient')->get()
            ->map(fn (PatientUserLink $link) => [
                'id' => $link->patient->id,
                'name' => $link->patient->preferred_name ?: $link->patient->name,
                'birth_date' => $link->patient->birth_date->toDateString(),
                'role' => $link->role->value,
                'role_label' => $link->role->label(),
            ]);

        return Inertia::render('patient-portal/Dashboard', [
            'patients' => $patients,
        ]);
    }
}
