<?php

declare(strict_types=1);

use App\Enums\RecordStatus;
use App\Enums\Weekday;
use App\Models\Address;
use App\Models\LegalEntity;
use App\Models\Organization;
use App\Models\Professional;
use App\Models\ProfessionalService;
use App\Models\ProfessionalSpecialty;
use App\Models\ProfessionalUnit;
use App\Models\ProfessionalWorkingHour;
use App\Models\Service;
use App\Models\ServiceSpecialty;
use App\Models\Specialty;
use App\Models\Unit;
use App\Services\Availability\PublicAvailabilityFinder;
use Illuminate\Support\Carbon;

/** @return array{0: Organization, 1: Unit, 2: Specialty, 3: Service, 4: Professional} */
function publicAvailabilitySetup(): array
{
    $organization = Organization::factory()->create();
    $legalEntity = LegalEntity::factory()->for($organization)->create();
    $unit = Unit::factory()->for($organization)->create(['legal_entity_id' => $legalEntity->id, 'status' => RecordStatus::Active, 'timezone' => 'America/Sao_Paulo']);
    Address::factory()->for($unit, 'addressable')->create(['neighborhood' => 'Centro', 'city' => 'Blumenau', 'state' => 'SC']);
    $unit->openingHours()->create([
        'organization_id' => $organization->id,
        'day_of_week' => Weekday::Monday->value,
        'opens_at' => '08:00',
        'closes_at' => '18:00',
    ]);

    $specialty = Specialty::factory()->for($organization)->create(['status' => RecordStatus::Active]);
    $service = Service::factory()->for($organization)->create([
        'status' => RecordStatus::Active,
        'is_public' => true,
        'default_duration_minutes' => 30,
        'unit_availability_scope' => 'all_units',
    ]);
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

it('resolves the eligibility chain unit -> specialty -> service -> professional', function () {
    [$organization, $unit, $specialty, $service, $professional] = publicAvailabilitySetup();
    $finder = app(PublicAvailabilityFinder::class);

    expect($finder->eligibleUnits($organization)->pluck('id'))->toContain($unit->id)
        ->and($finder->eligibleSpecialties($organization, $unit->id)->pluck('id'))->toContain($specialty->id)
        ->and($finder->eligibleServices($organization, $unit->id, $specialty->id)->pluck('id'))->toContain($service->id)
        ->and($finder->eligibleProfessionals($organization, $unit->id, $service->id, $specialty->id)->pluck('id'))->toContain($professional->id);
});

it('never lists a specialty without a compatible professional at the chosen unit', function () {
    [$organization, $unit] = publicAvailabilitySetup();
    $orphanSpecialty = Specialty::factory()->for($organization)->create(['status' => RecordStatus::Active]);

    $finder = app(PublicAvailabilityFinder::class);

    expect($finder->eligibleSpecialties($organization, $unit->id)->pluck('id'))->not->toContain($orphanSpecialty->id);
});

it('never lists a service that is not marked as publicly visible', function () {
    [$organization, $unit] = publicAvailabilitySetup();
    $privateService = Service::factory()->for($organization)->create(['status' => RecordStatus::Active, 'is_public' => false, 'unit_availability_scope' => 'all_units']);

    $finder = app(PublicAvailabilityFinder::class);

    expect($finder->eligibleServices($organization, $unit->id, null)->pluck('id'))->not->toContain($privateService->id);
});

it('never lists a professional from another organization', function () {
    [$organization, $unit, , $service] = publicAvailabilitySetup();
    [$foreignOrganization] = publicAvailabilitySetup();

    $finder = app(PublicAvailabilityFinder::class);
    $result = $finder->eligibleProfessionals($organization, $unit->id, $service->id, null);

    expect($result)->toHaveCount(1);
});

it('silently skips a service link pointing to a logically deleted professional', function () {
    [$organization, $unit, $specialty, $service, $professional] = publicAvailabilitySetup();

    $deletedProfessional = Professional::factory()->for($organization)->create(['status' => RecordStatus::Active]);
    ProfessionalService::factory()->for($deletedProfessional)->create(['organization_id' => $organization->id, 'service_id' => $service->id, 'status' => RecordStatus::Active]);
    $deletedProfessional->delete();

    $finder = app(PublicAvailabilityFinder::class);
    $result = $finder->eligibleProfessionals($organization, $unit->id, $service->id, $specialty->id);

    expect($result->pluck('id'))->toContain($professional->id)
        ->not->toContain($deletedProfessional->id);
});

it('marks a monday as available and a tuesday as unavailable within the vigency window', function () {
    [$organization, $unit, $specialty, $service, $professional] = publicAvailabilitySetup();
    $finder = app(PublicAvailabilityFinder::class);

    $dates = $finder->availableDates($organization, $unit->id, $service->id, $professional->id, $specialty->id, Carbon::parse('2026-08-01'));

    expect($dates->firstWhere('date', '2026-08-03')['is_available'])->toBeTrue()
        ->and($dates->firstWhere('date', '2026-08-04')['is_available'])->toBeFalse();
});

it('computes theoretical time slots respecting service duration', function () {
    [$organization, $unit, $specialty, $service, $professional] = publicAvailabilitySetup();
    $finder = app(PublicAvailabilityFinder::class);

    $times = $finder->availableTimes($organization, $unit->id, $service->id, $professional->id, $specialty->id, Carbon::parse('2026-08-03'));

    // Jornada 08:00-10:00, serviço de 30min, sem buffers: 4 horários.
    expect($times->pluck('time')->all())->toBe(['08:00', '08:30', '09:00', '09:30'])
        ->and($times->first()['professional_name'])->toBe($professional->display_name)
        ->and($times->first()['duration_minutes'])->toBe(30);
});

it('unions availability across compatible professionals when "any professional" is requested', function () {
    [$organization, $unit, $specialty, $service] = publicAvailabilitySetup();
    $secondProfessional = Professional::factory()->for($organization)->create(['status' => RecordStatus::Active]);
    $secondLink = ProfessionalUnit::factory()->for($secondProfessional)->create(['organization_id' => $organization->id, 'unit_id' => $unit->id, 'status' => RecordStatus::Active]);
    ProfessionalSpecialty::factory()->for($secondProfessional)->create(['organization_id' => $organization->id, 'specialty_id' => $specialty->id, 'status' => RecordStatus::Active]);
    ProfessionalService::factory()->for($secondProfessional)->create(['organization_id' => $organization->id, 'service_id' => $service->id, 'status' => RecordStatus::Active]);
    $unit->openingHours()->create([
        'organization_id' => $organization->id,
        'day_of_week' => Weekday::Tuesday->value,
        'opens_at' => '08:00',
        'closes_at' => '12:00',
    ]);
    ProfessionalWorkingHour::factory()->for($secondLink, 'professionalUnit')->create([
        'organization_id' => $organization->id,
        'weekday' => Weekday::Tuesday->value,
        'starts_at' => '08:00',
        'ends_at' => '10:00',
    ]);

    $finder = app(PublicAvailabilityFinder::class);
    $dates = $finder->availableDates($organization, $unit->id, $service->id, null, $specialty->id, Carbon::parse('2026-08-01'));

    expect($dates->firstWhere('date', '2026-08-03')['is_available'])->toBeTrue()
        ->and($dates->firstWhere('date', '2026-08-04')['is_available'])->toBeTrue();
});
