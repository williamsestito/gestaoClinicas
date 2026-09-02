<?php

declare(strict_types=1);

use App\Enums\RecordStatus;
use App\Enums\Weekday;
use App\Models\Address;
use App\Models\AppointmentRequest;
use App\Models\LegalEntity;
use App\Models\Organization;
use App\Models\Professional;
use App\Models\ProfessionalService;
use App\Models\ProfessionalSpecialty;
use App\Models\ProfessionalUnit;
use App\Models\ProfessionalWorkingHour;
use App\Models\Service;
use App\Models\ServiceSpecialty;
use App\Models\SiteSetting;
use App\Models\Specialty;
use App\Models\Unit;
use Illuminate\Support\Carbon;

/**
 * Próxima segunda-feira a partir de agora (nunca hoje, mesmo que hoje já
 * seja segunda) — a jornada de teste só existe às segundas
 * (`publicAvailabilityEndpointSetup()`). Data fixa aqui já quebrou a suíte
 * uma vez ao virar passado com o avanço do relógio real (ver
 * docs/roadmap.md, Etapa 3.7); nunca mais hardcodar uma data absoluta
 * neste arquivo.
 */
function nextAvailableMonday(): Carbon
{
    return Carbon::now()->next(Carbon::MONDAY);
}

/** @return array{0: Organization, 1: Unit, 2: Specialty, 3: Service, 4: Professional} */
function publicAvailabilityEndpointSetup(bool $published = true): array
{
    $organization = Organization::factory()->create();
    SiteSetting::factory()->create(['is_published' => $published]);
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
    $service = Service::factory()->for($organization)->create(['status' => RecordStatus::Active, 'is_public' => true, 'default_duration_minutes' => 30, 'unit_availability_scope' => 'all_units']);
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

    return [$organization, $unit, $specialty, $service, $professional];
}

it('lists eligible units without authentication', function () {
    [, $unit] = publicAvailabilityEndpointSetup();

    $this->get('/disponibilidade/unidades')
        ->assertOk()
        ->assertJsonFragment(['id' => $unit->id]);
});

it('lists eligible specialties for a unit', function () {
    [, $unit, $specialty] = publicAvailabilityEndpointSetup();

    $this->get('/disponibilidade/especialidades?'.http_build_query(['unit_id' => $unit->id]))
        ->assertOk()
        ->assertJsonFragment(['id' => $specialty->id]);
});

it('lists eligible services for a unit and specialty, without internal fields', function () {
    [, $unit, $specialty, $service] = publicAvailabilityEndpointSetup();

    $response = $this->get('/disponibilidade/servicos?'.http_build_query(['unit_id' => $unit->id, 'specialty_id' => $specialty->id]))
        ->assertOk()
        ->assertJsonFragment(['id' => $service->id]);

    expect($response->json('data.0'))->not->toHaveKeys(['internal_notes', 'code', 'organization_id']);
});

it('lists eligible professionals for a unit, service and specialty', function () {
    [, $unit, $specialty, $service, $professional] = publicAvailabilityEndpointSetup();

    $this->get('/disponibilidade/profissionais?'.http_build_query(['unit_id' => $unit->id, 'service_id' => $service->id, 'specialty_id' => $specialty->id]))
        ->assertOk()
        ->assertJsonFragment(['id' => $professional->id]);
});

it('rejects a unit id belonging to another organization', function () {
    [, , $specialty] = publicAvailabilityEndpointSetup();
    $foreignUnit = Unit::factory()->for(Organization::factory()->create())->create(['status' => RecordStatus::Active]);

    $this->getJson('/disponibilidade/servicos?'.http_build_query(['unit_id' => $foreignUnit->id, 'specialty_id' => $specialty->id]))
        ->assertUnprocessable();
});

it('returns available dates for a month, without exposing raw working hours or blocks', function () {
    [, $unit, $specialty, $service, $professional] = publicAvailabilityEndpointSetup();
    $monday = nextAvailableMonday();

    $response = $this->get('/disponibilidade/datas?'.http_build_query([
        'unit_id' => $unit->id,
        'service_id' => $service->id,
        'professional_id' => $professional->id,
        'specialty_id' => $specialty->id,
        'month' => $monday->format('Y-m'),
    ]))->assertOk();

    $mondayEntry = collect($response->json('data'))->firstWhere('date', $monday->toDateString());

    expect($response->json('data.0'))->toHaveKeys(['date', 'is_available'])
        ->and($mondayEntry)->not->toBeNull()
        ->and($mondayEntry['is_available'])->toBeTrue();
});

it('returns available times for a date, with minimal aggregated data', function () {
    [, $unit, $specialty, $service, $professional] = publicAvailabilityEndpointSetup();

    $response = $this->get('/disponibilidade/horarios?'.http_build_query([
        'unit_id' => $unit->id,
        'service_id' => $service->id,
        'professional_id' => $professional->id,
        'specialty_id' => $specialty->id,
        'date' => nextAvailableMonday()->toDateString(),
    ]))->assertOk();

    $slot = $response->json('data.0');
    expect($slot)->toHaveKeys(['time', 'professional_id', 'professional_name', 'unit_name', 'service_name', 'duration_minutes'])
        ->and($slot)->not->toHaveKeys(['reason', 'internal_notes', 'document']);
});

it('never returns real data when the site is not published', function () {
    publicAvailabilityEndpointSetup(published: false);

    $this->get('/disponibilidade/unidades')
        ->assertOk()
        ->assertExactJson(['data' => []]);
});

it('never creates any reservation record when querying availability', function () {
    [, $unit, $specialty, $service, $professional] = publicAvailabilityEndpointSetup();

    $this->get('/disponibilidade/horarios?'.http_build_query([
        'unit_id' => $unit->id,
        'service_id' => $service->id,
        'professional_id' => $professional->id,
        'specialty_id' => $specialty->id,
        'date' => nextAvailableMonday()->toDateString(),
    ]))->assertOk();

    expect(AppointmentRequest::query()->count())->toBe(0);
});
