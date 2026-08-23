<?php

/*
|--------------------------------------------------------------------------
| Patient Portal Routes
|--------------------------------------------------------------------------
|
| Autocadastro e portal do paciente (Etapa 2.2 do roadmap) — guard
| "patient" próprio, sem Fortify e sem os middlewares "tenant.*" de staff
| (ver docs/modules/patient-portal.md). O login em si acontece em /login,
| que reconhece paciente e staff pelo mesmo e-mail (ver
| App\Providers\FortifyServiceProvider::configureActions()) — não existe
| tela de login dedicada ao portal. A organização é resolvida inline como
| Organization::query()->first() nos controllers (instalação single-tenant,
| ver docs/decisions/ADR-010-single-tenant-install-and-seo.md).
|
*/

use App\Http\Controllers\PatientPortal\PatientAppointmentController;
use App\Http\Controllers\PatientPortal\PatientAuthenticatedSessionController;
use App\Http\Controllers\PatientPortal\PatientAvailabilityController;
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

        Route::post('pacientes/{patient}/foto', [PatientProfileController::class, 'updatePhoto'])
            ->middleware('throttle:patient-portal-write')
            ->name('patients.photo.update');
        Route::delete('pacientes/{patient}/foto', [PatientProfileController::class, 'destroyPhoto'])
            ->middleware('throttle:patient-portal-write')
            ->name('patients.photo.destroy');
        // Sem throttle de escrita — leitura, mesmo padrão de
        // settings.patients.photo.show (staff).
        Route::get('pacientes/{patient}/foto', [PatientProfileController::class, 'showPhoto'])
            ->name('patients.photo.show');

        Route::get('dependentes/novo', [PatientDependentController::class, 'create'])->name('dependents.create');
        Route::post('dependentes', [PatientDependentController::class, 'store'])
            ->middleware('throttle:patient-portal-write')
            ->name('dependents.store');

        // Etapa 3.2 do roadmap — booking real pelo portal. "horarios" não
        // recebe {patient} na URL: disponibilidade não é dado sensível de um
        // paciente específico (mesmo racional de "available-slots" do staff,
        // ver routes/clinic.php).
        Route::get('agendamentos/horarios', [PatientAppointmentController::class, 'availableSlots'])
            ->name('appointments.available-slots');

        // Mesma cadeia unidade → especialidade → serviço → profissional →
        // datas → horários da busca pública da landing (ver
        // routes/public-site.php), só que autenticada e escopada pela
        // organização do próprio paciente — usada para guiar o fluxo de
        // "novo agendamento" (Create.vue) com o mesmo passo a passo.
        Route::prefix('agendamentos/disponibilidade')->name('appointments.availability.')->group(function () {
            Route::get('unidades', [PatientAvailabilityController::class, 'units'])->name('units');
            Route::get('especialidades', [PatientAvailabilityController::class, 'specialties'])->name('specialties');
            Route::get('servicos', [PatientAvailabilityController::class, 'services'])->name('services');
            Route::get('profissionais', [PatientAvailabilityController::class, 'professionals'])->name('professionals');
            Route::get('datas', [PatientAvailabilityController::class, 'dates'])->name('dates');
            Route::get('horarios', [PatientAvailabilityController::class, 'times'])->name('times');
        });
        Route::get('pacientes/{patient}/agendamentos', [PatientAppointmentController::class, 'index'])
            ->name('appointments.index');
        Route::patch('pacientes/{patient}/pre-agendamentos/{appointmentRequest}/cancelar', [PatientAppointmentController::class, 'cancelRequest'])
            ->middleware('throttle:patient-portal-write')
            ->name('appointment-requests.cancel');
        Route::get('pacientes/{patient}/agendamentos/novo', [PatientAppointmentController::class, 'create'])
            ->name('appointments.create');
        Route::post('pacientes/{patient}/agendamentos', [PatientAppointmentController::class, 'store'])
            ->middleware('throttle:patient-portal-write')
            ->name('appointments.store');

        // Etapa 3.3 do roadmap — cancelar/reagendar o próprio agendamento.
        Route::patch('pacientes/{patient}/agendamentos/{appointment}/cancelar', [PatientAppointmentController::class, 'cancel'])
            ->middleware('throttle:patient-portal-write')
            ->name('appointments.cancel');
        Route::get('pacientes/{patient}/agendamentos/{appointment}/reagendar', [PatientAppointmentController::class, 'editReschedule'])
            ->name('appointments.reschedule.edit');
        Route::put('pacientes/{patient}/agendamentos/{appointment}/reagendar', [PatientAppointmentController::class, 'reschedule'])
            ->middleware('throttle:patient-portal-write')
            ->name('appointments.reschedule.update');
        Route::patch('pacientes/{patient}/agendamentos/{appointment}/aceitar', [PatientAppointmentController::class, 'acceptProposedTime'])
            ->middleware('throttle:patient-portal-write')
            ->name('appointments.accept-proposed-time');
    });
});
