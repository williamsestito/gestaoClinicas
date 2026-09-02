<?php

declare(strict_types=1);

use App\Models\Organization;
use App\Models\Service;
use App\Models\Specialty;
use App\Models\Unit;
use App\Support\ServiceLinkSynchronizer;
use Illuminate\Validation\ValidationException;

it('blocks syncing a specialty that belongs to another organization, even bypassing the Form Request', function () {
    $organization = Organization::factory()->create();
    $service = Service::factory()->for($organization)->create();
    $foreignSpecialty = Specialty::factory()->for(Organization::factory()->create())->create();

    expect(fn () => ServiceLinkSynchronizer::syncSpecialties($service, [$foreignSpecialty->id]))
        ->toThrow(ValidationException::class);

    expect($service->specialtyLinks()->count())->toBe(0);
});

it('blocks syncing a unit that belongs to another organization, even bypassing the Form Request', function () {
    $organization = Organization::factory()->create();
    $service = Service::factory()->for($organization)->create();
    $foreignUnit = Unit::factory()->for(Organization::factory()->create())->create();

    expect(fn () => ServiceLinkSynchronizer::syncUnits($service, [$foreignUnit->id]))
        ->toThrow(ValidationException::class);

    expect($service->unitLinks()->count())->toBe(0);
});

it('syncs specialties and units that genuinely belong to the same organization', function () {
    $organization = Organization::factory()->create();
    $service = Service::factory()->for($organization)->create();
    $specialty = Specialty::factory()->for($organization)->create();
    $unit = Unit::factory()->for($organization)->create();

    ServiceLinkSynchronizer::syncSpecialties($service, [$specialty->id]);
    ServiceLinkSynchronizer::syncUnits($service, [$unit->id]);

    expect($service->specialtyLinks()->pluck('specialty_id')->all())->toBe([$specialty->id])
        ->and($service->unitLinks()->pluck('unit_id')->all())->toBe([$unit->id]);
});
