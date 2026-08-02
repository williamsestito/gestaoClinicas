<?php

declare(strict_types=1);

use App\Enums\ProfessionalOperationalStatus;
use App\Enums\ProfessionalTimeBlockScope;
use App\Enums\ProfessionalTimeBlockType;
use App\Enums\RecordStatus;
use App\Enums\Weekday;
use App\Models\LegalEntity;
use App\Models\Organization;
use App\Models\Professional;
use App\Models\ProfessionalRegistration;
use App\Models\ProfessionalTimeBlock;
use App\Models\ProfessionalUnit;
use App\Models\ProfessionalWorkingHour;
use App\Models\Unit;
use App\Services\Professionals\ProfessionalOperationalStatusResolver;

function fullyOperationalProfessional(): Professional
{
    $organization = Organization::factory()->create();
    $legalEntity = LegalEntity::factory()->for($organization)->create();
    $unit = Unit::factory()->for($organization)->create(['legal_entity_id' => $legalEntity->id, 'status' => RecordStatus::Active]);
    $professional = Professional::factory()->for($organization)->create(['status' => RecordStatus::Active]);
    $link = ProfessionalUnit::factory()->for($professional)->create(['organization_id' => $organization->id, 'unit_id' => $unit->id]);
    ProfessionalWorkingHour::factory()->for($link, 'professionalUnit')->create([
        'organization_id' => $organization->id,
        'weekday' => Weekday::Monday,
    ]);

    return $professional;
}

it('is operational when active with unit and working hours', function () {
    $professional = fullyOperationalProfessional();

    $result = (new ProfessionalOperationalStatusResolver)->resolve($professional);

    expect($result->isOperational)->toBeTrue()
        ->and($result->status)->toBe(ProfessionalOperationalStatus::Operational)
        ->and($result->reasons)->toBe([]);
});

it('is inactive when the professional itself is inactive', function () {
    $professional = fullyOperationalProfessional();
    $professional->update(['status' => RecordStatus::Inactive]);

    $result = (new ProfessionalOperationalStatusResolver)->resolve($professional);

    expect($result->isOperational)->toBeFalse()
        ->and($result->status)->toBe(ProfessionalOperationalStatus::Inactive)
        ->and($result->reasons)->toBe(['Profissional inativo.']);
});

it('is incomplete when there is no active unit', function () {
    $organization = Organization::factory()->create();
    $professional = Professional::factory()->for($organization)->create(['status' => RecordStatus::Active]);

    $result = (new ProfessionalOperationalStatusResolver)->resolve($professional);

    expect($result->isOperational)->toBeFalse()
        ->and($result->status)->toBe(ProfessionalOperationalStatus::Incomplete)
        ->and($result->reasons)->toContain('Este profissional ainda não possui unidade de atuação ativa.');
});

it('is incomplete when there is no configured working hour', function () {
    $organization = Organization::factory()->create();
    $legalEntity = LegalEntity::factory()->for($organization)->create();
    $unit = Unit::factory()->for($organization)->create(['legal_entity_id' => $legalEntity->id, 'status' => RecordStatus::Active]);
    $professional = Professional::factory()->for($organization)->create(['status' => RecordStatus::Active]);
    ProfessionalUnit::factory()->for($professional)->create(['organization_id' => $organization->id, 'unit_id' => $unit->id]);

    $result = (new ProfessionalOperationalStatusResolver)->resolve($professional);

    expect($result->isOperational)->toBeFalse()
        ->and($result->reasons)->toContain('Este profissional ainda não possui jornada configurada.');
});

it('warns without blocking when there is no active service', function () {
    $professional = fullyOperationalProfessional();

    $result = (new ProfessionalOperationalStatusResolver)->resolve($professional);

    expect($result->isOperational)->toBeTrue()
        ->and($result->warnings)->toContain('Este profissional ainda não possui serviço ativo.');
});

it('warns when the primary registration is expired', function () {
    $professional = fullyOperationalProfessional();
    ProfessionalRegistration::factory()->for($professional)->create([
        'organization_id' => $professional->organization_id,
        'is_primary' => true,
        'expires_at' => now()->subDay(),
    ]);

    $result = (new ProfessionalOperationalStatusResolver)->resolve($professional);

    expect($result->warnings)->toContain('O registro profissional principal está vencido.');
});

it('warns when there is an ongoing time block', function () {
    $professional = fullyOperationalProfessional();
    ProfessionalTimeBlock::factory()->for($professional)->create([
        'organization_id' => $professional->organization_id,
        'scope' => ProfessionalTimeBlockScope::AllUnits,
        'type' => ProfessionalTimeBlockType::Absence,
        'starts_at' => now()->subHour(),
        'ends_at' => now()->addHour(),
        'is_all_day' => false,
    ]);

    $result = (new ProfessionalOperationalStatusResolver)->resolve($professional);

    expect($result->warnings)->toContain('Há uma ausência em andamento.');
});
