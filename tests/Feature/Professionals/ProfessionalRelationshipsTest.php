<?php

declare(strict_types=1);

use App\Models\LegalEntity;
use App\Models\Professional;
use App\Models\ProfessionalService;
use App\Models\ProfessionalSpecialty;
use App\Models\ProfessionalUnit;
use App\Models\Service;
use App\Models\Specialty;
use App\Models\Unit;
use Illuminate\Database\QueryException;

// --- professional_specialty -------------------------------------------------

it('links a professional to several specialties', function () {
    $professional = Professional::factory()->create();
    $specialties = Specialty::factory()->count(3)->for($professional->organization)->create();

    foreach ($specialties as $specialty) {
        ProfessionalSpecialty::factory()->for($professional)->create([
            'organization_id' => $professional->organization_id,
            'specialty_id' => $specialty->id,
        ]);
    }

    expect($professional->specialtyLinks()->count())->toBe(3);
});

it('allows only one primary active specialty per professional', function () {
    $professional = Professional::factory()->create();
    ProfessionalSpecialty::factory()->primary()->for($professional)->create([
        'organization_id' => $professional->organization_id,
    ]);

    expect(fn () => ProfessionalSpecialty::factory()->primary()->for($professional)->create([
        'organization_id' => $professional->organization_id,
    ]))->toThrow(QueryException::class);
});

it('rejects a duplicate active link between the same professional and specialty', function () {
    $professional = Professional::factory()->create();
    $specialty = Specialty::factory()->for($professional->organization)->create();

    ProfessionalSpecialty::factory()->for($professional)->create([
        'organization_id' => $professional->organization_id,
        'specialty_id' => $specialty->id,
    ]);

    expect(fn () => ProfessionalSpecialty::factory()->for($professional)->create([
        'organization_id' => $professional->organization_id,
        'specialty_id' => $specialty->id,
    ]))->toThrow(QueryException::class);
});

it('allows relinking a professional to the same specialty after the previous link is soft deleted', function () {
    $professional = Professional::factory()->create();
    $specialty = Specialty::factory()->for($professional->organization)->create();

    $first = ProfessionalSpecialty::factory()->for($professional)->create([
        'organization_id' => $professional->organization_id,
        'specialty_id' => $specialty->id,
    ]);
    $first->delete();

    $second = ProfessionalSpecialty::factory()->for($professional)->create([
        'organization_id' => $professional->organization_id,
        'specialty_id' => $specialty->id,
    ]);

    expect($second->exists)->toBeTrue()
        ->and(ProfessionalSpecialty::withTrashed()->find($first->id))->not->toBeNull();
});

// --- professional_unit -------------------------------------------------

it('links a professional to several units of the same organization', function () {
    $professional = Professional::factory()->create();
    $legalEntity = LegalEntity::factory()->for($professional->organization)->create();
    $units = Unit::factory()->count(2)->for($professional->organization)->for($legalEntity, 'legalEntity')->create();

    foreach ($units as $unit) {
        ProfessionalUnit::factory()->for($professional)->create([
            'organization_id' => $professional->organization_id,
            'unit_id' => $unit->id,
        ]);
    }

    expect($professional->unitLinks()->count())->toBe(2);
});

it('allows only one primary active unit per professional', function () {
    $professional = Professional::factory()->create();
    ProfessionalUnit::factory()->primary()->for($professional)->create([
        'organization_id' => $professional->organization_id,
    ]);

    expect(fn () => ProfessionalUnit::factory()->primary()->for($professional)->create([
        'organization_id' => $professional->organization_id,
    ]))->toThrow(QueryException::class);
});

it('rejects a duplicate active link between the same professional and unit', function () {
    $professional = Professional::factory()->create();
    $legalEntity = LegalEntity::factory()->for($professional->organization)->create();
    $unit = Unit::factory()->for($professional->organization)->for($legalEntity, 'legalEntity')->create();

    ProfessionalUnit::factory()->for($professional)->create([
        'organization_id' => $professional->organization_id,
        'unit_id' => $unit->id,
    ]);

    expect(fn () => ProfessionalUnit::factory()->for($professional)->create([
        'organization_id' => $professional->organization_id,
        'unit_id' => $unit->id,
    ]))->toThrow(QueryException::class);
});

it('preserves a professional_unit link history after soft delete', function () {
    $link = ProfessionalUnit::factory()->create();
    $link->delete();

    expect(ProfessionalUnit::query()->find($link->id))->toBeNull()
        ->and(ProfessionalUnit::withTrashed()->find($link->id))->not->toBeNull();
});

// --- professional_service -------------------------------------------------

it('links a professional to several services', function () {
    $professional = Professional::factory()->create();
    $services = Service::factory()->count(3)->for($professional->organization)->create();

    foreach ($services as $service) {
        ProfessionalService::factory()->for($professional)->create([
            'organization_id' => $professional->organization_id,
            'service_id' => $service->id,
        ]);
    }

    expect($professional->serviceLinks()->count())->toBe(3);
});

it('rejects a duplicate active link between the same professional and service', function () {
    $professional = Professional::factory()->create();
    $service = Service::factory()->for($professional->organization)->create();

    ProfessionalService::factory()->for($professional)->create([
        'organization_id' => $professional->organization_id,
        'service_id' => $service->id,
    ]);

    expect(fn () => ProfessionalService::factory()->for($professional)->create([
        'organization_id' => $professional->organization_id,
        'service_id' => $service->id,
    ]))->toThrow(QueryException::class);
});

it('uses the service defaults when no custom duration/price is set on the link', function () {
    $link = ProfessionalService::factory()->create();

    expect($link->custom_duration_minutes)->toBeNull()
        ->and($link->custom_price_cents)->toBeNull();
});

it('allows an optional custom duration/price overriding the service defaults', function () {
    $link = ProfessionalService::factory()->create([
        'custom_duration_minutes' => 60,
        'custom_price_cents' => 20000,
    ]);

    expect($link->custom_duration_minutes)->toBe(60)
        ->and($link->custom_price_cents)->toBe(20000);
});
