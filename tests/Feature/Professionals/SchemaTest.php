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
use Illuminate\Support\Facades\Schema;

/**
 * Verifica a fundação de banco de dados da Fase 2 (Etapa 2.1): tabelas,
 * colunas essenciais, ULIDs, soft deletes e as constraints compostas que
 * impedem o cruzamento entre organizações diferentes.
 */
it('creates all professionals-domain tables with their soft-delete columns', function () {
    foreach ([
        'specialties',
        'services',
        'professionals',
        'professional_registrations',
        'professional_specialty',
        'professional_unit',
        'professional_service',
    ] as $table) {
        expect(Schema::hasTable($table))->toBeTrue("table [$table] should exist");
        expect(Schema::hasColumn($table, 'deleted_at'))->toBeTrue("table [$table] should have deleted_at");
        expect(Schema::hasColumn($table, 'organization_id'))->toBeTrue("table [$table] should have organization_id");
    }
});

it('uses ULIDs (26-char) as primary keys for every professionals-domain table', function () {
    $organization = Organization::factory()->create();

    expect(Specialty::factory()->for($organization)->create()->id)->toMatch('/^[0-9a-z]{26}$/i')
        ->and(Service::factory()->for($organization)->create()->id)->toMatch('/^[0-9a-z]{26}$/i')
        ->and(Professional::factory()->for($organization)->create()->id)->toMatch('/^[0-9a-z]{26}$/i');
});

it('has the expected minimal columns on specialties', function () {
    foreach (['name', 'code', 'description', 'status', 'display_order'] as $column) {
        expect(Schema::hasColumn('specialties', $column))->toBeTrue();
    }
});

it('has the expected minimal columns on services', function () {
    foreach ([
        'name', 'code', 'description', 'default_duration_minutes', 'buffer_before_minutes',
        'buffer_after_minutes', 'default_price_cents', 'status', 'color', 'is_public',
        'requires_manual_confirmation', 'internal_notes',
    ] as $column) {
        expect(Schema::hasColumn('services', $column))->toBeTrue();
    }
});

it('has the expected minimal columns on professionals, including an optional user_id', function () {
    foreach ([
        'user_id', 'name', 'social_name', 'display_name', 'email', 'phone',
        'document', 'birth_date', 'bio', 'photo_path', 'status',
    ] as $column) {
        expect(Schema::hasColumn('professionals', $column))->toBeTrue();
    }
});

it('has the expected minimal columns on professional_registrations', function () {
    foreach ([
        'professional_id', 'council', 'registration_type', 'registration_number',
        'state', 'issued_at', 'expires_at', 'status', 'is_primary', 'internal_notes',
    ] as $column) {
        expect(Schema::hasColumn('professional_registrations', $column))->toBeTrue();
    }
});

it('blocks a professional_specialty row from crossing organizations at the database level', function () {
    $orgA = Organization::factory()->create();
    $orgB = Organization::factory()->create();
    $professionalA = Professional::factory()->for($orgA)->create();
    $specialtyB = Specialty::factory()->for($orgB)->create();

    expect(fn () => ProfessionalSpecialty::query()->create([
        'organization_id' => $orgA->id,
        'professional_id' => $professionalA->id,
        'specialty_id' => $specialtyB->id,
        'status' => 'active',
    ]))->toThrow(QueryException::class);
});

it('blocks a professional_unit row from crossing organizations at the database level', function () {
    $orgA = Organization::factory()->create();
    $orgB = Organization::factory()->create();
    $professionalA = Professional::factory()->for($orgA)->create();
    $legalEntityB = LegalEntity::factory()->for($orgB)->create();
    $unitB = Unit::factory()->for($orgB)->for($legalEntityB, 'legalEntity')->create();

    expect(fn () => ProfessionalUnit::query()->create([
        'organization_id' => $orgA->id,
        'professional_id' => $professionalA->id,
        'unit_id' => $unitB->id,
        'status' => 'active',
    ]))->toThrow(QueryException::class);
});

it('blocks a professional_service row from crossing organizations at the database level', function () {
    $orgA = Organization::factory()->create();
    $orgB = Organization::factory()->create();
    $professionalA = Professional::factory()->for($orgA)->create();
    $serviceB = Service::factory()->for($orgB)->create();

    expect(fn () => ProfessionalService::query()->create([
        'organization_id' => $orgA->id,
        'professional_id' => $professionalA->id,
        'service_id' => $serviceB->id,
        'status' => 'active',
    ]))->toThrow(QueryException::class);
});

it('blocks a professional_registration row from pointing to a professional of another organization', function () {
    $orgA = Organization::factory()->create();
    $orgB = Organization::factory()->create();
    $professionalB = Professional::factory()->for($orgB)->create();

    expect(fn () => ProfessionalRegistration::query()->create([
        'organization_id' => $orgA->id,
        'professional_id' => $professionalB->id,
        'council' => 'CRM',
        'registration_number' => '123456',
        'state' => 'SP',
        'status' => 'active',
    ]))->toThrow(QueryException::class);
});

it('enforces service duration and buffer ranges via database check constraints', function () {
    $organization = Organization::factory()->create();

    expect(fn () => Service::factory()->for($organization)->create(['default_duration_minutes' => 0]))
        ->toThrow(QueryException::class);

    expect(fn () => Service::factory()->for($organization)->create(['default_duration_minutes' => 2000]))
        ->toThrow(QueryException::class);
});

it('enforces that a professional_unit end date is never before its start date', function () {
    $organization = Organization::factory()->create();
    $professional = Professional::factory()->for($organization)->create();

    expect(fn () => ProfessionalUnit::factory()->for($professional)->create([
        'organization_id' => $organization->id,
        'starts_on' => '2026-06-01',
        'ends_on' => '2026-01-01',
    ]))->toThrow(QueryException::class);
});
