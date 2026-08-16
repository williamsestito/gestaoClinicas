<?php

declare(strict_types=1);

namespace App\Http\Controllers\PatientPortal;

use App\Actions\PatientPortal\AddDependentPatientAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\PatientPortal\AddDependentPatientRequest;
use App\Models\Organization;
use App\Models\PatientUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PatientDependentController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('patient-portal/dependents/Create');
    }

    public function store(AddDependentPatientRequest $request, AddDependentPatientAction $action): RedirectResponse
    {
        /** @var PatientUser $patientUser */
        $patientUser = $request->user('patient');

        // Instalação single-tenant (ver ADR-010) — a conta já foi criada
        // sob a única organização existente; reaproveita a mesma para
        // manter a FK composta consistente.
        $organization = Organization::query()->findOrFail($patientUser->organization_id);

        $action->handle($patientUser, $organization, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Dependente adicionado com sucesso.']);

        return to_route('patient-portal.dashboard');
    }
}
