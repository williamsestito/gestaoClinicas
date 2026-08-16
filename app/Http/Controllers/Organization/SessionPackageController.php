<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organization;

use App\Actions\Organization\CloseSessionPackageAction;
use App\Actions\Organization\CreateSessionPackageAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\CreateSessionPackageRequest;
use App\Models\Patient;
use App\Models\SessionPackage;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class SessionPackageController extends Controller
{
    public function store(CreateSessionPackageRequest $request, Patient $patient, CreateSessionPackageAction $action): RedirectResponse
    {
        $action->handle($patient, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pacote de sessões criado com sucesso.']);

        return back();
    }

    public function close(Patient $patient, SessionPackage $sessionPackage, CloseSessionPackageAction $action): RedirectResponse
    {
        $this->authorizeBelongsToPatient($patient, $sessionPackage);
        $this->authorize('update', $patient);

        $action->handle($sessionPackage);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pacote de sessões encerrado.']);

        return back();
    }

    private function authorizeBelongsToPatient(Patient $patient, SessionPackage $sessionPackage): void
    {
        if ($sessionPackage->patient_id !== $patient->id) {
            abort(404);
        }
    }
}
