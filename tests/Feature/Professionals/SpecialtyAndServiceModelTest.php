<?php

declare(strict_types=1);

use App\Enums\RecordStatus;
use App\Models\Organization;
use App\Models\Service;
use App\Models\Specialty;
use Illuminate\Database\QueryException;

it('creates a specialty scoped to an organization with active status by default', function () {
    $organization = Organization::factory()->create();
    $specialty = Specialty::factory()->for($organization)->create();

    expect($specialty->organization->is($organization))->toBeTrue()
        ->and($specialty->status)->toBe(RecordStatus::Active);
});

it('does not allow two specialties with the same name in the same organization', function () {
    $organization = Organization::factory()->create();
    Specialty::factory()->for($organization)->create(['name' => 'Cardiologia']);

    expect(fn () => Specialty::factory()->for($organization)->create(['name' => 'Cardiologia']))
        ->toThrow(QueryException::class);
});

it('allows the same specialty name to be reused across different organizations', function () {
    $organizationA = Organization::factory()->create();
    $organizationB = Organization::factory()->create();

    Specialty::factory()->for($organizationA)->create(['name' => 'Cardiologia']);
    $second = Specialty::factory()->for($organizationB)->create(['name' => 'Cardiologia']);

    expect($second->exists)->toBeTrue();
});

it('is soft deleted and can be restored (specialty)', function () {
    $specialty = Specialty::factory()->create();
    $specialty->delete();

    expect(Specialty::query()->find($specialty->id))->toBeNull()
        ->and(Specialty::withTrashed()->find($specialty->id)->trashed())->toBeTrue();

    $specialty->restore();
    expect($specialty->fresh()->trashed())->toBeFalse();
});

it('creates a service scoped to an organization with the expected defaults', function () {
    $organization = Organization::factory()->create();
    $service = Service::factory()->for($organization)->create([
        'default_duration_minutes' => 45,
        'default_price_cents' => 12000,
    ]);

    expect($service->organization->is($organization))->toBeTrue()
        ->and($service->status)->toBe(RecordStatus::Active)
        ->and($service->default_duration_minutes)->toBe(45)
        ->and($service->default_price_cents)->toBe(12000)
        ->and($service->is_public)->toBeFalse()
        ->and($service->requires_manual_confirmation)->toBeFalse();
});

it('does not allow two services with the same code in the same organization', function () {
    $organization = Organization::factory()->create();
    Service::factory()->for($organization)->create(['code' => 'SRV-1']);

    expect(fn () => Service::factory()->for($organization)->create(['code' => 'SRV-1']))
        ->toThrow(QueryException::class);
});

it('is soft deleted and can be restored (service)', function () {
    $service = Service::factory()->create();
    $service->delete();

    expect(Service::query()->find($service->id))->toBeNull()
        ->and(Service::withTrashed()->find($service->id)->trashed())->toBeTrue();

    $service->restore();
    expect($service->fresh()->trashed())->toBeFalse();
});
