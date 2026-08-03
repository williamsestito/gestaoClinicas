<?php

declare(strict_types=1);

use App\Enums\RecordStatus;
use App\Enums\Weekday;
use App\Models\LegalEntity;
use App\Models\Organization;
use App\Models\Professional;
use App\Models\ProfessionalTimeBlock;
use App\Models\ProfessionalUnit;
use App\Models\ProfessionalWorkingHour;
use App\Models\Unit;
use Illuminate\Support\Carbon;

it('exposes weekdays, vigency and ongoing blocks summary on the agendas page', function () {
    $user = actingOwnerWithActiveContext();
    $organization = $user->organizationMemberships()->first()->organization;
    $legalEntity = LegalEntity::factory()->for($organization)->create();
    $unit = Unit::factory()->for($organization)->create(['legal_entity_id' => $legalEntity->id, 'status' => RecordStatus::Active]);

    $professional = Professional::factory()->for($organization)->create(['status' => RecordStatus::Active]);
    $link = ProfessionalUnit::factory()->for($professional)->create(['organization_id' => $organization->id, 'unit_id' => $unit->id, 'status' => RecordStatus::Active]);

    ProfessionalWorkingHour::factory()->for($link, 'professionalUnit')->create([
        'organization_id' => $organization->id,
        'weekday' => Weekday::Monday->value,
        'starts_at' => '08:00',
        'ends_at' => '12:00',
        'effective_from' => '2026-08-01',
        'effective_until' => '2026-08-30',
    ]);

    ProfessionalTimeBlock::factory()->for($organization)->for($professional)->create([
        'unit_id' => $unit->id,
        'scope' => 'specific_unit',
        'type' => 'day_off',
        'is_all_day' => true,
        'starts_at' => Carbon::now()->subHour(),
        'ends_at' => Carbon::now()->addHour(),
        'status' => RecordStatus::Active,
    ]);

    $response = $this->actingAs($user)->get('/settings/professionals/agendas');

    $rows = collect($response->viewData('page')['props']['professionals'])->keyBy('id');

    expect($rows[$professional->id]['weekdays'])->toContain(Weekday::Monday)
        ->and($rows[$professional->id]['vigency_from'])->toBe('2026-08-01')
        ->and($rows[$professional->id]['vigency_until'])->toBe('2026-08-30')
        ->and($rows[$professional->id]['has_ongoing_absence'])->toBeTrue()
        ->and($rows[$professional->id]['ongoing_time_blocks_count'])->toBe(1)
        ->and($rows[$professional->id]['operational_status'])->toBe('operational');
});

it('flags a professional with an active unit but no working hours as a conflict alert', function () {
    $user = actingOwnerWithActiveContext();
    $organization = $user->organizationMemberships()->first()->organization;
    $legalEntity = LegalEntity::factory()->for($organization)->create();
    $unit = Unit::factory()->for($organization)->create(['legal_entity_id' => $legalEntity->id, 'status' => RecordStatus::Active]);
    $professional = Professional::factory()->for($organization)->create(['status' => RecordStatus::Active]);
    ProfessionalUnit::factory()->for($professional)->create(['organization_id' => $organization->id, 'unit_id' => $unit->id, 'status' => RecordStatus::Active]);

    $response = $this->actingAs($user)->get('/settings/professionals/agendas');

    $rows = collect($response->viewData('page')['props']['professionals'])->keyBy('id');

    expect($rows[$professional->id]['has_conflict_alert'])->toBeTrue()
        ->and($rows[$professional->id]['operational_status'])->toBe('incomplete');
});

it('never exposes agendas from another organization', function () {
    $user = actingOwnerWithActiveContext();
    $foreignProfessional = Professional::factory()->for(Organization::factory()->create())->create();

    $response = $this->actingAs($user)->get('/settings/professionals/agendas');

    $ids = collect($response->viewData('page')['props']['professionals'])->pluck('id');

    expect($ids)->not->toContain($foreignProfessional->id);
});

it('exposes unit, specialty and service filter options on the agendas page', function () {
    $user = actingOwnerWithActiveContext();

    $this->actingAs($user)->get('/settings/professionals/agendas')
        ->assertInertia(fn ($page) => $page
            ->component('settings/professionals/Agendas')
            ->has('units')
            ->has('specialties')
            ->has('services'));
});
