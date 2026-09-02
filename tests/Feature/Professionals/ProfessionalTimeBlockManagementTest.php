<?php

declare(strict_types=1);

use App\Enums\AuditAction;
use App\Enums\OrganizationMembershipStatus;
use App\Enums\PermissionKey;
use App\Enums\ProfessionalTimeBlockScope;
use App\Enums\RecordStatus;
use App\Models\AuditLog;
use App\Models\LegalEntity;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Permission;
use App\Models\Professional;
use App\Models\ProfessionalTimeBlock;
use App\Models\ProfessionalUnit;
use App\Models\Role;
use App\Models\Unit;
use App\Models\UnitMembership;
use App\Models\User;

/** @return array{0: User, 1: Organization, 2: Professional} */
function timeBlockSetup(): array
{
    $user = actingOwnerWithActiveContext();
    $organization = $user->organizationMemberships()->first()->organization;
    $professional = Professional::factory()->for($organization)->create();

    return [$user, $organization, $professional];
}

function unitLinkedTo(Professional $professional): Unit
{
    $legalEntity = LegalEntity::factory()->for($professional->organization)->create();
    $unit = Unit::factory()->for($professional->organization)->create(['legal_entity_id' => $legalEntity->id, 'status' => RecordStatus::Active]);
    ProfessionalUnit::factory()->for($professional)->create(['organization_id' => $professional->organization_id, 'unit_id' => $unit->id]);

    return $unit;
}

it('creates an all-day vacation for all units', function () {
    [$user, , $professional] = timeBlockSetup();

    $this->actingAs($user)->post("/settings/professionals/{$professional->id}/time-blocks", [
        'type' => 'vacation',
        'scope' => 'all_units',
        'is_all_day' => true,
        'starts_date' => '2026-09-01',
        'ends_date' => '2026-09-10',
        'reason' => 'Férias anuais',
    ])->assertRedirect();

    $block = ProfessionalTimeBlock::query()->where('professional_id', $professional->id)->firstOrFail();
    expect($block->scope)->toBe(ProfessionalTimeBlockScope::AllUnits)
        ->and($block->unit_id)->toBeNull()
        ->and($block->is_all_day)->toBeTrue()
        ->and(AuditLog::query()->where('action', AuditAction::Created)->exists())->toBeTrue();
});

it('creates a day off for a specific unit', function () {
    [$user, , $professional] = timeBlockSetup();
    $unit = unitLinkedTo($professional);

    $this->actingAs($user)->post("/settings/professionals/{$professional->id}/time-blocks", [
        'type' => 'day_off',
        'scope' => 'specific_unit',
        'unit_id' => $unit->id,
        'is_all_day' => true,
        'starts_date' => '2026-09-01',
        'ends_date' => '2026-09-01',
    ])->assertRedirect();

    $block = ProfessionalTimeBlock::query()->where('professional_id', $professional->id)->firstOrFail();
    expect($block->scope)->toBe(ProfessionalTimeBlockScope::SpecificUnit)
        ->and($block->unit_id)->toBe($unit->id);
});

it('creates an absence with a specific time range', function () {
    [$user, , $professional] = timeBlockSetup();
    $unit = unitLinkedTo($professional);

    $this->actingAs($user)->post("/settings/professionals/{$professional->id}/time-blocks", [
        'type' => 'absence',
        'scope' => 'specific_unit',
        'unit_id' => $unit->id,
        'is_all_day' => false,
        'starts_date' => '2026-09-01',
        'starts_time' => '09:00',
        'ends_date' => '2026-09-01',
        'ends_time' => '11:00',
    ])->assertRedirect();

    expect(ProfessionalTimeBlock::query()->where('professional_id', $professional->id)->exists())->toBeTrue();
});

it('creates administrative blocks, external events and partial unavailability', function () {
    [$user, , $professional] = timeBlockSetup();
    $unit = unitLinkedTo($professional);

    foreach (['administrative_block', 'external_event', 'partial_unavailability'] as $index => $type) {
        $this->actingAs($user)->post("/settings/professionals/{$professional->id}/time-blocks", [
            'type' => $type,
            'scope' => 'specific_unit',
            'unit_id' => $unit->id,
            'is_all_day' => false,
            'starts_date' => '2026-09-0'.($index + 2),
            'starts_time' => '09:00',
            'ends_date' => '2026-09-0'.($index + 2),
            'ends_time' => '11:00',
        ])->assertRedirect();
    }

    expect(ProfessionalTimeBlock::query()->where('professional_id', $professional->id)->count())->toBe(3);
});

it('rejects a specific unit the professional has no link with', function () {
    [$user, $organization, $professional] = timeBlockSetup();
    $legalEntity = LegalEntity::factory()->for($organization)->create();
    $unlinkedUnit = Unit::factory()->for($organization)->create(['legal_entity_id' => $legalEntity->id, 'status' => RecordStatus::Active]);

    $this->actingAs($user)->post("/settings/professionals/{$professional->id}/time-blocks", [
        'type' => 'absence',
        'scope' => 'specific_unit',
        'unit_id' => $unlinkedUnit->id,
        'is_all_day' => true,
        'starts_date' => '2026-09-01',
        'ends_date' => '2026-09-01',
    ])->assertSessionHasErrors('unit_id');
});

it('rejects a unit belonging to another clinic', function () {
    [$user, , $professional] = timeBlockSetup();
    $foreignUnit = Unit::factory()->for(Organization::factory()->create())->create();

    $this->actingAs($user)->post("/settings/professionals/{$professional->id}/time-blocks", [
        'type' => 'absence',
        'scope' => 'specific_unit',
        'unit_id' => $foreignUnit->id,
        'is_all_day' => true,
        'starts_date' => '2026-09-01',
        'ends_date' => '2026-09-01',
    ])->assertSessionHasErrors('unit_id');
});

it('rejects an end date before the start date', function () {
    [$user, , $professional] = timeBlockSetup();

    $this->actingAs($user)->post("/settings/professionals/{$professional->id}/time-blocks", [
        'type' => 'vacation',
        'scope' => 'all_units',
        'is_all_day' => true,
        'starts_date' => '2026-09-10',
        'ends_date' => '2026-09-01',
    ])->assertSessionHasErrors('ends_date');
});

it('blocks overlapping time blocks in the same unit', function () {
    [$user, , $professional] = timeBlockSetup();
    $unit = unitLinkedTo($professional);

    $this->actingAs($user)->post("/settings/professionals/{$professional->id}/time-blocks", [
        'type' => 'absence', 'scope' => 'specific_unit', 'unit_id' => $unit->id,
        'is_all_day' => false, 'starts_date' => '2026-09-01', 'starts_time' => '09:00',
        'ends_date' => '2026-09-01', 'ends_time' => '11:00',
    ])->assertRedirect();

    $this->actingAs($user)->post("/settings/professionals/{$professional->id}/time-blocks", [
        'type' => 'absence', 'scope' => 'specific_unit', 'unit_id' => $unit->id,
        'is_all_day' => false, 'starts_date' => '2026-09-01', 'starts_time' => '10:00',
        'ends_date' => '2026-09-01', 'ends_time' => '12:00',
    ])->assertSessionHasErrors('starts_date');

    expect(AuditLog::query()->where('action', AuditAction::ConflictDetected)->exists())->toBeTrue();
});

it('blocks a specific-unit time block overlapping an all-units time block', function () {
    [$user, , $professional] = timeBlockSetup();
    $unit = unitLinkedTo($professional);

    $this->actingAs($user)->post("/settings/professionals/{$professional->id}/time-blocks", [
        'type' => 'vacation', 'scope' => 'all_units',
        'is_all_day' => true, 'starts_date' => '2026-09-01', 'ends_date' => '2026-09-10',
    ])->assertRedirect();

    $this->actingAs($user)->post("/settings/professionals/{$professional->id}/time-blocks", [
        'type' => 'absence', 'scope' => 'specific_unit', 'unit_id' => $unit->id,
        'is_all_day' => false, 'starts_date' => '2026-09-05', 'starts_time' => '09:00',
        'ends_date' => '2026-09-05', 'ends_time' => '11:00',
    ])->assertSessionHasErrors('starts_date');
});

it('allows time blocks in different units to coexist', function () {
    [$user, $organization, $professional] = timeBlockSetup();
    $unitA = unitLinkedTo($professional);
    $unitB = unitLinkedTo($professional);

    $this->actingAs($user)->post("/settings/professionals/{$professional->id}/time-blocks", [
        'type' => 'absence', 'scope' => 'specific_unit', 'unit_id' => $unitA->id,
        'is_all_day' => false, 'starts_date' => '2026-09-01', 'starts_time' => '09:00',
        'ends_date' => '2026-09-01', 'ends_time' => '11:00',
    ])->assertRedirect();

    $this->actingAs($user)->post("/settings/professionals/{$professional->id}/time-blocks", [
        'type' => 'absence', 'scope' => 'specific_unit', 'unit_id' => $unitB->id,
        'is_all_day' => false, 'starts_date' => '2026-09-01', 'starts_time' => '09:00',
        'ends_date' => '2026-09-01', 'ends_time' => '11:00',
    ])->assertRedirect();

    expect(ProfessionalTimeBlock::query()->where('professional_id', $professional->id)->count())->toBe(2);
});

it('updates a time block', function () {
    [$user, , $professional] = timeBlockSetup();
    $unit = unitLinkedTo($professional);
    $block = ProfessionalTimeBlock::factory()->for($professional)->create([
        'organization_id' => $professional->organization_id,
        'unit_id' => $unit->id,
        'scope' => ProfessionalTimeBlockScope::SpecificUnit,
    ]);

    $this->actingAs($user)->put("/settings/professionals/{$professional->id}/time-blocks/{$block->id}", [
        'type' => 'day_off',
        'scope' => 'specific_unit',
        'unit_id' => $unit->id,
        'is_all_day' => true,
        'starts_date' => '2026-10-01',
        'ends_date' => '2026-10-01',
        'reason' => 'Atualizado',
    ])->assertRedirect();

    expect($block->fresh()->type->value)->toBe('day_off')
        ->and($block->fresh()->reason)->toBe('Atualizado');
});

it('deactivates and reactivates a time block', function () {
    [$user, , $professional] = timeBlockSetup();
    $block = ProfessionalTimeBlock::factory()->for($professional)->create(['organization_id' => $professional->organization_id]);

    $this->actingAs($user)->patch("/settings/professionals/{$professional->id}/time-blocks/{$block->id}/deactivate")
        ->assertRedirect();
    expect($block->fresh()->status)->toBe(RecordStatus::Inactive);

    $this->actingAs($user)->patch("/settings/professionals/{$professional->id}/time-blocks/{$block->id}/activate")
        ->assertRedirect();
    expect($block->fresh()->status)->toBe(RecordStatus::Active);
});

it('logically deletes a time block and preserves history', function () {
    [$user, , $professional] = timeBlockSetup();
    $block = ProfessionalTimeBlock::factory()->for($professional)->create(['organization_id' => $professional->organization_id]);

    $this->actingAs($user)->delete("/settings/professionals/{$professional->id}/time-blocks/{$block->id}")
        ->assertRedirect();

    expect($block->fresh()->trashed())->toBeTrue()
        ->and(Professional::query()->find($professional->id))->not->toBeNull();
});

it('restores a time block and detects a conflict on restore', function () {
    [$user, , $professional] = timeBlockSetup();
    $block = ProfessionalTimeBlock::factory()->for($professional)->create([
        'organization_id' => $professional->organization_id,
        'starts_at' => '2026-09-01 09:00:00',
        'ends_at' => '2026-09-01 11:00:00',
    ]);
    $block->delete();

    $this->actingAs($user)->post("/settings/professionals/{$professional->id}/time-blocks/{$block->id}/restore")
        ->assertRedirect();
    expect($block->fresh()->trashed())->toBeFalse()
        ->and($block->fresh()->status)->toBe(RecordStatus::Inactive);

    $block->delete();
    ProfessionalTimeBlock::factory()->for($professional)->create([
        'organization_id' => $professional->organization_id,
        'starts_at' => '2026-09-01 09:00:00',
        'ends_at' => '2026-09-01 11:00:00',
    ]);

    $this->actingAs($user)->post("/settings/professionals/{$professional->id}/time-blocks/{$block->id}/restore")
        ->assertSessionHasErrors('starts_date');
});

it('never exposes internal notes to a user without manage access', function () {
    [$user, $organization, $professional] = timeBlockSetup();
    ProfessionalTimeBlock::factory()->for($professional)->create([
        'organization_id' => $organization->id,
        'internal_notes' => 'Segredo interno sensível',
    ]);
    $viewer = User::factory()->create();
    OrganizationMembership::factory()->for($organization)->for($viewer)->create(['status' => OrganizationMembershipStatus::Active]);
    session(['active_organization_id' => $organization->id]);

    $response = $this->actingAs($viewer)->get("/settings/professionals/{$professional->id}/time-blocks");

    $response->assertInertia(fn ($page) => $page
        ->where('timeBlocks.0.internal_notes', null));
});

it('blocks a member without any time-block permission', function () {
    $organization = Organization::factory()->create();
    $professional = Professional::factory()->for($organization)->create();
    $user = User::factory()->create();
    OrganizationMembership::factory()->for($organization)->for($user)->create(['status' => OrganizationMembershipStatus::Active]);
    session(['active_organization_id' => $organization->id]);

    $this->actingAs($user)->post("/settings/professionals/{$professional->id}/time-blocks", [
        'type' => 'vacation', 'scope' => 'all_units',
        'is_all_day' => true, 'starts_date' => '2026-09-01', 'ends_date' => '2026-09-10',
    ])->assertForbidden();
});

it('allows a unit manager scoped to their unit but blocks them from an all-units block', function () {
    $organization = Organization::factory()->create();
    $professional = Professional::factory()->for($organization)->create();
    $legalEntity = LegalEntity::factory()->for($organization)->create();
    $managedUnit = Unit::factory()->for($organization)->create(['legal_entity_id' => $legalEntity->id, 'status' => RecordStatus::Active]);
    ProfessionalUnit::factory()->for($professional)->create(['organization_id' => $organization->id, 'unit_id' => $managedUnit->id]);

    $permission = Permission::query()->create([
        'key' => PermissionKey::ProfessionalTimeBlocksManage->value,
        'group' => PermissionKey::ProfessionalTimeBlocksManage->group(),
        'label' => PermissionKey::ProfessionalTimeBlocksManage->label(),
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

    $this->actingAs($user)->post("/settings/professionals/{$professional->id}/time-blocks", [
        'type' => 'absence', 'scope' => 'specific_unit', 'unit_id' => $managedUnit->id,
        'is_all_day' => false, 'starts_date' => '2026-09-01', 'starts_time' => '09:00',
        'ends_date' => '2026-09-01', 'ends_time' => '11:00',
    ])->assertRedirect();

    $this->actingAs($user)->post("/settings/professionals/{$professional->id}/time-blocks", [
        'type' => 'vacation', 'scope' => 'all_units',
        'is_all_day' => true, 'starts_date' => '2026-10-01', 'ends_date' => '2026-10-10',
    ])->assertForbidden();
});

it('blocks acting on a time block that belongs to a professional from another URL scope', function () {
    [$user, $organization, $professionalA] = timeBlockSetup();
    $professionalB = Professional::factory()->for($organization)->create();
    $blockOfB = ProfessionalTimeBlock::factory()->for($professionalB)->create(['organization_id' => $organization->id]);

    $this->actingAs($user)->patch("/settings/professionals/{$professionalA->id}/time-blocks/{$blockOfB->id}/deactivate")
        ->assertNotFound();
});

it('blocks access to a professional belonging to another organization', function () {
    $user = actingOwnerWithActiveContext();
    $foreignProfessional = Professional::factory()->for(Organization::factory()->create())->create();

    $this->actingAs($user)->get("/settings/professionals/{$foreignProfessional->id}/time-blocks")
        ->assertNotFound();
});
