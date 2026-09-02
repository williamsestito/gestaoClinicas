<?php

declare(strict_types=1);

use App\Enums\AuditAction;
use App\Enums\OrganizationMembershipStatus;
use App\Enums\PermissionKey;
use App\Enums\RecordStatus;
use App\Models\AuditLog;
use App\Models\LegalEntity;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Permission;
use App\Models\Professional;
use App\Models\ProfessionalUnit;
use App\Models\ProfessionalWorkingHour;
use App\Models\Role;
use App\Models\Unit;
use App\Models\UnitMembership;
use App\Models\User;

function unitWithOpeningHours(Organization $organization, array $overrides = []): Unit
{
    $legalEntity = LegalEntity::factory()->for($organization)->create();
    $unit = Unit::factory()->for($organization)->create(array_merge([
        'legal_entity_id' => $legalEntity->id,
        'status' => RecordStatus::Active,
    ], $overrides));
    $unit->openingHours()->create([
        'organization_id' => $organization->id,
        'day_of_week' => 1,
        'opens_at' => '08:00',
        'closes_at' => '18:00',
    ]);

    return $unit;
}

/** @return array{0: User, 1: Organization, 2: Professional, 3: Unit, 4: ProfessionalUnit} */
function workingHourSetup(): array
{
    $user = actingOwnerWithActiveContext();
    $organization = $user->organizationMemberships()->first()->organization;
    $professional = Professional::factory()->for($organization)->create();
    $unit = unitWithOpeningHours($organization);
    $link = ProfessionalUnit::factory()->for($professional)->create(['organization_id' => $organization->id, 'unit_id' => $unit->id]);

    return [$user, $organization, $professional, $unit, $link];
}

it('creates a working hour interval', function () {
    [$user, , $professional, , $link] = workingHourSetup();

    $this->actingAs($user)->post("/settings/professionals/{$professional->id}/units/{$link->id}/working-hours", [
        'weekday' => 1,
        'starts_at' => '08:00',
        'ends_at' => '12:00',
    ])->assertRedirect();

    expect(ProfessionalWorkingHour::query()->where('professional_unit_id', $link->id)->count())->toBe(1)
        ->and(AuditLog::query()->where('action', AuditAction::Created)->exists())->toBeTrue();
});

it('allows several intervals in the same day', function () {
    [$user, , $professional, , $link] = workingHourSetup();

    $this->actingAs($user)->post("/settings/professionals/{$professional->id}/units/{$link->id}/working-hours", [
        'weekday' => 1, 'starts_at' => '08:00', 'ends_at' => '12:00',
    ])->assertRedirect();

    $this->actingAs($user)->post("/settings/professionals/{$professional->id}/units/{$link->id}/working-hours", [
        'weekday' => 1, 'starts_at' => '13:30', 'ends_at' => '18:00',
    ])->assertRedirect();

    expect(ProfessionalWorkingHour::query()->where('professional_unit_id', $link->id)->count())->toBe(2);
});

it('allows intervals in several days', function () {
    [$user, , $professional, $unit, $link] = workingHourSetup();
    $unit->openingHours()->create(['organization_id' => $professional->organization_id, 'day_of_week' => 2, 'opens_at' => '08:00', 'closes_at' => '18:00']);
    $unit->openingHours()->create(['organization_id' => $professional->organization_id, 'day_of_week' => 3, 'opens_at' => '08:00', 'closes_at' => '18:00']);

    foreach ([1, 2, 3] as $weekday) {
        $this->actingAs($user)->post("/settings/professionals/{$professional->id}/units/{$link->id}/working-hours", [
            'weekday' => $weekday, 'starts_at' => '08:00', 'ends_at' => '12:00',
        ])->assertRedirect();
    }

    expect(ProfessionalWorkingHour::query()->where('professional_unit_id', $link->id)->count())->toBe(3);
});

it('rejects an end time equal to or before the start time', function () {
    [$user, , $professional, , $link] = workingHourSetup();

    $this->actingAs($user)->post("/settings/professionals/{$professional->id}/units/{$link->id}/working-hours", [
        'weekday' => 1, 'starts_at' => '10:00', 'ends_at' => '10:00',
    ])->assertSessionHasErrors('ends_at');

    $this->actingAs($user)->post("/settings/professionals/{$professional->id}/units/{$link->id}/working-hours", [
        'weekday' => 1, 'starts_at' => '10:00', 'ends_at' => '09:00',
    ])->assertSessionHasErrors('ends_at');
});

it('rejects a working hour crossing midnight', function () {
    [$user, , $professional, , $link] = workingHourSetup();

    $this->actingAs($user)->post("/settings/professionals/{$professional->id}/units/{$link->id}/working-hours", [
        'weekday' => 1, 'starts_at' => '22:00', 'ends_at' => '02:00',
    ])->assertSessionHasErrors('ends_at');
});

it('rejects an interval outside the unit opening hours', function () {
    [$user, , $professional, , $link] = workingHourSetup();

    $this->actingAs($user)->post("/settings/professionals/{$professional->id}/units/{$link->id}/working-hours", [
        'weekday' => 1, 'starts_at' => '07:00', 'ends_at' => '09:00',
    ])->assertSessionHasErrors('starts_at');
});

it('rejects an interval on a day the unit is closed', function () {
    [$user, , $professional, , $link] = workingHourSetup();

    $this->actingAs($user)->post("/settings/professionals/{$professional->id}/units/{$link->id}/working-hours", [
        'weekday' => 0, 'starts_at' => '08:00', 'ends_at' => '12:00',
    ])->assertSessionHasErrors('starts_at');
});

it('rejects a working hour for an inactive unit', function () {
    [$user, $organization, $professional] = workingHourSetup();
    $inactiveUnit = unitWithOpeningHours($organization, ['status' => RecordStatus::Inactive]);
    $inactiveLink = ProfessionalUnit::factory()->for($professional)->create(['organization_id' => $organization->id, 'unit_id' => $inactiveUnit->id]);

    $this->actingAs($user)->post("/settings/professionals/{$professional->id}/units/{$inactiveLink->id}/working-hours", [
        'weekday' => 1, 'starts_at' => '08:00', 'ends_at' => '12:00',
    ])->assertSessionHasErrors('starts_at');
});

it('blocks overlapping intervals in the same unit', function () {
    [$user, , $professional, , $link] = workingHourSetup();

    $this->actingAs($user)->post("/settings/professionals/{$professional->id}/units/{$link->id}/working-hours", [
        'weekday' => 1, 'starts_at' => '08:00', 'ends_at' => '12:00',
    ])->assertRedirect();

    $this->actingAs($user)->post("/settings/professionals/{$professional->id}/units/{$link->id}/working-hours", [
        'weekday' => 1, 'starts_at' => '10:00', 'ends_at' => '14:00',
    ])->assertSessionHasErrors('starts_at');

    expect(AuditLog::query()->where('action', AuditAction::ConflictDetected)->exists())->toBeTrue();
});

it('blocks overlapping intervals across different units for the same professional', function () {
    [$user, $organization, $professional, , $linkA] = workingHourSetup();
    $unitB = unitWithOpeningHours($organization);
    $linkB = ProfessionalUnit::factory()->for($professional)->create(['organization_id' => $organization->id, 'unit_id' => $unitB->id]);

    $this->actingAs($user)->post("/settings/professionals/{$professional->id}/units/{$linkA->id}/working-hours", [
        'weekday' => 1, 'starts_at' => '08:00', 'ends_at' => '12:00',
    ])->assertRedirect();

    $this->actingAs($user)->post("/settings/professionals/{$professional->id}/units/{$linkB->id}/working-hours", [
        'weekday' => 1, 'starts_at' => '10:00', 'ends_at' => '14:00',
    ])->assertSessionHasErrors('starts_at');
});

it('allows the same time in different units when the effective periods do not overlap', function () {
    [$user, $organization, $professional, , $linkA] = workingHourSetup();
    $unitB = unitWithOpeningHours($organization);
    $linkB = ProfessionalUnit::factory()->for($professional)->create(['organization_id' => $organization->id, 'unit_id' => $unitB->id]);

    $this->actingAs($user)->post("/settings/professionals/{$professional->id}/units/{$linkA->id}/working-hours", [
        'weekday' => 1, 'starts_at' => '08:00', 'ends_at' => '12:00',
        'effective_from' => '2026-01-01', 'effective_until' => '2026-06-30',
    ])->assertRedirect();

    $this->actingAs($user)->post("/settings/professionals/{$professional->id}/units/{$linkB->id}/working-hours", [
        'weekday' => 1, 'starts_at' => '08:00', 'ends_at' => '12:00',
        'effective_from' => '2026-07-01',
    ])->assertRedirect();

    expect(ProfessionalWorkingHour::query()->count())->toBe(2);
});

it('blocks overlapping effective periods across units', function () {
    [$user, $organization, $professional, , $linkA] = workingHourSetup();
    $unitB = unitWithOpeningHours($organization);
    $linkB = ProfessionalUnit::factory()->for($professional)->create(['organization_id' => $organization->id, 'unit_id' => $unitB->id]);

    $this->actingAs($user)->post("/settings/professionals/{$professional->id}/units/{$linkA->id}/working-hours", [
        'weekday' => 1, 'starts_at' => '08:00', 'ends_at' => '12:00',
        'effective_from' => '2026-01-01', 'effective_until' => '2026-12-31',
    ])->assertRedirect();

    $this->actingAs($user)->post("/settings/professionals/{$professional->id}/units/{$linkB->id}/working-hours", [
        'weekday' => 1, 'starts_at' => '08:00', 'ends_at' => '12:00',
        'effective_from' => '2026-06-01',
    ])->assertSessionHasErrors('starts_at');
});

it('copies the intervals of a day to several target days atomically', function () {
    [$user, , $professional, , $link] = workingHourSetup();
    $unit = $link->unit;
    $unit->openingHours()->create(['organization_id' => $professional->organization_id, 'day_of_week' => 2, 'opens_at' => '08:00', 'closes_at' => '18:00']);
    $unit->openingHours()->create(['organization_id' => $professional->organization_id, 'day_of_week' => 3, 'opens_at' => '08:00', 'closes_at' => '18:00']);

    $this->actingAs($user)->post("/settings/professionals/{$professional->id}/units/{$link->id}/working-hours", [
        'weekday' => 1, 'starts_at' => '08:00', 'ends_at' => '12:00',
    ])->assertRedirect();

    $this->actingAs($user)->post("/settings/professionals/{$professional->id}/units/{$link->id}/working-hours/copy", [
        'source_weekday' => 1,
        'target_weekdays' => [2, 3],
    ])->assertRedirect();

    expect(ProfessionalWorkingHour::query()->where('weekday', 2)->exists())->toBeTrue()
        ->and(ProfessionalWorkingHour::query()->where('weekday', 3)->exists())->toBeTrue()
        ->and(AuditLog::query()->where('action', AuditAction::Copied)->exists())->toBeTrue();
});

it('saves nothing when copying to a conflicting target day', function () {
    [$user, , $professional, , $link] = workingHourSetup();
    $unit = $link->unit;
    $unit->openingHours()->create(['organization_id' => $professional->organization_id, 'day_of_week' => 2, 'opens_at' => '08:00', 'closes_at' => '18:00']);

    $this->actingAs($user)->post("/settings/professionals/{$professional->id}/units/{$link->id}/working-hours", [
        'weekday' => 1, 'starts_at' => '08:00', 'ends_at' => '12:00',
    ])->assertRedirect();
    $this->actingAs($user)->post("/settings/professionals/{$professional->id}/units/{$link->id}/working-hours", [
        'weekday' => 2, 'starts_at' => '09:00', 'ends_at' => '11:00',
    ])->assertRedirect();

    $this->actingAs($user)->post("/settings/professionals/{$professional->id}/units/{$link->id}/working-hours/copy", [
        'source_weekday' => 1,
        'target_weekdays' => [2],
    ])->assertSessionHasErrors('target_weekdays.Terça-feira');

    expect(ProfessionalWorkingHour::query()->where('weekday', 2)->count())->toBe(1);
});

it('reports a distinct error per conflicting target day, never dropping the reason for any of them', function () {
    [$user, , $professional, , $link] = workingHourSetup();
    $unit = $link->unit;
    // Terça tem funcionamento compatível; quarta não tem nenhum
    // funcionamento cadastrado — ambas devem aparecer no erro, cada uma
    // com sua própria mensagem.
    $unit->openingHours()->create(['organization_id' => $professional->organization_id, 'day_of_week' => 2, 'opens_at' => '08:00', 'closes_at' => '18:00']);

    $this->actingAs($user)->post("/settings/professionals/{$professional->id}/units/{$link->id}/working-hours", [
        'weekday' => 1, 'starts_at' => '08:00', 'ends_at' => '12:00',
    ])->assertRedirect();
    $this->actingAs($user)->post("/settings/professionals/{$professional->id}/units/{$link->id}/working-hours", [
        'weekday' => 2, 'starts_at' => '09:00', 'ends_at' => '11:00',
    ])->assertRedirect();

    $response = $this->actingAs($user)->post("/settings/professionals/{$professional->id}/units/{$link->id}/working-hours/copy", [
        'source_weekday' => 1,
        'target_weekdays' => [2, 3],
    ]);

    $response->assertSessionHasErrors(['target_weekdays.Terça-feira', 'target_weekdays.Quarta-feira']);
    $errors = session('errors')->getBag('default');
    expect($errors->first('target_weekdays.Terça-feira'))->toContain('sobrepõe')
        ->and($errors->first('target_weekdays.Quarta-feira'))->toContain('fora do funcionamento');
});

it('logically deletes a working hour and preserves history', function () {
    [$user, , $professional, , $link] = workingHourSetup();
    $workingHour = ProfessionalWorkingHour::factory()->for($link, 'professionalUnit')->create(['organization_id' => $professional->organization_id, 'weekday' => 1, 'starts_at' => '08:00', 'ends_at' => '12:00']);

    $this->actingAs($user)->delete("/settings/professionals/{$professional->id}/working-hours/{$workingHour->id}")
        ->assertRedirect();

    expect($workingHour->fresh()->trashed())->toBeTrue()
        ->and(ProfessionalUnit::query()->find($link->id))->not->toBeNull();
});

it('restores a working hour and detects a conflict on restore', function () {
    [$user, , $professional, , $link] = workingHourSetup();
    $workingHour = ProfessionalWorkingHour::factory()->for($link, 'professionalUnit')->create(['organization_id' => $professional->organization_id, 'weekday' => 1, 'starts_at' => '08:00', 'ends_at' => '12:00']);
    $workingHour->delete();

    $this->actingAs($user)->post("/settings/professionals/{$professional->id}/working-hours/{$workingHour->id}/restore")
        ->assertRedirect();
    expect($workingHour->fresh()->trashed())->toBeFalse()
        ->and($workingHour->fresh()->status)->toBe(RecordStatus::Inactive);

    $workingHour->delete();
    ProfessionalWorkingHour::factory()->for($link, 'professionalUnit')->create(['organization_id' => $professional->organization_id, 'weekday' => 1, 'starts_at' => '08:00', 'ends_at' => '12:00']);

    $this->actingAs($user)->post("/settings/professionals/{$professional->id}/working-hours/{$workingHour->id}/restore")
        ->assertSessionHasErrors('starts_at');
});

it('blocks a member without any availability permission', function () {
    $organization = Organization::factory()->create();
    $professional = Professional::factory()->for($organization)->create();
    $unit = unitWithOpeningHours($organization);
    $link = ProfessionalUnit::factory()->for($professional)->create(['organization_id' => $organization->id, 'unit_id' => $unit->id]);
    $user = User::factory()->create();
    OrganizationMembership::factory()->for($organization)->for($user)->create(['status' => OrganizationMembershipStatus::Active]);
    session(['active_organization_id' => $organization->id]);

    $this->actingAs($user)->post("/settings/professionals/{$professional->id}/units/{$link->id}/working-hours", [
        'weekday' => 1, 'starts_at' => '08:00', 'ends_at' => '12:00',
    ])->assertForbidden();
});

it('allows a unit manager scoped to their own unit but blocks them for a different unit', function () {
    $organization = Organization::factory()->create();
    $professional = Professional::factory()->for($organization)->create();
    $managedUnit = unitWithOpeningHours($organization);
    $otherUnit = unitWithOpeningHours($organization);
    $managedLink = ProfessionalUnit::factory()->for($professional)->create(['organization_id' => $organization->id, 'unit_id' => $managedUnit->id]);
    $otherLink = ProfessionalUnit::factory()->for($professional)->create(['organization_id' => $organization->id, 'unit_id' => $otherUnit->id]);

    $permission = Permission::query()->create([
        'key' => PermissionKey::ProfessionalAvailabilityManage->value,
        'group' => PermissionKey::ProfessionalAvailabilityManage->group(),
        'label' => PermissionKey::ProfessionalAvailabilityManage->label(),
    ]);
    $role = Role::factory()->for($organization)->create();
    $role->permissions()->attach($permission);

    $user = User::factory()->create();
    $membership = OrganizationMembership::factory()->for($organization)->for($user)->create([
        'status' => OrganizationMembershipStatus::Active,
        'role_id' => $role->id,
    ]);
    UnitMembership::factory()->for($membership, 'organizationMembership')->for($managedUnit, 'unit')->create(['is_manager' => true, 'status' => RecordStatus::Active]);
    session(['active_organization_id' => $organization->id]);

    $this->actingAs($user)->post("/settings/professionals/{$professional->id}/units/{$managedLink->id}/working-hours", [
        'weekday' => 1, 'starts_at' => '08:00', 'ends_at' => '12:00',
    ])->assertRedirect();

    $this->actingAs($user)->post("/settings/professionals/{$professional->id}/units/{$otherLink->id}/working-hours", [
        'weekday' => 1, 'starts_at' => '08:00', 'ends_at' => '12:00',
    ])->assertForbidden();
});

it('allows a clinic admin with the broad professionals.manage permission on any unit', function () {
    $organization = Organization::factory()->create();
    $professional = Professional::factory()->for($organization)->create();
    $unit = unitWithOpeningHours($organization);
    $link = ProfessionalUnit::factory()->for($professional)->create(['organization_id' => $organization->id, 'unit_id' => $unit->id]);

    $permission = Permission::query()->create([
        'key' => PermissionKey::ProfessionalsManage->value,
        'group' => PermissionKey::ProfessionalsManage->group(),
        'label' => PermissionKey::ProfessionalsManage->label(),
    ]);
    $role = Role::factory()->for($organization)->create();
    $role->permissions()->attach($permission);
    $user = User::factory()->create();
    OrganizationMembership::factory()->for($organization)->for($user)->create([
        'status' => OrganizationMembershipStatus::Active,
        'role_id' => $role->id,
    ]);
    session(['active_organization_id' => $organization->id]);

    $this->actingAs($user)->post("/settings/professionals/{$professional->id}/units/{$link->id}/working-hours", [
        'weekday' => 1, 'starts_at' => '08:00', 'ends_at' => '12:00',
    ])->assertRedirect();
});

it('blocks acting on a working hour that belongs to a professional from another URL scope', function () {
    [$user, $organization, $professionalA, , $linkA] = workingHourSetup();
    $professionalB = Professional::factory()->for($organization)->create();
    $unitB = unitWithOpeningHours($organization);
    $linkB = ProfessionalUnit::factory()->for($professionalB)->create(['organization_id' => $organization->id, 'unit_id' => $unitB->id]);
    $workingHourOfB = ProfessionalWorkingHour::factory()->for($linkB, 'professionalUnit')->create(['organization_id' => $organization->id, 'weekday' => 1, 'starts_at' => '08:00', 'ends_at' => '12:00']);

    $this->actingAs($user)->patch("/settings/professionals/{$professionalA->id}/working-hours/{$workingHourOfB->id}/deactivate")
        ->assertNotFound();

    expect($linkA)->not->toBeNull();
});

it('blocks access to a professional belonging to another organization', function () {
    $user = actingOwnerWithActiveContext();
    $foreignProfessional = Professional::factory()->for(Organization::factory()->create())->create();

    $this->actingAs($user)->get("/settings/professionals/{$foreignProfessional->id}/availability")
        ->assertNotFound();
});

it('configures a full working week in a single batch operation, atomically', function () {
    [$user, $organization, $professional, $unit, $link] = workingHourSetup();

    foreach ([2, 3, 4, 5] as $weekday) {
        $unit->openingHours()->create([
            'organization_id' => $organization->id,
            'day_of_week' => $weekday,
            'opens_at' => '08:00',
            'closes_at' => '18:00',
        ]);
    }

    $this->actingAs($user)->post("/settings/professionals/{$professional->id}/units/{$link->id}/working-hours/configure", [
        'weekdays' => [1, 2, 3, 4, 5],
        'intervals' => [
            ['starts_at' => '08:00', 'ends_at' => '12:00'],
            ['starts_at' => '13:30', 'ends_at' => '18:00'],
        ],
        'effective_from' => '2026-08-01',
        'effective_until' => '2026-08-30',
    ])->assertRedirect();

    expect(ProfessionalWorkingHour::query()->where('professional_unit_id', $link->id)->count())->toBe(10)
        ->and(AuditLog::query()->where('action', AuditAction::Created)->latest()->first()->after_data)
        ->toMatchArray(['weekdays' => [1, 2, 3, 4, 5], 'intervals_count' => 2, 'created_count' => 10]);
});

it('includes saturday and sunday in the batch only when explicitly selected, auditing the inclusion', function () {
    [$user, $organization, $professional, $unit, $link] = workingHourSetup();

    foreach ([0, 6] as $weekday) {
        $unit->openingHours()->create([
            'organization_id' => $organization->id,
            'day_of_week' => $weekday,
            'opens_at' => '09:00',
            'closes_at' => '13:00',
        ]);
    }

    $this->actingAs($user)->post("/settings/professionals/{$professional->id}/units/{$link->id}/working-hours/configure", [
        'weekdays' => [6, 0],
        'intervals' => [['starts_at' => '09:00', 'ends_at' => '13:00']],
        'effective_from' => '2026-08-01',
        'effective_until' => '2026-08-30',
    ])->assertRedirect();

    $log = AuditLog::query()->where('action', AuditAction::Created)->latest()->first();
    expect(ProfessionalWorkingHour::query()->where('professional_unit_id', $link->id)->count())->toBe(2)
        ->and($log->after_data['includes_saturday'])->toBeTrue()
        ->and($log->after_data['includes_sunday'])->toBeTrue();
});

it('does not save anything when any day in the batch conflicts with the unit opening hours', function () {
    [$user, $organization, $professional, $unit, $link] = workingHourSetup();
    // weekday 2 (Tuesday) has no opening hours configured on purpose.

    $this->actingAs($user)->post("/settings/professionals/{$professional->id}/units/{$link->id}/working-hours/configure", [
        'weekdays' => [1, 2],
        'intervals' => [['starts_at' => '08:00', 'ends_at' => '12:00']],
        'effective_from' => '2026-08-01',
        'effective_until' => '2026-08-30',
    ])->assertSessionHasErrors('weekdays');

    expect(ProfessionalWorkingHour::query()->where('professional_unit_id', $link->id)->count())->toBe(0);
});

it('rejects overlapping intervals within the same batch submission', function () {
    [$user, , $professional, , $link] = workingHourSetup();

    $this->actingAs($user)->post("/settings/professionals/{$professional->id}/units/{$link->id}/working-hours/configure", [
        'weekdays' => [1],
        'intervals' => [
            ['starts_at' => '08:00', 'ends_at' => '12:00'],
            ['starts_at' => '11:00', 'ends_at' => '15:00'],
        ],
        'effective_from' => '2026-08-01',
        'effective_until' => '2026-08-30',
    ])->assertSessionHasErrors('intervals');

    expect(ProfessionalWorkingHour::query()->where('professional_unit_id', $link->id)->count())->toBe(0);
});

it('rejects a batch vigency without both start and end dates', function () {
    [$user, , $professional, , $link] = workingHourSetup();

    $this->actingAs($user)->post("/settings/professionals/{$professional->id}/units/{$link->id}/working-hours/configure", [
        'weekdays' => [1],
        'intervals' => [['starts_at' => '08:00', 'ends_at' => '12:00']],
        'effective_from' => '2026-08-01',
    ])->assertSessionHasErrors('effective_until');
});

it('rejects a batch vigency exceeding the maximum allowed period', function () {
    [$user, , $professional, , $link] = workingHourSetup();

    $this->actingAs($user)->post("/settings/professionals/{$professional->id}/units/{$link->id}/working-hours/configure", [
        'weekdays' => [1],
        'intervals' => [['starts_at' => '08:00', 'ends_at' => '12:00']],
        'effective_from' => '2020-01-01',
        'effective_until' => '2030-01-01',
    ])->assertSessionHasErrors('effective_until');
});

it('blocks a member without the manage-own permission from configuring another professional agenda in batch', function () {
    [, $organization, $professional, , $link] = workingHourSetup();
    $member = User::factory()->create();
    OrganizationMembership::factory()->for($organization)->for($member)->create(['status' => OrganizationMembershipStatus::Active]);

    $this->actingAs($member)->post("/settings/professionals/{$professional->id}/units/{$link->id}/working-hours/configure", [
        'weekdays' => [1],
        'intervals' => [['starts_at' => '08:00', 'ends_at' => '12:00']],
        'effective_from' => '2026-08-01',
        'effective_until' => '2026-08-30',
    ])->assertForbidden();
});
