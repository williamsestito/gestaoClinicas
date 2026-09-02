<?php

declare(strict_types=1);

use App\Models\LegalEntity;
use App\Models\Organization;
use App\Models\Professional;
use App\Models\ProfessionalRegistration;
use App\Models\ProfessionalService;
use App\Models\ProfessionalSpecialty;
use App\Models\ProfessionalUnit;
use App\Models\Service;
use App\Models\Specialty;
use App\Models\Unit;
use Illuminate\Database\QueryException;

it('never returns specialties from another organization when scoped by organization_id', function () {
    $organizationA = Organization::factory()->create();
    $organizationB = Organization::factory()->create();

    Specialty::factory()->for($organizationA)->create();
    Specialty::factory()->for($organizationB)->create();

    $result = Specialty::query()->where('organization_id', $organizationA->id)->get();

    expect($result)->toHaveCount(1)
        ->and($result->first()->organization_id)->toBe($organizationA->id);
});

it('never returns services from another organization when scoped by organization_id', function () {
    $organizationA = Organization::factory()->create();
    $organizationB = Organization::factory()->create();

    Service::factory()->for($organizationA)->create();
    Service::factory()->for($organizationB)->create();

    $result = Service::query()->where('organization_id', $organizationA->id)->get();

    expect($result)->toHaveCount(1);
});

it('never returns professionals from another organization when scoped by organization_id', function () {
    $organizationA = Organization::factory()->create();
    $organizationB = Organization::factory()->create();

    Professional::factory()->for($organizationA)->create();
    Professional::factory()->for($organizationB)->create();

    $result = Professional::query()->where('organization_id', $organizationA->id)->get();

    expect($result)->toHaveCount(1);
});

it('rejects linking a professional to a specialty of a different organization', function () {
    $organizationA = Organization::factory()->create();
    $organizationB = Organization::factory()->create();
    $professional = Professional::factory()->for($organizationA)->create();
    $foreignSpecialty = Specialty::factory()->for($organizationB)->create();

    expect(fn () => ProfessionalSpecialty::query()->create([
        'organization_id' => $professional->organization_id,
        'professional_id' => $professional->id,
        'specialty_id' => $foreignSpecialty->id,
        'status' => 'active',
    ]))->toThrow(QueryException::class);
});

it('rejects linking a professional to a service of a different organization', function () {
    $organizationA = Organization::factory()->create();
    $organizationB = Organization::factory()->create();
    $professional = Professional::factory()->for($organizationA)->create();
    $foreignService = Service::factory()->for($organizationB)->create();

    expect(fn () => ProfessionalService::query()->create([
        'organization_id' => $professional->organization_id,
        'professional_id' => $professional->id,
        'service_id' => $foreignService->id,
        'status' => 'active',
    ]))->toThrow(QueryException::class);
});

it('rejects linking a professional to a unit of a different organization', function () {
    $organizationA = Organization::factory()->create();
    $organizationB = Organization::factory()->create();
    $professional = Professional::factory()->for($organizationA)->create();
    $legalEntityB = LegalEntity::factory()->for($organizationB)->create();
    $foreignUnit = Unit::factory()->for($organizationB)->for($legalEntityB, 'legalEntity')->create();

    expect(fn () => ProfessionalUnit::query()->create([
        'organization_id' => $professional->organization_id,
        'professional_id' => $professional->id,
        'unit_id' => $foreignUnit->id,
        'status' => 'active',
    ]))->toThrow(QueryException::class);
});

it('rejects a professional registration pointing to a professional of another organization even with a valid id', function () {
    $organizationA = Organization::factory()->create();
    $organizationB = Organization::factory()->create();
    $foreignProfessional = Professional::factory()->for($organizationB)->create();

    expect(fn () => ProfessionalRegistration::query()->create([
        'organization_id' => $organizationA->id,
        'professional_id' => $foreignProfessional->id,
        'council' => 'CRM',
        'registration_number' => '999999',
        'state' => 'SP',
        'status' => 'active',
    ]))->toThrow(QueryException::class);
});
