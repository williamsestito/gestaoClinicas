<?php

declare(strict_types=1);

use App\Actions\Organization\SeedSystemRolesAction;
use App\Enums\AppointmentRequestStatus;
use App\Enums\AppointmentStatus;
use App\Enums\OrganizationMembershipStatus;
use App\Enums\RecordStatus;
use App\Enums\SystemRole;
use App\Enums\Weekday;
use App\Models\Appointment;
use App\Models\AppointmentRequest;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Patient;
use App\Models\Professional;
use App\Models\ProfessionalService;
use App\Models\ProfessionalUnit;
use App\Models\ProfessionalWorkingHour;
use App\Models\Role;
use App\Models\Service;
use App\Models\Unit;
use App\Models\UnitMembership;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Organização própria com unidade (America/Sao_Paulo, funcionamento
 * 08:00-18:00 às segundas), profissional vinculado a um User real com o
 * papel de sistema "Profissional" (só App\Enums\PermissionKey::AppointmentsManageOwn,
 * nunca appointments.manage), serviço de 30min sem buffer, e um
 * pré-agendamento pendente atribuído a ele — pronta para testar a
 * conversão em Appointment real e o autoatendimento de reagendar/cancelar.
 *
 * @return array{
 *     professionalUser: User,
 *     organization: Organization,
 *     unit: Unit,
 *     professional: Professional,
 *     service: Service,
 *     patient: Patient,
 *     appointmentRequest: AppointmentRequest,
 * }
 */
function professionalSelfServiceSetup(): array
{
    $organization = Organization::factory()->create();
    app(SeedSystemRolesAction::class)->handle($organization);
    $role = Role::query()->where('organization_id', $organization->id)->where('slug', SystemRole::Professional->value)->firstOrFail();

    $unit = Unit::factory()->for($organization)->create(['timezone' => 'America/Sao_Paulo', 'status' => RecordStatus::Active]);
    $unit->openingHours()->create([
        'organization_id' => $organization->id,
        'day_of_week' => Weekday::Monday->value,
        'opens_at' => '08:00',
        'closes_at' => '18:00',
        'sort_order' => 0,
    ]);

    $professionalUser = User::factory()->create();
    $professional = Professional::factory()->for($organization)->create(['user_id' => $professionalUser->id, 'status' => RecordStatus::Active]);
    $professionalUnit = ProfessionalUnit::factory()->for($professional)->create([
        'organization_id' => $organization->id,
        'unit_id' => $unit->id,
        'status' => RecordStatus::Active,
    ]);
    ProfessionalWorkingHour::factory()->for($professionalUnit, 'professionalUnit')->create([
        'organization_id' => $organization->id,
        'weekday' => Weekday::Monday,
        'starts_at' => '08:00',
        'ends_at' => '18:00',
    ]);

    $service = Service::factory()->for($organization)->create([
        'default_duration_minutes' => 30,
        'buffer_before_minutes' => 0,
        'buffer_after_minutes' => 0,
    ]);
    ProfessionalService::factory()->for($professional)->create([
        'organization_id' => $organization->id,
        'service_id' => $service->id,
    ]);

    $patient = Patient::factory()->for($organization)->create();

    $membership = OrganizationMembership::factory()->for($organization)->for($professionalUser)->create([
        'status' => OrganizationMembershipStatus::Active,
        'role_id' => $role->id,
    ]);
    // Sem isto, App\Http\Middleware\ResolveUnitContext não acha nenhuma
    // unidade ativa para o vínculo (unitMemberships() é distinto de
    // ProfessionalUnit — vínculo de acesso do staff à unidade, não o
    // vínculo clínico do profissional) e EnsureActiveUnit redireciona
    // qualquer rota vinculada a unidade, incluindo /dashboard.
    UnitMembership::factory()->for($membership)->create([
        'unit_id' => $unit->id,
        'status' => RecordStatus::Active,
    ]);
    session(['active_organization_id' => $organization->id]);

    $appointmentRequest = AppointmentRequest::factory()->for($organization)->for($professional)->create([
        'status' => AppointmentRequestStatus::Pending,
    ]);

    return compact('professionalUser', 'organization', 'unit', 'professional', 'service', 'patient', 'appointmentRequest');
}

// 2026-08-03 é uma segunda-feira.
function professionalSelfServiceMonday(): Carbon
{
    return Carbon::parse('2026-08-03');
}

it('lets a professional convert their own pending request into a real appointment, visible in their dashboard agenda', function () {
    $setup = professionalSelfServiceSetup();

    $this->actingAs($setup['professionalUser'])->post('/settings/appointments', [
        'unit_id' => $setup['unit']->id,
        'professional_id' => $setup['professional']->id,
        'patient_id' => $setup['patient']->id,
        'service_id' => $setup['service']->id,
        'starts_at' => professionalSelfServiceMonday()->toDateString().'T09:00:00',
        'appointment_request_id' => $setup['appointmentRequest']->id,
    ])->assertRedirect();

    $appointment = Appointment::query()->where('professional_id', $setup['professional']->id)->firstOrFail();
    expect($appointment->status)->toBe(AppointmentStatus::Confirmed);

    $freshRequest = $setup['appointmentRequest']->fresh();
    expect($freshRequest->status)->toBe(AppointmentRequestStatus::Scheduled)
        ->and($freshRequest->appointment_id)->toBe($appointment->id);

    // Aparece na agenda do próprio profissional (DashboardController).
    $dashboard = $this->actingAs($setup['professionalUser'])->get('/dashboard?period=day&date='.professionalSelfServiceMonday()->toDateString());
    $dashboard->assertOk()->assertInertia(fn ($page) => $page
        ->where('professionalDashboard.agenda.0.id', $appointment->id));
});

it('prefills the create-appointment page with the unit and professional already known from the source request', function () {
    $setup = professionalSelfServiceSetup();
    $setup['appointmentRequest']->update(['unit_id' => $setup['unit']->id]);

    $response = $this->actingAs($setup['professionalUser'])->get(
        '/settings/appointments/create?appointment_request_id='.$setup['appointmentRequest']->id,
    );

    $response->assertOk()->assertInertia(fn ($page) => $page
        ->where('prefill.unit_id', $setup['unit']->id)
        ->where('prefill.professional_id', $setup['professional']->id));
});

it('leaves the unit prefill null when the source request never had one on file', function () {
    $setup = professionalSelfServiceSetup();

    $response = $this->actingAs($setup['professionalUser'])->get(
        '/settings/appointments/create?appointment_request_id='.$setup['appointmentRequest']->id,
    );

    $response->assertOk()->assertInertia(fn ($page) => $page
        ->where('prefill.unit_id', null)
        ->where('prefill.professional_id', $setup['professional']->id));
});

it('blocks a professional from converting a colleague\'s pending request', function () {
    $setup = professionalSelfServiceSetup();
    $colleague = Professional::factory()->for($setup['organization'])->create(['status' => RecordStatus::Active]);
    $colleagueRequest = AppointmentRequest::factory()->for($setup['organization'])->for($colleague)->create();

    $this->actingAs($setup['professionalUser'])->get(
        '/settings/appointments/create?appointment_request_id='.$colleagueRequest->id,
    )->assertForbidden();

    $this->actingAs($setup['professionalUser'])->post('/settings/appointments', [
        'unit_id' => $setup['unit']->id,
        'professional_id' => $setup['professional']->id,
        'patient_id' => $setup['patient']->id,
        'service_id' => $setup['service']->id,
        'starts_at' => professionalSelfServiceMonday()->toDateString().'T09:00:00',
        'appointment_request_id' => $colleagueRequest->id,
    ])->assertForbidden();

    expect(Appointment::query()->count())->toBe(0);
});

it('blocks a professional from tampering professional_id to book under a colleague\'s schedule using their own authorized request', function () {
    $setup = professionalSelfServiceSetup();
    $colleague = Professional::factory()->for($setup['organization'])->create(['status' => RecordStatus::Active]);
    ProfessionalUnit::factory()->for($colleague)->create(['organization_id' => $setup['organization']->id, 'unit_id' => $setup['unit']->id, 'status' => RecordStatus::Active]);
    ProfessionalService::factory()->for($colleague)->create(['organization_id' => $setup['organization']->id, 'service_id' => $setup['service']->id]);

    $this->actingAs($setup['professionalUser'])->post('/settings/appointments', [
        'unit_id' => $setup['unit']->id,
        'professional_id' => $colleague->id,
        'patient_id' => $setup['patient']->id,
        'service_id' => $setup['service']->id,
        'starts_at' => professionalSelfServiceMonday()->toDateString().'T09:00:00',
        'appointment_request_id' => $setup['appointmentRequest']->id,
    ])->assertForbidden();

    expect(Appointment::query()->where('professional_id', $colleague->id)->count())->toBe(0);
});

it('lets a professional reschedule their own appointment', function () {
    $setup = professionalSelfServiceSetup();
    $appointment = Appointment::factory()->for($setup['organization'])->for($setup['professional'])->for($setup['unit'])->for($setup['service'])->for($setup['patient'])->create([
        'status' => AppointmentStatus::Confirmed,
        'starts_at' => professionalSelfServiceMonday()->setTime(9, 0),
        'ends_at' => professionalSelfServiceMonday()->setTime(9, 30),
    ]);

    $this->actingAs($setup['professionalUser'])->put("/settings/appointments/{$appointment->id}/reschedule", [
        'starts_at' => professionalSelfServiceMonday()->toDateString().'T10:00:00',
    ])->assertRedirect();

    expect($appointment->fresh()->starts_at->setTimezone($setup['unit']->timezone)->format('H:i'))->toBe('10:00');
});

it('blocks a professional from rescheduling a colleague\'s appointment', function () {
    $setup = professionalSelfServiceSetup();
    $colleague = Professional::factory()->for($setup['organization'])->create(['status' => RecordStatus::Active]);
    $appointment = Appointment::factory()->for($setup['organization'])->for($colleague)->for($setup['unit'])->for($setup['service'])->for($setup['patient'])->create([
        'status' => AppointmentStatus::Confirmed,
        'starts_at' => professionalSelfServiceMonday()->setTime(9, 0),
        'ends_at' => professionalSelfServiceMonday()->setTime(9, 30),
    ]);

    $this->actingAs($setup['professionalUser'])->put("/settings/appointments/{$appointment->id}/reschedule", [
        'starts_at' => professionalSelfServiceMonday()->toDateString().'T10:00:00',
    ])->assertForbidden();

    expect($appointment->fresh()->starts_at->format('H:i'))->toBe(professionalSelfServiceMonday()->setTime(9, 0)->format('H:i'));
});

it('lets a professional cancel their own appointment', function () {
    $setup = professionalSelfServiceSetup();
    $appointment = Appointment::factory()->for($setup['organization'])->for($setup['professional'])->for($setup['unit'])->for($setup['service'])->for($setup['patient'])->create([
        'status' => AppointmentStatus::Confirmed,
    ]);

    $this->actingAs($setup['professionalUser'])->patch("/settings/appointments/{$appointment->id}/cancel", [
        'reason' => 'Paciente pediu para remarcar.',
    ])->assertRedirect();

    expect($appointment->fresh()->status)->toBe(AppointmentStatus::Cancelled);
});

it('blocks a professional from cancelling a colleague\'s appointment', function () {
    $setup = professionalSelfServiceSetup();
    $colleague = Professional::factory()->for($setup['organization'])->create(['status' => RecordStatus::Active]);
    $appointment = Appointment::factory()->for($setup['organization'])->for($colleague)->for($setup['unit'])->for($setup['service'])->for($setup['patient'])->create([
        'status' => AppointmentStatus::Confirmed,
    ]);

    $this->actingAs($setup['professionalUser'])->patch("/settings/appointments/{$appointment->id}/cancel", [
        'reason' => 'Tentativa indevida.',
    ])->assertForbidden();

    expect($appointment->fresh()->status)->toBe(AppointmentStatus::Confirmed);
});

it('rejects status=scheduled on the professional self-service appointment request status update', function () {
    $setup = professionalSelfServiceSetup();

    $this->actingAs($setup['professionalUser'])->patch(
        "/settings/meus-pre-agendamentos/{$setup['appointmentRequest']->id}/status",
        ['status' => 'scheduled'],
    )->assertSessionHasErrors('status');

    expect($setup['appointmentRequest']->fresh()->status)->toBe(AppointmentRequestStatus::Pending);
});

it('rejects status=scheduled on the admin-wide appointment request status update', function () {
    $owner = actingOwnerWithActiveContext();
    $organization = $owner->organizationMemberships()->first()->organization;
    $request = AppointmentRequest::factory()->for($organization)->create();

    $this->actingAs($owner)->patch(
        "/settings/site/appointment-requests/{$request->id}/status",
        ['status' => 'scheduled'],
    )->assertSessionHasErrors('status');

    expect($request->fresh()->status)->toBe(AppointmentRequestStatus::Pending);
});
