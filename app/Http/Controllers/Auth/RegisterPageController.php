<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Substitui a view de registro nativa do Fortify (feature `registration`
 * desabilitada em config/fortify.php) — autocadastro de conta de staff/
 * organização não existe mais, então esta tela só oferece o autocadastro
 * de paciente (ver App\Http\Controllers\PatientPortal\RegisteredPatientUserController).
 */
class RegisterPageController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('auth/Register');
    }
}
