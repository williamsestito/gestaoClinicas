<?php

declare(strict_types=1);

use App\Enums\PatientUserLinkRole;
use App\Enums\RecordStatus;
use App\Enums\Weekday;
use App\Models\Address;
use App\Models\LegalEntity;
use App\Models\Organization;
use App\Models\Patient;
use App\Models\PatientUser;
use App\Models\PatientUserLink;
use App\Models\Professional;
use App\Models\ProfessionalService;
use App\Models\ProfessionalSpecialty;
use App\Models\ProfessionalUnit;
use App\Models\ProfessionalWorkingHour;
use App\Models\Service;
use App\Models\ServiceSpecialty;
use App\Models\Specialty;
use App\Models\Unit;
use Illuminate\Support\Carbon;

/**
 * Mesma cadeia de disponibilidade de PublicAvailabilityEndpointsTest, mas
 * para o portal do paciente — sem SiteSetting (o portal nunca depende do
 * site público estar publicado) e com um serviço NÃO público (is_public
 * false), já que um paciente autenticado pode agendar qualquer serviço
 * ativo da própria organização, não só os de vitrine.
 *
 * @return array{0: Organization, 1: Unit, 2: Specialty, 3: Service, 4: Professional, 5: PatientUser}
 */
function patientAvailabilitySetup(): array
{
    $organization = Organization::factory()->create();
    $legalEntity = LegalEntity::factory()->for($organization)->create();
    $unit = Unit::factory()->for($organization)->create(['legal_entity_id' => $legalEntity->id, 'status' => RecordStatus::Active]);
    Address::factory()->for($unit, 'addressable')->create();
    $unit->openingHours()->create([
        'organization_id' => $organization->id,
        'day_of_week' => Weekday::Monday->value,
        'opens_at' => '08:00',
        'closes_at' => '18:00',
    ]);

    $specialty = Specialty::factory()->for($organization)->create(['status' => RecordStatus::Active]);
    $service = Service::factory()->for($organization)->create(['status' => RecordStatus::Active, 'is_public' => false, 'default_duration_minutes' => 30, 'unit_availability_scope' => 'all_units']);
    ServiceSpecialty::factory()->for($organization)->for($service)->for($specialty)->create();

    $professional = Professional::factory()->for($organization)->create(['status' => RecordStatus::Active]);
    $link = ProfessionalUnit::factory()->for($professional)->create(['organization_id' => $organization->id, 'unit_id' => $unit->id, 'status' => RecordStatus::Active]);
    ProfessionalSpecialty::factory()->for($professional)->create(['organization_id' => $organization->id, 'specialty_id' => $specialty->id, 'status' => RecordStatus::Active]);
    ProfessionalService::factory()->for($professional)->create(['organization_id' => $organization->id, 'service_id' => $service->id, 'status' => RecordStatus::Active]);
    ProfessionalWorkingHour::factory()->for($link, 'professionalUnit')->create([
        'organization_id' => $organization->id,
        'weekday' => Weekday::Monday->value,
        'starts_at' => '08:00',
        'ends_at' => '10:00',
    ]);

    $patientUser = PatientUser::factory()->for($organization)->create();
    $patient = Patient::factory()->for($organization)->create();
    PatientUserLink::factory()
        ->for($patientUser)
        ->for($patient, 'patient')
        ->create(['organization_id' => $organization->id, 'role' => PatientUserLinkRole::Self]);

    return [$organization, $unit, $specialty, $service, $professional, $patientUser];
}

// Próxima segunda-feira a partir de hoje — nunca uma data fixa no passado,
// já que a validação de disponibilidade rejeita datas anteriores a hoje.
function patientAvailabilityMonday(): Carbon
{
    return Carbon::now()->next(Carbon::MONDAY);
}

it('lists eligible units for the authenticated patient own organization', function () {
    [, $unit, , , , $patientUser] = patientAvailabilitySetup();

    $this->actingAs($patientUser, 'patient')
        ->getJson('/portal/agendamentos/disponibilidade/unidades')
        ->assertOk()
        ->assertJsonFragment(['id' => $unit->id]);
});

it('lists eligible specialties for a unit', function () {
    [, $unit, $specialty, , , $patientUser] = patientAvailabilitySetup();

    $this->actingAs($patientUser, 'patient')
        ->getJson('/portal/agendamentos/disponibilidade/especialidades?'.http_build_query(['unit_id' => $unit->id]))
        ->assertOk()
        ->assertJsonFragment(['id' => $specialty->id]);
});

it('lists a non-public service, unlike the public availability search', function () {
    [, $unit, , $service, , $patientUser] = patientAvailabilitySetup();

    $this->actingAs($patientUser, 'patient')
        ->getJson('/portal/agendamentos/disponibilidade/servicos?'.http_build_query(['unit_id' => $unit->id]))
        ->assertOk()
        ->assertJsonFragment(['id' => $service->id]);
});

it('lists eligible professionals for a service', function () {
    [, $unit, , $service, $professional, $patientUser] = patientAvailabilitySetup();

    $this->actingAs($patientUser, 'patient')
        ->getJson('/portal/agendamentos/disponibilidade/profissionais?'.http_build_query(['unit_id' => $unit->id, 'service_id' => $service->id]))
        ->assertOk()
        ->assertJsonFragment(['id' => $professional->id]);
});

it('lists available dates for a month regardless of the public site being published', function () {
    [, $unit, , $service, , $patientUser] = patientAvailabilitySetup();

    $this->actingAs($patientUser, 'patient')
        ->getJson('/portal/agendamentos/disponibilidade/datas?'.http_build_query([
            'unit_id' => $unit->id,
            'service_id' => $service->id,
            'month' => patientAvailabilityMonday()->format('Y-m'),
        ]))
        ->assertOk()
        ->assertJsonFragment(['date' => patientAvailabilityMonday()->toDateString(), 'is_available' => true]);
});

it('lists available times for a date', function () {
    [, $unit, , $service, $professional, $patientUser] = patientAvailabilitySetup();

    $this->actingAs($patientUser, 'patient')
        ->getJson('/portal/agendamentos/disponibilidade/horarios?'.http_build_query([
            'unit_id' => $unit->id,
            'service_id' => $service->id,
            'date' => patientAvailabilityMonday()->toDateString(),
        ]))
        ->assertOk()
        ->assertJsonFragment(['professional_id' => $professional->id]);
});

it('rejects a unit id from another organization', function () {
    [, , , , , $patientUser] = patientAvailabilitySetup();
    [, $foreignUnit] = patientAvailabilitySetup();

    $this->actingAs($patientUser, 'patient')
        ->getJson('/portal/agendamentos/disponibilidade/especialidades?'.http_build_query(['unit_id' => $foreignUnit->id]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('unit_id');
});

it('rejects a service id from another organization', function () {
    [, $unit, , , , $patientUser] = patientAvailabilitySetup();
    [, , , $foreignService] = patientAvailabilitySetup();

    $this->actingAs($patientUser, 'patient')
        ->getJson('/portal/agendamentos/disponibilidade/profissionais?'.http_build_query(['unit_id' => $unit->id, 'service_id' => $foreignService->id]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('service_id');
});

it('blocks an unauthenticated request from listing availability', function () {
    patientAvailabilitySetup();

    $this->getJson('/portal/agendamentos/disponibilidade/unidades')->assertUnauthorized();
});
