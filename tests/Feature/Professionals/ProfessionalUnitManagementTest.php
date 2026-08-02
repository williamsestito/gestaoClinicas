<?php

declare(strict_types=1);

use App\Enums\AuditAction;
use App\Enums\OrganizationMembershipStatus;
use App\Enums\PermissionKey;
use App\Enums\ProfessionalUnitVigencyStatus;
use App\Enums\RecordStatus;
use App\Models\AuditLog;
use App\Models\LegalEntity;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Permission;
use App\Models\Professional;
use App\Models\ProfessionalUnit;
use App\Models\Role;
use App\Models\Unit;
use App\Models\UnitMembership;
use App\Models\User;
use Illuminate\Database\QueryException;

function professionalWithUnitOwnedByOwner(): array
{
    $user = actingOwnerWithActiveContext();
    $organization = $user->organizationMemberships()->first()->organization;
    $professional = Professional::factory()->for($organization)->create();

    return [$user, $organization, $professional];
}

function unitFor(Organization $organization): Unit
{
    $legalEntity = LegalEntity::factory()->for($organization)->create();

    return Unit::factory()->for($organization)->create([
        'legal_entity_id' => $legalEntity->id,
        'status' => RecordStatus::Active,
    ]);
}

it('assigns a professional to a unit', function () {
    [$user, $organization, $professional] = professionalWithUnitOwnedByOwner();
    $unit = unitFor($organization);

    $this->actingAs($user)->post("/settings/professionals/{$professional->id}/units", [
        'unit_id' => $unit->id,
    ])->assertRedirect();

    expect(ProfessionalUnit::query()->where('professional_id', $professional->id)->where('unit_id', $unit->id)->exists())->toBeTrue()
        ->and(AuditLog::query()->where('action', AuditAction::Created)->whereNotNull('auditable_id')->exists())->toBeTrue();
});

it('never creates or alters a UnitMembership when linking a professional to a unit', function () {
    [$user, $organization, $professional] = professionalWithUnitOwnedByOwner();
    $unit = unitFor($organization);
    $before = UnitMembership::query()->count();

    $this->actingAs($user)->post("/settings/professionals/{$professional->id}/units", [
        'unit_id' => $unit->id,
    ])->assertRedirect();

    expect(UnitMembership::query()->count())->toBe($before);
});

it('allows several units for the same professional', function () {
    [$user, $organization, $professional] = professionalWithUnitOwnedByOwner();
    $units = [unitFor($organization), unitFor($organization), unitFor($organization)];

    foreach ($units as $unit) {
        $this->actingAs($user)->post("/settings/professionals/{$professional->id}/units", [
            'unit_id' => $unit->id,
        ])->assertRedirect();
    }

    expect($professional->unitLinks()->count())->toBe(3);
});

it('rejects assigning an inactive or deleted unit', function () {
    [$user, $organization, $professional] = professionalWithUnitOwnedByOwner();
    $inactive = unitFor($organization);
    $inactive->update(['status' => RecordStatus::Inactive]);
    $deleted = unitFor($organization);
    $deleted->delete();

    $this->actingAs($user)->post("/settings/professionals/{$professional->id}/units", [
        'unit_id' => $inactive->id,
    ])->assertSessionHasErrors('unit_id');

    $this->actingAs($user)->post("/settings/professionals/{$professional->id}/units", [
        'unit_id' => $deleted->id,
    ])->assertSessionHasErrors('unit_id');
});

it('rejects a unit belonging to another clinic', function () {
    [$user, , $professional] = professionalWithUnitOwnedByOwner();
    $foreignUnit = unitFor(Organization::factory()->create());

    $this->actingAs($user)->post("/settings/professionals/{$professional->id}/units", [
        'unit_id' => $foreignUnit->id,
    ])->assertSessionHasErrors('unit_id');
});

it('rejects a duplicate active link', function () {
    [$user, $organization, $professional] = professionalWithUnitOwnedByOwner();
    $unit = unitFor($organization);
    ProfessionalUnit::factory()->for($professional)->create(['organization_id' => $organization->id, 'unit_id' => $unit->id]);

    $this->actingAs($user)->post("/settings/professionals/{$professional->id}/units", [
        'unit_id' => $unit->id,
    ])->assertSessionHasErrors('unit_id');
});

it('rejects an end date before the start date', function () {
    [$user, $organization, $professional] = professionalWithUnitOwnedByOwner();
    $unit = unitFor($organization);

    $this->actingAs($user)->post("/settings/professionals/{$professional->id}/units", [
        'unit_id' => $unit->id,
        'starts_on' => '2026-09-10',
        'ends_on' => '2026-09-01',
    ])->assertSessionHasErrors('ends_on');
});

it('updates the vigency dates of a unit link', function () {
    [$user, $organization, $professional] = professionalWithUnitOwnedByOwner();
    $link = ProfessionalUnit::factory()->for($professional)->create(['organization_id' => $organization->id]);

    $this->actingAs($user)->put("/settings/professionals/{$professional->id}/units/{$link->id}", [
        'starts_on' => '2026-09-01',
        'ends_on' => '2026-12-31',
    ])->assertRedirect();

    expect($link->fresh()->starts_on->format('Y-m-d'))->toBe('2026-09-01')
        ->and($link->fresh()->ends_on->format('Y-m-d'))->toBe('2026-12-31');
});

it('computes the vigency status from the dates', function () {
    $future = ProfessionalUnit::factory()->make(['starts_on' => now()->addDays(10), 'ends_on' => null]);
    $current = ProfessionalUnit::factory()->make(['starts_on' => now()->subDays(10), 'ends_on' => now()->addDays(10)]);
    $noDates = ProfessionalUnit::factory()->make(['starts_on' => null, 'ends_on' => null]);
    $ended = ProfessionalUnit::factory()->make(['starts_on' => now()->subDays(30), 'ends_on' => now()->subDays(1)]);

    expect($future->vigencyStatus())->toBe(ProfessionalUnitVigencyStatus::Scheduled)
        ->and($current->vigencyStatus())->toBe(ProfessionalUnitVigencyStatus::InEffect)
        ->and($noDates->vigencyStatus())->toBe(ProfessionalUnitVigencyStatus::InEffect)
        ->and($ended->vigencyStatus())->toBe(ProfessionalUnitVigencyStatus::Ended);
});

it('sets and swaps the primary unit transactionally', function () {
    [$user, $organization, $professional] = professionalWithUnitOwnedByOwner();
    $linkA = ProfessionalUnit::factory()->primary()->for($professional)->create(['organization_id' => $organization->id]);
    $linkB = ProfessionalUnit::factory()->for($professional)->create(['organization_id' => $organization->id]);

    $this->actingAs($user)->patch("/settings/professionals/{$professional->id}/units/{$linkB->id}/primary")
        ->assertRedirect();

    expect($linkA->fresh()->is_primary)->toBeFalse()
        ->and($linkB->fresh()->is_primary)->toBeTrue();
});

it('never allows two active primary units for the same professional', function () {
    [, $organization, $professional] = professionalWithUnitOwnedByOwner();
    ProfessionalUnit::factory()->primary()->for($professional)->create(['organization_id' => $organization->id]);

    expect(fn () => ProfessionalUnit::factory()->primary()->for($professional)->create(['organization_id' => $organization->id]))
        ->toThrow(QueryException::class);
});

it('blocks deactivating the primary unit when other active links exist', function () {
    [$user, $organization, $professional] = professionalWithUnitOwnedByOwner();
    $primary = ProfessionalUnit::factory()->primary()->for($professional)->create(['organization_id' => $organization->id]);
    ProfessionalUnit::factory()->for($professional)->create(['organization_id' => $organization->id]);

    $this->actingAs($user)->patch("/settings/professionals/{$professional->id}/units/{$primary->id}/deactivate")
        ->assertSessionHasErrors('unit');

    expect($primary->fresh()->status)->toBe(RecordStatus::Active);
});

it('allows deactivating the primary unit when it is the only active link', function () {
    [$user, $organization, $professional] = professionalWithUnitOwnedByOwner();
    $primary = ProfessionalUnit::factory()->primary()->for($professional)->create(['organization_id' => $organization->id]);

    $this->actingAs($user)->patch("/settings/professionals/{$professional->id}/units/{$primary->id}/deactivate")
        ->assertRedirect();

    expect($primary->fresh()->status)->toBe(RecordStatus::Inactive);
});

it('blocks removing the primary unit when other active links exist', function () {
    [$user, $organization, $professional] = professionalWithUnitOwnedByOwner();
    $primary = ProfessionalUnit::factory()->primary()->for($professional)->create(['organization_id' => $organization->id]);
    ProfessionalUnit::factory()->for($professional)->create(['organization_id' => $organization->id]);

    $this->actingAs($user)->delete("/settings/professionals/{$professional->id}/units/{$primary->id}")
        ->assertSessionHasErrors('unit');

    expect($primary->fresh()->trashed())->toBeFalse();
});

it('logically removes a unit link and preserves history', function () {
    [$user, $organization, $professional] = professionalWithUnitOwnedByOwner();
    $link = ProfessionalUnit::factory()->for($professional)->create(['organization_id' => $organization->id]);

    $this->actingAs($user)->delete("/settings/professionals/{$professional->id}/units/{$link->id}")
        ->assertRedirect();

    expect($link->fresh()->trashed())->toBeTrue()
        ->and(Unit::query()->find($link->unit_id))->not->toBeNull()
        ->and(Professional::query()->find($professional->id))->not->toBeNull();
});

it('restores a unit link and detects an active duplicate conflict', function () {
    [$user, $organization, $professional] = professionalWithUnitOwnedByOwner();
    $unit = unitFor($organization);
    $link = ProfessionalUnit::factory()->for($professional)->create(['organization_id' => $organization->id, 'unit_id' => $unit->id]);
    $link->delete();

    $this->actingAs($user)->post("/settings/professionals/{$professional->id}/units/{$link->id}/restore")
        ->assertRedirect();
    expect($link->fresh()->trashed())->toBeFalse()
        ->and($link->fresh()->status)->toBe(RecordStatus::Inactive);

    $link->delete();
    ProfessionalUnit::factory()->for($professional)->create(['organization_id' => $organization->id, 'unit_id' => $unit->id]);

    $this->actingAs($user)->post("/settings/professionals/{$professional->id}/units/{$link->id}/restore")
        ->assertSessionHasErrors('unit');
});

it('blocks a member without the manage-units permission', function () {
    $organization = Organization::factory()->create();
    $professional = Professional::factory()->for($organization)->create();
    $user = User::factory()->create();
    OrganizationMembership::factory()->for($organization)->for($user)->create(['status' => OrganizationMembershipStatus::Active]);
    session(['active_organization_id' => $organization->id]);
    $unit = unitFor($organization);

    $this->actingAs($user)->post("/settings/professionals/{$professional->id}/units", [
        'unit_id' => $unit->id,
    ])->assertForbidden();
});

it('allows a member with the professionals.manage-units permission', function () {
    $organization = Organization::factory()->create();
    $professional = Professional::factory()->for($organization)->create();
    $user = User::factory()->create();
    $permission = Permission::query()->create([
        'key' => PermissionKey::ProfessionalsManageUnits->value,
        'group' => PermissionKey::ProfessionalsManageUnits->group(),
        'label' => PermissionKey::ProfessionalsManageUnits->label(),
    ]);
    $role = Role::factory()->for($organization)->create();
    $role->permissions()->attach($permission);
    OrganizationMembership::factory()->for($organization)->for($user)->create([
        'status' => OrganizationMembershipStatus::Active,
        'role_id' => $role->id,
    ]);
    session(['active_organization_id' => $organization->id]);
    $unit = unitFor($organization);

    $this->actingAs($user)->post("/settings/professionals/{$professional->id}/units", [
        'unit_id' => $unit->id,
    ])->assertRedirect();
});

it('blocks acting on a unit link that belongs to a professional from another URL scope', function () {
    [$user, $organization, $professionalA] = professionalWithUnitOwnedByOwner();
    $professionalB = Professional::factory()->for($organization)->create();
    $linkOfB = ProfessionalUnit::factory()->for($professionalB)->create(['organization_id' => $organization->id]);

    $this->actingAs($user)->patch("/settings/professionals/{$professionalA->id}/units/{$linkOfB->id}/deactivate")
        ->assertNotFound();
});

it('blocks access to a professional belonging to another organization', function () {
    $user = actingOwnerWithActiveContext();
    $foreignProfessional = Professional::factory()->for(Organization::factory()->create())->create();

    $this->actingAs($user)->get("/settings/professionals/{$foreignProfessional->id}/units")
        ->assertNotFound();
});
