<?php

/*
|--------------------------------------------------------------------------
| Patient Portal Routes
|--------------------------------------------------------------------------
|
| Autocadastro, login e portal do paciente (Etapa 2.2 do roadmap) — guard
| "patient" próprio, sem Fortify e sem os middlewares "tenant.*" de staff
| (ver docs/modules/patient-portal.md). A organização é resolvida inline
| como Organization::query()->first() nos controllers (instalação
| single-tenant, ver docs/decisions/ADR-010-single-tenant-install-and-seo.md).
|
*/

use App\Http\Controllers\PatientPortal\PatientAuthenticatedSessionController;
use App\Http\Controllers\PatientPortal\PatientDependentController;
use App\Http\Controllers\PatientPortal\PatientPasswordResetController;
use App\Http\Controllers\PatientPortal\PatientPortalDashboardController;
use App\Http\Controllers\PatientPortal\PatientProfileController;
use App\Http\Controllers\PatientPortal\PatientVerifyEmailController;
use App\Http\Controllers\PatientPortal\RegisteredPatientUserController;
use Illuminate\Support\Facades\Route;

Route::prefix('portal')->name('patient-portal.')->group(function () {
    Route::middleware('guest:patient')->group(function () {
        Route::get('registrar', [RegisteredPatientUserController::class, 'create'])->name('register');
        Route::post('registrar', [RegisteredPatientUserController::class, 'store'])->middleware('throttle:patient-register');

        Route::get('login', [PatientAuthenticatedSessionController::class, 'create'])->name('login');
        Route::post('login', [PatientAuthenticatedSessionController::class, 'store'])->middleware('throttle:patient-login');

        Route::get('esqueci-senha', [PatientPasswordResetController::class, 'create'])->name('password.request');
        Route::post('esqueci-senha', [PatientPasswordResetController::class, 'store'])
            ->middleware('throttle:patient-password-reset')
            ->name('password.email');
        Route::get('redefinir-senha/{token}', [PatientPasswordResetController::class, 'edit'])->name('password.reset');
        Route::post('redefinir-senha', [PatientPasswordResetController::class, 'update'])
            ->middleware('throttle:patient-password-reset')
            ->name('password.update');
    });

    Route::middleware(['auth:patient', 'patient.active', 'patient.share-portal-data'])->group(function () {
        Route::post('logout', [PatientAuthenticatedSessionController::class, 'destroy'])->name('logout');

        Route::get('verificar-email/{id}/{hash}', PatientVerifyEmailController::class)
            ->middleware('signed')
            ->name('verification.verify');

        Route::get('/', [PatientPortalDashboardController::class, 'index'])->name('dashboard');

        Route::get('pacientes/{patient}/editar', [PatientProfileController::class, 'edit'])->name('patients.edit');
        Route::put('pacientes/{patient}', [PatientProfileController::class, 'update'])
            ->middleware('throttle:patient-portal-write')
            ->name('patients.update');

        Route::get('dependentes/novo', [PatientDependentController::class, 'create'])->name('dependents.create');
        Route::post('dependentes', [PatientDependentController::class, 'store'])
            ->middleware('throttle:patient-portal-write')
            ->name('dependents.store');
    });
});
