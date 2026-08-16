<?php

declare(strict_types=1);

use App\Enums\OrganizationMembershipStatus;
use App\Enums\PermissionKey;
use App\Enums\ProfessionalTimeBlockScope;
use App\Enums\ProfessionalTimeBlockType;
use App\Enums\RecordStatus;
use App\Enums\Weekday;
use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Patient;
use App\Models\Permission;
use App\Models\Professional;
use App\Models\ProfessionalService;
use App\Models\ProfessionalTimeBlock;
use App\Models\ProfessionalUnit;
use App\Models\ProfessionalWorkingHour;
use App\Models\Role;
use App\Models\Service;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Organização própria com unidade (fuso America/Sao_Paulo, funcionamento
 * 08:00-18:00 às segundas), profissional com jornada 08:00-18:00às
 * segundas, serviço de 30min sem buffer, e um paciente — pronta para testar
 * criação de agendamento real.
 */
function appointmentSetup(): array
{
    $user = actingOwnerWithActiveContext();
    $organization = $user->organizationMemberships()->first()->organization;
    $unit = $organization->units()->first();
    $unit->update(['timezone' => 'America/Sao_Paulo']);
    $unit->openingHours()->create([
        'organization_id' => $organization->id,
        'day_of_week' => Weekday::Monday->value,
        'opens_at' => '08:00',
        'closes_at' => '18:00',
        'sort_order' => 0,
    ]);

    $professional = Professional::factory()->for($organization)->create(['status' => RecordStatus::Active]);
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
    $link = ProfessionalService::factory()->for($professional)->create([
        'organization_id' => $organization->id,
        'service_id' => $service->id,
    ]);

    $patient = Patient::factory()->for($organization)->create();

    return compact('user', 'organization', 'unit', 'professional', 'professionalUnit', 'service', 'link', 'patient');
}

// 2026-08-03 é uma segunda-feira.
function appointmentMonday(): Carbon
{
    return Carbon::parse('2026-08-03');
}

it('creates a real appointment within the professional availability', function () {
    ['user' => $user, 'organization' => $organization, 'unit' => $unit, 'professional' => $professional, 'service' => $service, 'patient' => $patient] = appointmentSetup();

    $this->actingAs($user)->post('/settings/appointments', [
        'unit_id' => $unit->id,
        'professional_id' => $professional->id,
        'patient_id' => $patient->id,
        'service_id' => $service->id,
        'starts_at' => '2026-08-03T09:00:00',
    ])->assertRedirect('/settings/appointments');

    $appointment = Appointment::query()->where('patient_id', $patient->id)->firstOrFail();
    expect($appointment->status->value)->toBe('confirmed')
        ->and($appointment->professional_id)->toBe($professional->id)
        ->and($appointment->starts_at->setTimezone('America/Sao_Paulo')->format('H:i'))->toBe('09:00')
        ->and($appointment->ends_at->setTimezone('America/Sao_Paulo')->format('H:i'))->toBe('09:30')
        ->and(AuditLog::query()->where('auditable_id', $appointment->id)->exists())->toBeTrue();
});

it('blocks a second appointment overlapping the same professional', function () {
    ['user' => $user, 'unit' => $unit, 'professional' => $professional, 'service' => $service, 'patient' => $patient] = appointmentSetup();
    $otherPatient = Patient::factory()->for($professional->organization)->create();

    $this->actingAs($user)->post('/settings/appointments', [
        'unit_id' => $unit->id,
        'professional_id' => $professional->id,
        'patient_id' => $patient->id,
        'service_id' => $service->id,
        'starts_at' => '2026-08-03T09:00:00',
    ])->assertRedirect();

    $this->actingAs($user)->post('/settings/appointments', [
        'unit_id' => $unit->id,
        'professional_id' => $professional->id,
        'patient_id' => $otherPatient->id,
        'service_id' => $service->id,
        'starts_at' => '2026-08-03T09:15:00',
    ])->assertSessionHasErrors('starts_at');

    expect(Appointment::query()->count())->toBe(1);
});

it('allows a back-to-back appointment right after another ends', function () {
    ['user' => $user, 'unit' => $unit, 'professional' => $professional, 'service' => $service, 'patient' => $patient] = appointmentSetup();
    $otherPatient = Patient::factory()->for($professional->organization)->create();

    $this->actingAs($user)->post('/settings/appointments', [
        'unit_id' => $unit->id,
        'professional_id' => $professional->id,
        'patient_id' => $patient->id,
        'service_id' => $service->id,
        'starts_at' => '2026-08-03T09:00:00',
    ])->assertRedirect();

    $this->actingAs($user)->post('/settings/appointments', [
        'unit_id' => $unit->id,
        'professional_id' => $professional->id,
        'patient_id' => $otherPatient->id,
        'service_id' => $service->id,
        'starts_at' => '2026-08-03T09:30:00',
    ])->assertRedirect();

    expect(Appointment::query()->count())->toBe(2);
});

it('blocks an appointment outside the professional working hours', function () {
    ['user' => $user, 'unit' => $unit, 'professional' => $professional, 'service' => $service, 'patient' => $patient] = appointmentSetup();

    $this->actingAs($user)->post('/settings/appointments', [
        'unit_id' => $unit->id,
        'professional_id' => $professional->id,
        'patient_id' => $patient->id,
        'service_id' => $service->id,
        'starts_at' => '2026-08-03T19:00:00',
    ])->assertSessionHasErrors('starts_at');

    expect(Appointment::query()->count())->toBe(0);
});

it('blocks an appointment during an active time block', function () {
    ['user' => $user, 'organization' => $organization, 'unit' => $unit, 'professional' => $professional, 'service' => $service, 'patient' => $patient] = appointmentSetup();

    ProfessionalTimeBlock::factory()->for($professional)->create([
        'organization_id' => $organization->id,
        'unit_id' => $unit->id,
        'scope' => ProfessionalTimeBlockScope::SpecificUnit,
        'type' => ProfessionalTimeBlockType::Absence,
        'starts_at' => Carbon::parse('2026-08-03 09:00', 'America/Sao_Paulo')->utc(),
        'ends_at' => Carbon::parse('2026-08-03 10:00', 'America/Sao_Paulo')->utc(),
        'is_all_day' => false,
    ]);

    $this->actingAs($user)->post('/settings/appointments', [
        'unit_id' => $unit->id,
        'professional_id' => $professional->id,
        'patient_id' => $patient->id,
        'service_id' => $service->id,
        'starts_at' => '2026-08-03T09:15:00',
    ])->assertSessionHasErrors('starts_at');

    expect(Appointment::query()->count())->toBe(0);
});

it('suggests available slots excluding an already booked time', function () {
    ['user' => $user, 'unit' => $unit, 'professional' => $professional, 'service' => $service, 'patient' => $patient] = appointmentSetup();

    $this->actingAs($user)->post('/settings/appointments', [
        'unit_id' => $unit->id,
        'professional_id' => $professional->id,
        'patient_id' => $patient->id,
        'service_id' => $service->id,
        'starts_at' => '2026-08-03T09:00:00',
    ])->assertRedirect();

    $response = $this->actingAs($user)->getJson(
        "/settings/appointments/available-slots?unit_id={$unit->id}&professional_id={$professional->id}&service_id={$service->id}&date=2026-08-03",
    )->assertOk();

    $times = collect($response->json('slots'))->pluck('time');

    expect($times)->not->toContain('09:00')
        ->and($times)->toContain('08:00')
        ->and($times)->toContain('09:30');
});

it('rejects a patient from another organization even with a valid id', function () {
    ['user' => $user, 'unit' => $unit, 'professional' => $professional, 'service' => $service] = appointmentSetup();
    $foreignPatient = Patient::factory()->for(Organization::factory()->create())->create();

    $this->actingAs($user)->post('/settings/appointments', [
        'unit_id' => $unit->id,
        'professional_id' => $professional->id,
        'patient_id' => $foreignPatient->id,
        'service_id' => $service->id,
        'starts_at' => '2026-08-03T09:00:00',
    ])->assertSessionHasErrors('patient_id');
});

it('blocks a member without the appointments.manage permission from creating an appointment', function () {
    ['unit' => $unit, 'professional' => $professional, 'service' => $service, 'patient' => $patient, 'organization' => $organization] = appointmentSetup();

    $plainUser = User::factory()->create();
    OrganizationMembership::factory()->for($organization)->for($plainUser)->create(['status' => OrganizationMembershipStatus::Active]);
    session(['active_organization_id' => $organization->id]);

    $this->actingAs($plainUser)->post('/settings/appointments', [
        'unit_id' => $unit->id,
        'professional_id' => $professional->id,
        'patient_id' => $patient->id,
        'service_id' => $service->id,
        'starts_at' => '2026-08-03T09:00:00',
    ])->assertForbidden();
});

it('allows a member with the appointments.manage permission to create an appointment', function () {
    ['unit' => $unit, 'professional' => $professional, 'service' => $service, 'patient' => $patient, 'organization' => $organization] = appointmentSetup();

    $memberUser = User::factory()->create();
    $permission = Permission::query()->create([
        'key' => PermissionKey::AppointmentsManage->value,
        'group' => PermissionKey::AppointmentsManage->group(),
        'label' => PermissionKey::AppointmentsManage->label(),
    ]);
    $role = Role::factory()->for($organization)->create();
    $role->permissions()->attach($permission);
    OrganizationMembership::factory()->for($organization)->for($memberUser)->create([
        'status' => OrganizationMembershipStatus::Active,
        'role_id' => $role->id,
    ]);
    session(['active_organization_id' => $organization->id]);

    $this->actingAs($memberUser)->post('/settings/appointments', [
        'unit_id' => $unit->id,
        'professional_id' => $professional->id,
        'patient_id' => $patient->id,
        'service_id' => $service->id,
        'starts_at' => '2026-08-03T09:00:00',
    ])->assertRedirect('/settings/appointments');
});

it('blocks access to an appointment belonging to another organization even with a valid id', function () {
    ['user' => $user] = appointmentSetup();
    $foreignOrganization = Organization::factory()->create();
    $foreignAppointment = Appointment::factory()->for($foreignOrganization)->create();

    $this->actingAs($user)->patch("/settings/appointments/{$foreignAppointment->id}/check-in")
        ->assertNotFound();

    $this->actingAs($user)->patch("/settings/appointments/{$foreignAppointment->id}/cancel", ['reason' => 'x'])
        ->assertNotFound();
});
