<?php

declare(strict_types=1);

namespace App\Http\Controllers\PatientPortal;

use App\Http\Controllers\Controller;
use App\Models\PatientUser;
use Illuminate\Http\RedirectResponse;

/**
 * Confirma o e-mail a partir do link assinado enviado por
 * App\Notifications\VerifyPatientEmailNotification. Nenhuma rota do portal
 * exige e-mail verificado nesta etapa — ver docs/modules/patient-portal.md.
 */
class PatientVerifyEmailController extends Controller
{
    public function __invoke(string $id, string $hash): RedirectResponse
    {
        $patientUser = PatientUser::query()->findOrFail($id);

        if (! hash_equals(sha1($patientUser->getEmailForVerification()), $hash)) {
            abort(403);
        }

        if (! $patientUser->hasVerifiedEmail()) {
            $patientUser->markEmailAsVerified();
        }

        return to_route('patient-portal.dashboard')->with('status', 'E-mail verificado com sucesso.');
    }
}
