<?php

use App\Http\Controllers\Organization\AppointmentController;
use App\Http\Controllers\Organization\AppointmentRequestController;
use App\Http\Controllers\Organization\AuditLogController;
use App\Http\Controllers\Organization\DashboardController;
use App\Http\Controllers\Organization\DashboardReminderController;
use App\Http\Controllers\Organization\InvitationController;
use App\Http\Controllers\Organization\LegalEntityController;
use App\Http\Controllers\Organization\MedicalRecordController;
use App\Http\Controllers\Organization\MedicalRecordFileController;
use App\Http\Controllers\Organization\MyAppointmentRequestsController;
use App\Http\Controllers\Organization\MyPatientsController;
use App\Http\Controllers\Organization\MyScheduleController;
use App\Http\Controllers\Organization\OnboardingController;
use App\Http\Controllers\Organization\OrganizationContextController;
use App\Http\Controllers\Organization\OrganizationModuleController;
use App\Http\Controllers\Organization\OrganizationSettingsController;
use App\Http\Controllers\Organization\PatientController;
use App\Http\Controllers\Organization\PatientEmergencyContactController;
use App\Http\Controllers\Organization\PatientResponsibleController;
use App\Http\Controllers\Organization\ProductController;
use App\Http\Controllers\Organization\ProfessionalController;
use App\Http\Controllers\Organization\ProfessionalRegistrationController;
use App\Http\Controllers\Organization\ProfessionalServiceController;
use App\Http\Controllers\Organization\ProfessionalSpecialtyController;
use App\Http\Controllers\Organization\ProfessionalTimeBlockController;
use App\Http\Controllers\Organization\ProfessionalUnitController;
use App\Http\Controllers\Organization\ProfessionalWorkingHourController;
use App\Http\Controllers\Organization\ResourceController;
use App\Http\Controllers\Organization\RoleController;
use App\Http\Controllers\Organization\SaleController;
use App\Http\Controllers\Organization\SeoMarketingController;
use App\Http\Controllers\Organization\ServiceController;
use App\Http\Controllers\Organization\SessionPackageController;
use App\Http\Controllers\Organization\SiteBenefitController;
use App\Http\Controllers\Organization\SiteContentController;
use App\Http\Controllers\Organization\SiteFaqController;
use App\Http\Controllers\Organization\SiteGalleryItemController;
use App\Http\Controllers\Organization\SitePartnerController;
use App\Http\Controllers\Organization\SiteProfessionalController;
use App\Http\Controllers\Organization\SiteSectionsController;
use App\Http\Controllers\Organization\SiteServiceController;
use App\Http\Controllers\Organization\SiteTestimonialController;
use App\Http\Controllers\Organization\SpecialtyController;
use App\Http\Controllers\Organization\UnitContextController;
use App\Http\Controllers\Organization\UnitController;
use App\Http\Controllers\Organization\UserManagementController;
use App\Http\Controllers\Organization\WaitlistController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Clinic Routes
|--------------------------------------------------------------------------
|
| Rotas do contexto autenticado da clinica (equipe/profissionais): dashboard,
| onboarding de organização, seleção de organização/unidade ativa e
| configurações de organização/unidades (Fase 1). Módulos de negócio
| (agenda, pacientes, prontuário, etc.) serão adicionados em fases futuras.
|
*/

Route::middleware(['auth', 'verified', 'tenant.organization', 'tenant.unit'])->group(function () {

    // Onboarding: acessível sem organização ativa (é como ela é criada).
    // Bloqueado para quem já tem uma organização (evita reacesso e criação
    // ilimitada de clínicas pela mesma rota).
    Route::middleware('tenant.no-active-organization')->group(function () {
        Route::get('onboarding/organization', [OnboardingController::class, 'create'])
            ->name('onboarding.organization.create');
        Route::post('onboarding/organization', [OnboardingController::class, 'store'])
            ->name('onboarding.organization.store');
    });

    // Seletor de organização: acessível sem organização ativa (é onde se escolhe uma).
    Route::get('context/organization', [OrganizationContextController::class, 'edit'])
        ->name('context.organization.edit');
    Route::put('context/organization', [OrganizationContextController::class, 'update'])
        ->middleware('throttle:20,1')
        ->name('context.organization.update');

    Route::middleware('tenant.active-organization')->group(function () {
        // Rotas operacionais: exigem também uma unidade ativa resolvida.
        Route::middleware('tenant.active-unit')->group(function () {
            Route::get('dashboard', [DashboardController::class, 'index'])
                ->name('dashboard');
        });

        Route::get('context/unit', [UnitContextController::class, 'edit'])
            ->name('context.unit.edit');
        Route::put('context/unit', [UnitContextController::class, 'update'])
            ->middleware('throttle:20,1')
            ->name('context.unit.update');

        Route::get('settings/organization', [OrganizationSettingsController::class, 'edit'])
            ->name('settings.organization.edit');
        Route::put('settings/organization', [OrganizationSettingsController::class, 'update'])
            ->name('settings.organization.update');

        // Etapa 1 do roadmap (docs/roadmap.md) — módulos por especialidade.
        // Tela de configuração única, sem {module} na URL, mesmo padrão de
        // settings/organization e settings/seo.
        Route::get('settings/modules', [OrganizationModuleController::class, 'edit'])
            ->name('settings.modules.edit');
        Route::put('settings/modules', [OrganizationModuleController::class, 'update'])
            ->name('settings.modules.update');

        Route::get('settings/units', [UnitController::class, 'index'])
            ->name('settings.units.index');
        Route::get('settings/units/create', [UnitController::class, 'create'])
            ->name('settings.units.create');
        Route::post('settings/units', [UnitController::class, 'store'])
            ->name('settings.units.store');
        // Restauração usa {unit} sem binding de Eloquent (o registro está
        // excluído logicamente) — a checagem de organização é manual no controller.
        Route::post('settings/units/{unit}/restore', [UnitController::class, 'restore'])
            ->name('settings.units.restore');
        // Fora do grupo tenant.unit-membership pelo mesmo motivo do restore:
        // essa rota também precisa funcionar quando a unidade está inativa
        // (é como ela volta a ficar ativa) — a checagem de organização é
        // manual no controller, e a autorização (owner) continua via Policy.
        Route::patch('settings/units/{unit}/status', [UnitController::class, 'updateStatus'])
            ->name('settings.units.status');

        Route::middleware('tenant.unit-membership')->group(function () {
            Route::get('settings/units/{unit}/edit', [UnitController::class, 'edit'])
                ->name('settings.units.edit');
            Route::put('settings/units/{unit}', [UnitController::class, 'update'])
                ->name('settings.units.update');
            Route::put('settings/units/{unit}/headquarters', [UnitController::class, 'makeHeadquarters'])
                ->name('settings.units.headquarters');
            Route::delete('settings/units/{unit}', [UnitController::class, 'destroy'])
                ->name('settings.units.destroy');
        });

        Route::get('settings/legal-entities', [LegalEntityController::class, 'index'])
            ->name('settings.legal-entities.index');
        Route::get('settings/legal-entities/create', [LegalEntityController::class, 'create'])
            ->name('settings.legal-entities.create');
        Route::post('settings/legal-entities', [LegalEntityController::class, 'store'])
            ->name('settings.legal-entities.store');
        Route::post('settings/legal-entities/{legalEntity}/restore', [LegalEntityController::class, 'restore'])
            ->name('settings.legal-entities.restore');

        Route::middleware('tenant.legal-entity-membership')->group(function () {
            Route::get('settings/legal-entities/{legalEntity}/edit', [LegalEntityController::class, 'edit'])
                ->name('settings.legal-entities.edit');
            Route::put('settings/legal-entities/{legalEntity}', [LegalEntityController::class, 'update'])
                ->name('settings.legal-entities.update');
            Route::patch('settings/legal-entities/{legalEntity}/status', [LegalEntityController::class, 'updateStatus'])
                ->name('settings.legal-entities.status');
            Route::put('settings/legal-entities/{legalEntity}/primary', [LegalEntityController::class, 'makePrimary'])
                ->name('settings.legal-entities.primary');
            Route::delete('settings/legal-entities/{legalEntity}', [LegalEntityController::class, 'destroy'])
                ->name('settings.legal-entities.destroy');
        });

        // Especialidades, serviços e profissionais são dados de clínica
        // (não exigem unidade ativa) — mesmo padrão de rotas usado por
        // legal-entities: create/store/restore fora do grupo de membership
        // (registro pode estar excluído logicamente ou ainda não existir),
        // edit/update/status/destroy dentro dele.
        Route::get('settings/specialties', [SpecialtyController::class, 'index'])
            ->name('settings.specialties.index');
        Route::get('settings/specialties/create', [SpecialtyController::class, 'create'])
            ->name('settings.specialties.create');
        Route::post('settings/specialties', [SpecialtyController::class, 'store'])
            ->name('settings.specialties.store');
        Route::post('settings/specialties/{specialty}/restore', [SpecialtyController::class, 'restore'])
            ->name('settings.specialties.restore');

        Route::middleware('tenant.specialty-membership')->group(function () {
            Route::get('settings/specialties/{specialty}/edit', [SpecialtyController::class, 'edit'])
                ->name('settings.specialties.edit');
            Route::put('settings/specialties/{specialty}', [SpecialtyController::class, 'update'])
                ->name('settings.specialties.update');
            Route::patch('settings/specialties/{specialty}/activate', [SpecialtyController::class, 'activate'])
                ->name('settings.specialties.activate');
            Route::patch('settings/specialties/{specialty}/deactivate', [SpecialtyController::class, 'deactivate'])
                ->name('settings.specialties.deactivate');
            Route::delete('settings/specialties/{specialty}', [SpecialtyController::class, 'destroy'])
                ->name('settings.specialties.destroy');
        });

        Route::get('settings/services', [ServiceController::class, 'index'])
            ->name('settings.services.index');
        Route::get('settings/services/create', [ServiceController::class, 'create'])
            ->name('settings.services.create');
        Route::post('settings/services', [ServiceController::class, 'store'])
            ->name('settings.services.store');
        Route::post('settings/services/{service}/restore', [ServiceController::class, 'restore'])
            ->name('settings.services.restore');

        Route::middleware('tenant.service-membership')->group(function () {
            Route::get('settings/services/{service}/edit', [ServiceController::class, 'edit'])
                ->name('settings.services.edit');
            Route::put('settings/services/{service}', [ServiceController::class, 'update'])
                ->name('settings.services.update');
            Route::patch('settings/services/{service}/activate', [ServiceController::class, 'activate'])
                ->name('settings.services.activate');
            Route::patch('settings/services/{service}/deactivate', [ServiceController::class, 'deactivate'])
                ->name('settings.services.deactivate');
            Route::delete('settings/services/{service}', [ServiceController::class, 'destroy'])
                ->name('settings.services.destroy');
        });

        // Etapa 5 do roadmap (docs/roadmap.md) — Comercial (produtos, serviços,
        // vendas). Mesmo padrão create/store/restore fora do grupo de
        // membership, edit/update/status/destroy dentro dele.
        Route::get('settings/products', [ProductController::class, 'index'])
            ->name('settings.products.index');
        Route::get('settings/products/create', [ProductController::class, 'create'])
            ->name('settings.products.create');
        Route::post('settings/products', [ProductController::class, 'store'])
            ->name('settings.products.store');
        Route::post('settings/products/{product}/restore', [ProductController::class, 'restore'])
            ->name('settings.products.restore');

        Route::middleware('tenant.product-membership')->group(function () {
            Route::get('settings/products/{product}/edit', [ProductController::class, 'edit'])
                ->name('settings.products.edit');
            Route::put('settings/products/{product}', [ProductController::class, 'update'])
                ->name('settings.products.update');
            Route::patch('settings/products/{product}/activate', [ProductController::class, 'activate'])
                ->name('settings.products.activate');
            Route::patch('settings/products/{product}/deactivate', [ProductController::class, 'deactivate'])
                ->name('settings.products.deactivate');
            Route::delete('settings/products/{product}', [ProductController::class, 'destroy'])
                ->name('settings.products.destroy');
        });

        Route::get('settings/sales', [SaleController::class, 'index'])
            ->name('settings.sales.index');
        Route::get('settings/sales/create', [SaleController::class, 'create'])
            ->name('settings.sales.create');
        Route::post('settings/sales', [SaleController::class, 'store'])
            ->name('settings.sales.store');

        Route::middleware('tenant.sale-membership')->group(function () {
            Route::get('settings/sales/{sale}', [SaleController::class, 'show'])
                ->name('settings.sales.show');
            Route::put('settings/sales/{sale}', [SaleController::class, 'update'])
                ->name('settings.sales.update');
            Route::patch('settings/sales/{sale}/confirmar', [SaleController::class, 'confirm'])
                ->name('settings.sales.confirm');
            Route::patch('settings/sales/{sale}/cancelar', [SaleController::class, 'cancel'])
                ->name('settings.sales.cancel');
            Route::patch('settings/sales/{sale}/itens/{item}/aprovar-desconto', [SaleController::class, 'approveDiscount'])
                ->name('settings.sales.items.approve-discount');
        });

        // Etapa 3.3 do roadmap (docs/roadmap.md) — recursos compartilhados
        // (salas/equipamentos), mesmo padrão de create/store/restore fora do
        // grupo de membership, edit/update/status/destroy dentro dele.
        Route::get('settings/resources', [ResourceController::class, 'index'])
            ->name('settings.resources.index');
        Route::get('settings/resources/create', [ResourceController::class, 'create'])
            ->name('settings.resources.create');
        Route::post('settings/resources', [ResourceController::class, 'store'])
            ->name('settings.resources.store');
        Route::post('settings/resources/{resource}/restore', [ResourceController::class, 'restore'])
            ->name('settings.resources.restore');

        Route::middleware('tenant.resource-membership')->group(function () {
            Route::get('settings/resources/{resource}/edit', [ResourceController::class, 'edit'])
                ->name('settings.resources.edit');
            Route::put('settings/resources/{resource}', [ResourceController::class, 'update'])
                ->name('settings.resources.update');
            Route::patch('settings/resources/{resource}/activate', [ResourceController::class, 'activate'])
                ->name('settings.resources.activate');
            Route::patch('settings/resources/{resource}/deactivate', [ResourceController::class, 'deactivate'])
                ->name('settings.resources.deactivate');
            Route::delete('settings/resources/{resource}', [ResourceController::class, 'destroy'])
                ->name('settings.resources.destroy');
        });

        // Etapa 2.1 do roadmap (docs/roadmap.md) — cadastro administrativo de
        // pacientes. "duplicates" não tem {patient} na URL (busca org-wide),
        // por isso fica fora do grupo tenant.patient-membership.
        Route::get('settings/patients', [PatientController::class, 'index'])
            ->name('settings.patients.index');
        Route::get('settings/patients/create', [PatientController::class, 'create'])
            ->name('settings.patients.create');
        Route::post('settings/patients', [PatientController::class, 'store'])
            ->name('settings.patients.store');
        Route::get('settings/patients/duplicates', [PatientController::class, 'duplicates'])
            ->name('settings.patients.duplicates');
        Route::get('settings/patients/search', [PatientController::class, 'search'])
            ->name('settings.patients.search');
        Route::post('settings/patients/{patient}/restore', [PatientController::class, 'restore'])
            ->name('settings.patients.restore');

        Route::middleware('tenant.patient-membership')->group(function () {
            Route::get('settings/patients/{patient}/edit', [PatientController::class, 'edit'])
                ->name('settings.patients.edit');
            // Etapa de melhoria de "Meus pacientes"/admin — modal de
            // detalhes com histórico de agendamentos agrupado por
            // profissional, sem conteúdo de prontuário (isso continua na
            // tela dedicada, com sua própria Policy).
            Route::get('settings/patients/{patient}/resumo', [PatientController::class, 'summary'])
                ->name('settings.patients.summary');
            Route::put('settings/patients/{patient}', [PatientController::class, 'update'])
                ->name('settings.patients.update');
            Route::patch('settings/patients/{patient}/activate', [PatientController::class, 'activate'])
                ->name('settings.patients.activate');
            Route::patch('settings/patients/{patient}/deactivate', [PatientController::class, 'deactivate'])
                ->name('settings.patients.deactivate');
            Route::delete('settings/patients/{patient}', [PatientController::class, 'destroy'])
                ->name('settings.patients.destroy');
            Route::post('settings/patients/{patient}/photo', [PatientController::class, 'updatePhoto'])
                ->name('settings.patients.photo.update');
            Route::delete('settings/patients/{patient}/photo', [PatientController::class, 'destroyPhoto'])
                ->name('settings.patients.photo.destroy');
            // Disco privado (`local`) — nunca um link público direto, ver
            // PatientController::showPhoto().
            Route::get('settings/patients/{patient}/photo', [PatientController::class, 'showPhoto'])
                ->name('settings.patients.photo.show');

            Route::post('settings/patients/{patient}/responsibles', [PatientResponsibleController::class, 'store'])
                ->name('settings.patients.responsibles.store');
            Route::put('settings/patients/{patient}/responsibles/{responsible}', [PatientResponsibleController::class, 'update'])
                ->name('settings.patients.responsibles.update');
            Route::delete('settings/patients/{patient}/responsibles/{responsible}', [PatientResponsibleController::class, 'destroy'])
                ->name('settings.patients.responsibles.destroy');

            Route::post('settings/patients/{patient}/emergency-contacts', [PatientEmergencyContactController::class, 'store'])
                ->name('settings.patients.emergency-contacts.store');
            Route::put('settings/patients/{patient}/emergency-contacts/{emergencyContact}', [PatientEmergencyContactController::class, 'update'])
                ->name('settings.patients.emergency-contacts.update');
            Route::delete('settings/patients/{patient}/emergency-contacts/{emergencyContact}', [PatientEmergencyContactController::class, 'destroy'])
                ->name('settings.patients.emergency-contacts.destroy');

            // Etapa 3.3 do roadmap (docs/roadmap.md) — pacotes de sessões.
            Route::post('settings/patients/{patient}/session-packages', [SessionPackageController::class, 'store'])
                ->name('settings.patients.session-packages.store');
            Route::patch('settings/patients/{patient}/session-packages/{sessionPackage}/close', [SessionPackageController::class, 'close'])
                ->name('settings.patients.session-packages.close');
        });

        // Etapa 3.1 do roadmap (docs/roadmap.md) — agenda real. "available-slots"
        // não tem {appointment} na URL (sugestão de horário livre, não
        // autoritativa — ver App\Services\Availability\StaffAppointmentSlotFinder),
        // por isso fica fora do grupo tenant.appointment-membership.
        Route::get('settings/appointments', [AppointmentController::class, 'index'])
            ->name('settings.appointments.index');
        Route::get('settings/appointments/create', [AppointmentController::class, 'create'])
            ->name('settings.appointments.create');
        Route::post('settings/appointments', [AppointmentController::class, 'store'])
            ->name('settings.appointments.store');
        Route::get('settings/appointments/available-slots', [AppointmentController::class, 'availableSlots'])
            ->name('settings.appointments.available-slots');
        // Etapa 3.3 — pacotes de sessões ativos e utilizáveis de um paciente,
        // para o select "descontar de pacote" na criação de agendamento.
        Route::get('settings/appointments/patients/{patient}/session-packages', [AppointmentController::class, 'patientSessionPackages'])
            ->name('settings.appointments.patient-session-packages');

        Route::middleware('tenant.appointment-membership')->group(function () {
            Route::put('settings/appointments/{appointment}/reschedule', [AppointmentController::class, 'reschedule'])
                ->name('settings.appointments.reschedule');
            Route::patch('settings/appointments/{appointment}/cancel', [AppointmentController::class, 'cancel'])
                ->name('settings.appointments.cancel');
            Route::patch('settings/appointments/{appointment}/confirm', [AppointmentController::class, 'confirm'])
                ->name('settings.appointments.confirm');
            Route::get('settings/appointments/{appointment}/propose', [AppointmentController::class, 'editPropose'])
                ->name('settings.appointments.propose.edit');
            Route::put('settings/appointments/{appointment}/propose', [AppointmentController::class, 'propose'])
                ->name('settings.appointments.propose.update');
            Route::patch('settings/appointments/{appointment}/check-in', [AppointmentController::class, 'checkIn'])
                ->name('settings.appointments.check-in');
            Route::patch('settings/appointments/{appointment}/start', [AppointmentController::class, 'start'])
                ->name('settings.appointments.start');
            Route::patch('settings/appointments/{appointment}/complete', [AppointmentController::class, 'complete'])
                ->name('settings.appointments.complete');
            Route::patch('settings/appointments/{appointment}/no-show', [AppointmentController::class, 'noShow'])
                ->name('settings.appointments.no-show');

            // Etapa 4 — prontuário do atendimento (ver docs/modules/medical-records.md).
            Route::get('settings/appointments/{appointment}/prontuario', [MedicalRecordController::class, 'show'])
                ->name('settings.medical-records.show');
        });

        Route::middleware('tenant.medical-record-membership')->group(function () {
            Route::patch('settings/prontuarios/{medicalRecord}', [MedicalRecordController::class, 'update'])
                ->name('settings.medical-records.update');
            Route::patch('settings/prontuarios/{medicalRecord}/finalizar', [MedicalRecordController::class, 'finalize'])
                ->name('settings.medical-records.finalize');
            Route::patch('settings/prontuarios/{medicalRecord}/liberar', [MedicalRecordController::class, 'release'])
                ->name('settings.medical-records.release');
            Route::post('settings/prontuarios/{medicalRecord}/adendos', [MedicalRecordController::class, 'addAddendum'])
                ->name('settings.medical-records.add-addendum');
            Route::post('settings/prontuarios/{medicalRecord}/arquivos', [MedicalRecordFileController::class, 'store'])
                ->name('settings.medical-record-files.store');
            Route::get('settings/prontuarios/{medicalRecord}/arquivos/{file}', [MedicalRecordFileController::class, 'show'])
                ->name('settings.medical-record-files.show');
            Route::get('settings/prontuarios/{medicalRecord}/arquivos/{file}/download', [MedicalRecordFileController::class, 'download'])
                ->name('settings.medical-record-files.download');
        });

        Route::middleware('tenant.patient-membership')->group(function () {
            Route::get('settings/meus-pacientes/{patient}/prontuarios', [MedicalRecordController::class, 'patientHistory'])
                ->name('settings.medical-records.patient-history');
        });

        // Etapa 3.3 do roadmap — lista de espera. Reaproveita a permissão
        // appointments.manage (via AppointmentPolicy::create()).
        Route::get('settings/appointments/waitlist', [WaitlistController::class, 'index'])
            ->name('settings.appointments.waitlist.index');
        Route::post('settings/appointments/waitlist', [WaitlistController::class, 'store'])
            ->name('settings.appointments.waitlist.store');
        Route::patch('settings/appointments/waitlist/{waitlistEntry}/cancel', [WaitlistController::class, 'cancel'])
            ->name('settings.appointments.waitlist.cancel');

        // "Minha agenda" — nunca aceita {professional} na URL: o profissional
        // é sempre resolvido a partir do usuário autenticado.
        Route::get('settings/minha-agenda', [MyScheduleController::class, 'availability'])
            ->name('settings.my-schedule.availability');
        Route::get('settings/minha-agenda/ausencias', [MyScheduleController::class, 'timeBlocks'])
            ->name('settings.my-schedule.time-blocks');
        Route::get('settings/meu-cadastro', [MyScheduleController::class, 'profile'])
            ->name('settings.my-schedule.profile');

        // "Meus pacientes" — mesmo padrão de "Minha agenda": nunca aceita
        // {professional} na URL, sempre resolvido a partir do usuário
        // autenticado.
        Route::get('settings/meus-pacientes', [MyPatientsController::class, 'index'])
            ->name('settings.my-patients.index');

        // "Meus pré-agendamentos" — mesmo padrão. O vínculo de posse de
        // {appointmentRequest} é validado dentro do próprio FormRequest
        // (ver UpdateOwnAppointmentRequestStatusRequest), não por
        // middleware — o profissional dono nunca é resolvido pela URL.
        Route::get('settings/meus-pre-agendamentos', [MyAppointmentRequestsController::class, 'index'])
            ->name('settings.my-appointment-requests.index');
        Route::patch('settings/meus-pre-agendamentos/{appointmentRequest}/status', [MyAppointmentRequestsController::class, 'updateStatus'])
            ->name('settings.my-appointment-requests.status');
        Route::patch('settings/meus-pre-agendamentos/{appointmentRequest}/notes', [MyAppointmentRequestsController::class, 'updateNotes'])
            ->name('settings.my-appointment-requests.notes');

        // Lembretes tipo post-it do dashboard do profissional — mesmo
        // padrão dos demais "meus-*": nunca aceita {professional} na URL.
        Route::post('dashboard/lembretes', [DashboardReminderController::class, 'store'])
            ->name('dashboard.reminders.store');
        Route::delete('dashboard/lembretes/{reminder}', [DashboardReminderController::class, 'destroy'])
            ->name('dashboard.reminders.destroy');
        Route::patch('dashboard/lembretes/{reminder}/silenciar-alarme', [DashboardReminderController::class, 'dismissAlarm'])
            ->name('dashboard.reminders.dismiss-alarm');

        Route::get('settings/professionals', [ProfessionalController::class, 'index'])
            ->name('settings.professionals.index');
        Route::get('settings/professionals/agendas', [ProfessionalController::class, 'agendas'])
            ->name('settings.professionals.agendas');
        Route::get('settings/professionals/create', [ProfessionalController::class, 'create'])
            ->name('settings.professionals.create');
        Route::post('settings/professionals', [ProfessionalController::class, 'store'])
            ->name('settings.professionals.store');
        Route::post('settings/professionals/{professional}/restore', [ProfessionalController::class, 'restore'])
            ->name('settings.professionals.restore');

        Route::middleware('tenant.professional-membership')->group(function () {
            Route::get('settings/professionals/{professional}/edit', [ProfessionalController::class, 'edit'])
                ->name('settings.professionals.edit');
            Route::put('settings/professionals/{professional}', [ProfessionalController::class, 'update'])
                ->name('settings.professionals.update');
            Route::patch('settings/professionals/{professional}/activate', [ProfessionalController::class, 'activate'])
                ->name('settings.professionals.activate');
            Route::patch('settings/professionals/{professional}/deactivate', [ProfessionalController::class, 'deactivate'])
                ->name('settings.professionals.deactivate');
            Route::put('settings/professionals/{professional}/user', [ProfessionalController::class, 'linkUser'])
                ->name('settings.professionals.user.update');
            Route::delete('settings/professionals/{professional}/user', [ProfessionalController::class, 'unlinkUser'])
                ->name('settings.professionals.user.destroy');
            Route::put('settings/professionals/{professional}/user/password', [ProfessionalController::class, 'resetUserPassword'])
                ->name('settings.professionals.user.password');

            // Etapa 2.5 — especialidades e registros profissionais.
            Route::get('settings/professionals/{professional}/specialties', [ProfessionalController::class, 'specialties'])
                ->name('settings.professionals.specialties.index');
            Route::post('settings/professionals/{professional}/specialties', [ProfessionalSpecialtyController::class, 'store'])
                ->name('settings.professionals.specialties.store');
            Route::patch('settings/professionals/{professional}/specialties/{professionalSpecialty}/primary', [ProfessionalSpecialtyController::class, 'setPrimary'])
                ->name('settings.professionals.specialties.primary');
            Route::patch('settings/professionals/{professional}/specialties/{professionalSpecialty}/activate', [ProfessionalSpecialtyController::class, 'activate'])
                ->name('settings.professionals.specialties.activate');
            Route::patch('settings/professionals/{professional}/specialties/{professionalSpecialty}/deactivate', [ProfessionalSpecialtyController::class, 'deactivate'])
                ->name('settings.professionals.specialties.deactivate');
            Route::delete('settings/professionals/{professional}/specialties/{professionalSpecialty}', [ProfessionalSpecialtyController::class, 'destroy'])
                ->name('settings.professionals.specialties.destroy');
            Route::post('settings/professionals/{professional}/specialties/{professionalSpecialty}/restore', [ProfessionalSpecialtyController::class, 'restore'])
                ->name('settings.professionals.specialties.restore');

            Route::post('settings/professionals/{professional}/registrations', [ProfessionalRegistrationController::class, 'store'])
                ->name('settings.professionals.registrations.store');
            Route::put('settings/professionals/{professional}/registrations/{professionalRegistration}', [ProfessionalRegistrationController::class, 'update'])
                ->name('settings.professionals.registrations.update');
            Route::patch('settings/professionals/{professional}/registrations/{professionalRegistration}/primary', [ProfessionalRegistrationController::class, 'setPrimary'])
                ->name('settings.professionals.registrations.primary');
            Route::patch('settings/professionals/{professional}/registrations/{professionalRegistration}/activate', [ProfessionalRegistrationController::class, 'activate'])
                ->name('settings.professionals.registrations.activate');
            Route::patch('settings/professionals/{professional}/registrations/{professionalRegistration}/deactivate', [ProfessionalRegistrationController::class, 'deactivate'])
                ->name('settings.professionals.registrations.deactivate');
            Route::delete('settings/professionals/{professional}/registrations/{professionalRegistration}', [ProfessionalRegistrationController::class, 'destroy'])
                ->name('settings.professionals.registrations.destroy');
            Route::post('settings/professionals/{professional}/registrations/{professionalRegistration}/restore', [ProfessionalRegistrationController::class, 'restore'])
                ->name('settings.professionals.registrations.restore');
            Route::get('settings/professionals/{professional}/registrations/{professionalRegistration}/reveal', [ProfessionalRegistrationController::class, 'reveal'])
                ->middleware('throttle:20,1')
                ->name('settings.professionals.registrations.reveal');

            // Etapa 2.6 — unidades de atuação do profissional.
            Route::get('settings/professionals/{professional}/units', [ProfessionalController::class, 'units'])
                ->name('settings.professionals.units.index');
            Route::post('settings/professionals/{professional}/units', [ProfessionalUnitController::class, 'store'])
                ->name('settings.professionals.units.store');
            Route::put('settings/professionals/{professional}/units/{professionalUnit}', [ProfessionalUnitController::class, 'update'])
                ->name('settings.professionals.units.update');
            Route::patch('settings/professionals/{professional}/units/{professionalUnit}/primary', [ProfessionalUnitController::class, 'setPrimary'])
                ->name('settings.professionals.units.primary');
            Route::patch('settings/professionals/{professional}/units/{professionalUnit}/activate', [ProfessionalUnitController::class, 'activate'])
                ->name('settings.professionals.units.activate');
            Route::patch('settings/professionals/{professional}/units/{professionalUnit}/deactivate', [ProfessionalUnitController::class, 'deactivate'])
                ->name('settings.professionals.units.deactivate');
            Route::delete('settings/professionals/{professional}/units/{professionalUnit}', [ProfessionalUnitController::class, 'destroy'])
                ->name('settings.professionals.units.destroy');
            Route::post('settings/professionals/{professional}/units/{professionalUnit}/restore', [ProfessionalUnitController::class, 'restore'])
                ->name('settings.professionals.units.restore');

            // Etapa 2.7 — serviços executados pelo profissional.
            Route::get('settings/professionals/{professional}/services', [ProfessionalController::class, 'services'])
                ->name('settings.professionals.services.index');
            Route::post('settings/professionals/{professional}/services', [ProfessionalServiceController::class, 'store'])
                ->name('settings.professionals.services.store');
            Route::put('settings/professionals/{professional}/services/{professionalService}', [ProfessionalServiceController::class, 'update'])
                ->name('settings.professionals.services.update');
            Route::patch('settings/professionals/{professional}/services/{professionalService}/activate', [ProfessionalServiceController::class, 'activate'])
                ->name('settings.professionals.services.activate');
            Route::patch('settings/professionals/{professional}/services/{professionalService}/deactivate', [ProfessionalServiceController::class, 'deactivate'])
                ->name('settings.professionals.services.deactivate');
            Route::delete('settings/professionals/{professional}/services/{professionalService}', [ProfessionalServiceController::class, 'destroy'])
                ->name('settings.professionals.services.destroy');
            Route::post('settings/professionals/{professional}/services/{professionalService}/restore', [ProfessionalServiceController::class, 'restore'])
                ->name('settings.professionals.services.restore');

            // Etapa 2.8 — jornada e disponibilidade regular do profissional.
            Route::get('settings/professionals/{professional}/availability', [ProfessionalController::class, 'availability'])
                ->name('settings.professionals.availability.index');
            Route::post('settings/professionals/{professional}/units/{professionalUnit}/working-hours', [ProfessionalWorkingHourController::class, 'store'])
                ->name('settings.professionals.working-hours.store');
            Route::post('settings/professionals/{professional}/units/{professionalUnit}/working-hours/copy', [ProfessionalWorkingHourController::class, 'copy'])
                ->name('settings.professionals.working-hours.copy');
            Route::post('settings/professionals/{professional}/units/{professionalUnit}/working-hours/configure', [ProfessionalWorkingHourController::class, 'configure'])
                ->name('settings.professionals.working-hours.configure');
            Route::put('settings/professionals/{professional}/working-hours/{workingHour}', [ProfessionalWorkingHourController::class, 'update'])
                ->name('settings.professionals.working-hours.update');
            Route::patch('settings/professionals/{professional}/working-hours/{workingHour}/activate', [ProfessionalWorkingHourController::class, 'activate'])
                ->name('settings.professionals.working-hours.activate');
            Route::patch('settings/professionals/{professional}/working-hours/{workingHour}/deactivate', [ProfessionalWorkingHourController::class, 'deactivate'])
                ->name('settings.professionals.working-hours.deactivate');
            Route::delete('settings/professionals/{professional}/working-hours/{workingHour}', [ProfessionalWorkingHourController::class, 'destroy'])
                ->name('settings.professionals.working-hours.destroy');
            Route::post('settings/professionals/{professional}/working-hours/{workingHour}/restore', [ProfessionalWorkingHourController::class, 'restore'])
                ->name('settings.professionals.working-hours.restore');

            // Etapa 2.9 — ausências, folgas e bloqueios do profissional.
            Route::get('settings/professionals/{professional}/time-blocks', [ProfessionalController::class, 'timeBlocks'])
                ->name('settings.professionals.time-blocks.index');
            Route::post('settings/professionals/{professional}/time-blocks', [ProfessionalTimeBlockController::class, 'store'])
                ->name('settings.professionals.time-blocks.store');
            Route::put('settings/professionals/{professional}/time-blocks/{timeBlock}', [ProfessionalTimeBlockController::class, 'update'])
                ->name('settings.professionals.time-blocks.update');
            Route::patch('settings/professionals/{professional}/time-blocks/{timeBlock}/activate', [ProfessionalTimeBlockController::class, 'activate'])
                ->name('settings.professionals.time-blocks.activate');
            Route::patch('settings/professionals/{professional}/time-blocks/{timeBlock}/deactivate', [ProfessionalTimeBlockController::class, 'deactivate'])
                ->name('settings.professionals.time-blocks.deactivate');
            Route::delete('settings/professionals/{professional}/time-blocks/{timeBlock}', [ProfessionalTimeBlockController::class, 'destroy'])
                ->name('settings.professionals.time-blocks.destroy');
            Route::post('settings/professionals/{professional}/time-blocks/{timeBlock}/restore', [ProfessionalTimeBlockController::class, 'restore'])
                ->name('settings.professionals.time-blocks.restore');

            Route::post('settings/professionals/{professional}/photo', [ProfessionalController::class, 'updatePhoto'])
                ->name('settings.professionals.photo.update');
            Route::delete('settings/professionals/{professional}/photo', [ProfessionalController::class, 'destroyPhoto'])
                ->name('settings.professionals.photo.destroy');
            Route::delete('settings/professionals/{professional}', [ProfessionalController::class, 'destroy'])
                ->name('settings.professionals.destroy');
        });

        Route::get('settings/roles', [RoleController::class, 'index'])
            ->name('settings.roles.index');
        Route::post('settings/roles', [RoleController::class, 'store'])
            ->name('settings.roles.store');
        Route::put('settings/roles/{role}', [RoleController::class, 'update'])
            ->name('settings.roles.update');
        Route::put('settings/roles/{role}/permissions', [RoleController::class, 'assignPermissions'])
            ->name('settings.roles.permissions');
        Route::post('settings/roles/{role}/duplicate', [RoleController::class, 'duplicate'])
            ->name('settings.roles.duplicate');
        Route::delete('settings/roles/{role}', [RoleController::class, 'destroy'])
            ->name('settings.roles.destroy');

        Route::get('settings/users', [UserManagementController::class, 'index'])
            ->name('settings.users.index');
        Route::post('settings/users/invite', [UserManagementController::class, 'invite'])
            ->name('settings.users.invite');
        Route::put('settings/users/{membership}', [UserManagementController::class, 'updateMembership'])
            ->name('settings.users.update');
        Route::patch('settings/users/{membership}/activate', [UserManagementController::class, 'activate'])
            ->name('settings.users.activate');
        Route::patch('settings/users/{membership}/deactivate', [UserManagementController::class, 'deactivate'])
            ->name('settings.users.deactivate');

        Route::post('settings/invitations/{invitation}/cancel', [InvitationController::class, 'cancel'])
            ->name('settings.invitations.cancel');
        Route::post('settings/invitations/{invitation}/resend', [InvitationController::class, 'resend'])
            ->middleware('throttle:6,1')
            ->name('settings.invitations.resend');

        Route::get('settings/site', [SiteContentController::class, 'edit'])
            ->name('settings.site.edit');
        Route::put('settings/site', [SiteContentController::class, 'update'])
            ->name('settings.site.update');
        Route::delete('settings/site/hero-image', [SiteContentController::class, 'destroyHeroImage'])
            ->name('settings.site.hero-image.destroy');
        Route::delete('settings/site/hero-image-mobile', [SiteContentController::class, 'destroyHeroImageMobile'])
            ->name('settings.site.hero-image-mobile.destroy');
        Route::delete('settings/site/logo', [SiteContentController::class, 'destroyLogo'])
            ->name('settings.site.logo.destroy');
        Route::delete('settings/site/favicon', [SiteContentController::class, 'destroyFavicon'])
            ->name('settings.site.favicon.destroy');
        Route::patch('settings/site/publish', [SiteContentController::class, 'publish'])
            ->name('settings.site.publish');
        Route::patch('settings/site/unpublish', [SiteContentController::class, 'unpublish'])
            ->name('settings.site.unpublish');

        Route::get('settings/seo', [SeoMarketingController::class, 'edit'])
            ->name('settings.seo.edit');
        Route::put('settings/seo', [SeoMarketingController::class, 'update'])
            ->name('settings.seo.update');

        Route::get('settings/site/sections', [SiteSectionsController::class, 'edit'])
            ->name('settings.site.sections.edit');
        Route::put('settings/site/sections', [SiteSectionsController::class, 'update'])
            ->name('settings.site.sections.update');

        // Coleções de conteúdo da landing pública (benefícios, serviços,
        // profissionais, galeria, depoimentos, FAQ) — mesmo padrão de
        // rotas em todas, ver App\Http\Controllers\Organization\Site*Controller.
        // $param precisa bater com o nome do argumento tipado no Controller
        // (route-model-binding implícito do Laravel resolve pelo nome).
        foreach ([
            'benefits' => [SiteBenefitController::class, 'siteBenefit'],
            'services' => [SiteServiceController::class, 'siteService'],
            'professionals' => [SiteProfessionalController::class, 'siteProfessional'],
            'gallery' => [SiteGalleryItemController::class, 'siteGalleryItem'],
            'testimonials' => [SiteTestimonialController::class, 'siteTestimonial'],
            'faq' => [SiteFaqController::class, 'siteFaq'],
            'partners' => [SitePartnerController::class, 'sitePartner'],
        ] as $segment => [$controller, $param]) {
            Route::get("settings/site/{$segment}", [$controller, 'index'])
                ->name("settings.site.{$segment}.index");
            Route::post("settings/site/{$segment}", [$controller, 'store'])
                ->name("settings.site.{$segment}.store");
            Route::patch("settings/site/{$segment}/reorder", [$controller, 'reorder'])
                ->name("settings.site.{$segment}.reorder");
            Route::put("settings/site/{$segment}/{{$param}}", [$controller, 'update'])
                ->name("settings.site.{$segment}.update");
            Route::patch("settings/site/{$segment}/{{$param}}/toggle", [$controller, 'toggle'])
                ->name("settings.site.{$segment}.toggle");
            Route::delete("settings/site/{$segment}/{{$param}}", [$controller, 'destroy'])
                ->name("settings.site.{$segment}.destroy");
        }

        // Vínculo opcional com o cadastro operacional (Etapa 2.11) — só
        // profissionais e serviços têm um cadastro operacional
        // correspondente; os demais itens de coleção são só promocionais.
        Route::post('settings/site/professionals/{siteProfessional}/link', [SiteProfessionalController::class, 'link'])
            ->name('settings.site.professionals.link');
        Route::delete('settings/site/professionals/{siteProfessional}/link', [SiteProfessionalController::class, 'unlink'])
            ->name('settings.site.professionals.unlink');
        Route::post('settings/site/professionals/{siteProfessional}/copy-public-data', [SiteProfessionalController::class, 'copyPublicData'])
            ->name('settings.site.professionals.copy-public-data');

        Route::post('settings/site/services/{siteService}/link', [SiteServiceController::class, 'link'])
            ->name('settings.site.services.link');
        Route::delete('settings/site/services/{siteService}/link', [SiteServiceController::class, 'unlink'])
            ->name('settings.site.services.unlink');
        Route::post('settings/site/services/{siteService}/copy-public-data', [SiteServiceController::class, 'copyPublicData'])
            ->name('settings.site.services.copy-public-data');

        Route::get('settings/site/appointment-requests', [AppointmentRequestController::class, 'index'])
            ->name('settings.site.appointment-requests.index');
        Route::patch('settings/site/appointment-requests/{appointmentRequest}/status', [AppointmentRequestController::class, 'updateStatus'])
            ->name('settings.site.appointment-requests.status');
        Route::patch('settings/site/appointment-requests/{appointmentRequest}/notes', [AppointmentRequestController::class, 'updateNotes'])
            ->name('settings.site.appointment-requests.notes');

        Route::get('settings/audit', [AuditLogController::class, 'index'])
            ->name('settings.audit.index');
    });
});
