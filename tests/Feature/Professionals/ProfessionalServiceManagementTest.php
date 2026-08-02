<?php

declare(strict_types=1);

use App\Enums\AuditAction;
use App\Enums\OrganizationMembershipStatus;
use App\Enums\PermissionKey;
use App\Enums\ProfessionalServiceUnitScope;
use App\Enums\RecordStatus;
use App\Enums\ServiceAvailabilityScope;
use App\Models\AuditLog;
use App\Models\LegalEntity;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Permission;
use App\Models\Professional;
use App\Models\ProfessionalService;
use App\Models\ProfessionalUnit;
use App\Models\Role;
use App\Models\Service;
use App\Models\Unit;
use App\Models\User;

function professionalWithServiceOwnedByOwner(): array
{
    $user = actingOwnerWithActiveContext();
    $organization = $user->organizationMemberships()->first()->organization;
    $professional = Professional::factory()->for($organization)->create();

    return [$user, $organization, $professional];
}

function serviceFor(Organization $organization, array $overrides = []): Service
{
    return Service::factory()->for($organization)->create(array_merge([
        'status' => RecordStatus::Active,
    ], $overrides));
}

function unitInOrganization(Organization $organization): Unit
{
    $legalEntity = LegalEntity::factory()->for($organization)->create();

    return Unit::factory()->for($organization)->create([
        'legal_entity_id' => $legalEntity->id,
        'status' => RecordStatus::Active,
    ]);
}

it('assigns a service to a professional with inherited values by default', function () {
    [$user, $organization, $professional] = professionalWithServiceOwnedByOwner();
    $service = serviceFor($organization, ['default_duration_minutes' => 45, 'default_price_cents' => 20000]);
    ProfessionalUnit::factory()->for($professional)->create(['organization_id' => $organization->id, 'unit_id' => unitInOrganization($organization)->id]);

    $this->actingAs($user)->post("/settings/professionals/{$professional->id}/services", [
        'service_id' => $service->id,
        'unit_scope' => 'all_compatible_units',
    ])->assertRedirect();

    $link = ProfessionalService::query()->where('professional_id', $professional->id)->where('service_id', $service->id)->firstOrFail();

    expect($link->status)->toBe(RecordStatus::Active)
        ->and($link->effectiveDurationMinutes())->toBe(45)
        ->and($link->effectivePriceCents())->toBe(20000)
        ->and($link->isDurationInherited())->toBeTrue()
        ->and(AuditLog::query()->where('action', AuditAction::Created)->whereNotNull('auditable_id')->exists())->toBeTrue();
});

it('assigns a service with custom overrides, including explicit zero distinct from inherited', function () {
    [$user, $organization, $professional] = professionalWithServiceOwnedByOwner();
    $service = serviceFor($organization, ['default_duration_minutes' => 45, 'buffer_before_minutes' => 10]);
    ProfessionalUnit::factory()->for($professional)->create(['organization_id' => $organization->id, 'unit_id' => unitInOrganization($organization)->id]);

    $this->actingAs($user)->post("/settings/professionals/{$professional->id}/services", [
        'service_id' => $service->id,
        'custom_duration_minutes' => 60,
        'custom_price' => 0,
        'custom_buffer_before_minutes' => 0,
        'unit_scope' => 'all_compatible_units',
    ])->assertRedirect();

    $link = ProfessionalService::query()->where('professional_id', $professional->id)->where('service_id', $service->id)->firstOrFail();

    expect($link->custom_duration_minutes)->toBe(60)
        ->and($link->custom_price_cents)->toBe(0)
        ->and($link->isPriceInherited())->toBeFalse()
        ->and($link->effectivePriceCents())->toBe(0)
        ->and($link->custom_buffer_before_minutes)->toBe(0)
        ->and($link->isBufferBeforeInherited())->toBeFalse();
});

it('rejects a custom duration of zero', function () {
    [$user, $organization, $professional] = professionalWithServiceOwnedByOwner();
    $service = serviceFor($organization);

    $this->actingAs($user)->post("/settings/professionals/{$professional->id}/services", [
        'service_id' => $service->id,
        'custom_duration_minutes' => 0,
        'unit_scope' => 'all_compatible_units',
    ])->assertSessionHasErrors('custom_duration_minutes');
});

it('rejects assigning an inactive or deleted service', function () {
    [$user, $organization, $professional] = professionalWithServiceOwnedByOwner();
    $inactive = serviceFor($organization, ['status' => RecordStatus::Inactive]);
    $deleted = serviceFor($organization);
    $deleted->delete();

    $this->actingAs($user)->post("/settings/professionals/{$professional->id}/services", [
        'service_id' => $inactive->id,
        'unit_scope' => 'all_compatible_units',
    ])->assertSessionHasErrors('service_id');

    $this->actingAs($user)->post("/settings/professionals/{$professional->id}/services", [
        'service_id' => $deleted->id,
        'unit_scope' => 'all_compatible_units',
    ])->assertSessionHasErrors('service_id');
});

it('rejects a service belonging to another clinic', function () {
    [$user, , $professional] = professionalWithServiceOwnedByOwner();
    $foreignService = serviceFor(Organization::factory()->create());

    $this->actingAs($user)->post("/settings/professionals/{$professional->id}/services", [
        'service_id' => $foreignService->id,
        'unit_scope' => 'all_compatible_units',
    ])->assertSessionHasErrors('service_id');
});

it('rejects a duplicate active link', function () {
    [$user, $organization, $professional] = professionalWithServiceOwnedByOwner();
    $service = serviceFor($organization);
    ProfessionalService::factory()->for($professional)->create(['organization_id' => $organization->id, 'service_id' => $service->id]);

    $this->actingAs($user)->post("/settings/professionals/{$professional->id}/services", [
        'service_id' => $service->id,
        'unit_scope' => 'all_compatible_units',
    ])->assertSessionHasErrors('service_id');
});

it('computes compatible units as the intersection between the professional active units and the service available units', function () {
    [, $organization, $professional] = professionalWithServiceOwnedByOwner();
    $unitA = unitInOrganization($organization);
    $unitB = unitInOrganization($organization);
    ProfessionalUnit::factory()->for($professional)->create(['organization_id' => $organization->id, 'unit_id' => $unitA->id]);
    ProfessionalUnit::factory()->for($professional)->create(['organization_id' => $organization->id, 'unit_id' => $unitB->id]);

    $service = serviceFor($organization, ['unit_availability_scope' => ServiceAvailabilityScope::SelectedUnits]);
    $service->unitLinks()->create(['organization_id' => $organization->id, 'unit_id' => $unitA->id]);

    $link = ProfessionalService::factory()->for($professional)->create(['organization_id' => $organization->id, 'service_id' => $service->id]);

    expect($link->compatibleUnitIds()->all())->toBe([$unitA->id]);
});

it('further restricts compatible units to the explicit selection when unit_scope is selected_units', function () {
    [, $organization, $professional] = professionalWithServiceOwnedByOwner();
    $unitA = unitInOrganization($organization);
    $unitB = unitInOrganization($organization);
    ProfessionalUnit::factory()->for($professional)->create(['organization_id' => $organization->id, 'unit_id' => $unitA->id]);
    ProfessionalUnit::factory()->for($professional)->create(['organization_id' => $organization->id, 'unit_id' => $unitB->id]);

    $service = serviceFor($organization, ['unit_availability_scope' => ServiceAvailabilityScope::AllUnits]);
    $link = ProfessionalService::factory()->for($professional)->create([
        'organization_id' => $organization->id,
        'service_id' => $service->id,
        'unit_scope' => ProfessionalServiceUnitScope::SelectedUnits,
    ]);
    $link->unitLinks()->create(['organization_id' => $organization->id, 'unit_id' => $unitA->id]);

    expect($link->compatibleUnitIds()->all())->toBe([$unitA->id]);
});

it('always resolves an empty compatible unit list when unit_scope is none', function () {
    [, $organization, $professional] = professionalWithServiceOwnedByOwner();
    ProfessionalUnit::factory()->for($professional)->create(['organization_id' => $organization->id, 'unit_id' => unitInOrganization($organization)->id]);
    $service = serviceFor($organization);

    $link = ProfessionalService::factory()->for($professional)->create([
        'organization_id' => $organization->id,
        'service_id' => $service->id,
        'unit_scope' => ProfessionalServiceUnitScope::None,
    ]);

    expect($link->compatibleUnitIds()->isEmpty())->toBeTrue();
});

it('creates the link as inactive with a distinct message when there is no compatible unit', function () {
    [$user, $organization, $professional] = professionalWithServiceOwnedByOwner();
    $service = serviceFor($organization);
    // Sem nenhum vínculo de unidade para o profissional: interseção vazia.

    $this->actingAs($user)->post("/settings/professionals/{$professional->id}/services", [
        'service_id' => $service->id,
        'unit_scope' => 'all_compatible_units',
    ])->assertRedirect();

    $link = ProfessionalService::query()->where('professional_id', $professional->id)->where('service_id', $service->id)->firstOrFail();
    expect($link->status)->toBe(RecordStatus::Inactive);
});

it('blocks activating a link when there is no compatible unit', function () {
    [$user, $organization, $professional] = professionalWithServiceOwnedByOwner();
    $service = serviceFor($organization);
    $link = ProfessionalService::factory()->inactive()->for($professional)->create(['organization_id' => $organization->id, 'service_id' => $service->id]);

    $this->actingAs($user)->patch("/settings/professionals/{$professional->id}/services/{$link->id}/activate")
        ->assertSessionHasErrors('service');

    expect($link->fresh()->status)->toBe(RecordStatus::Inactive);
});

it('allows activating a link once a compatible unit exists', function () {
    [$user, $organization, $professional] = professionalWithServiceOwnedByOwner();
    ProfessionalUnit::factory()->for($professional)->create(['organization_id' => $organization->id, 'unit_id' => unitInOrganization($organization)->id]);
    $service = serviceFor($organization);
    $link = ProfessionalService::factory()->inactive()->for($professional)->create(['organization_id' => $organization->id, 'service_id' => $service->id]);

    $this->actingAs($user)->patch("/settings/professionals/{$professional->id}/services/{$link->id}/activate")
        ->assertRedirect();

    expect($link->fresh()->status)->toBe(RecordStatus::Active);
});

it('updates the custom values and unit scope of a link', function () {
    [$user, $organization, $professional] = professionalWithServiceOwnedByOwner();
    $unit = unitInOrganization($organization);
    ProfessionalUnit::factory()->for($professional)->create(['organization_id' => $organization->id, 'unit_id' => $unit->id]);
    $service = serviceFor($organization, ['default_duration_minutes' => 30]);
    $link = ProfessionalService::factory()->for($professional)->create(['organization_id' => $organization->id, 'service_id' => $service->id]);

    $this->actingAs($user)->put("/settings/professionals/{$professional->id}/services/{$link->id}", [
        'custom_duration_minutes' => 50,
        'unit_scope' => 'selected_units',
        'unit_ids' => [$unit->id],
    ])->assertRedirect();

    $fresh = $link->fresh();
    expect($fresh->custom_duration_minutes)->toBe(50)
        ->and($fresh->unit_scope)->toBe(ProfessionalServiceUnitScope::SelectedUnits)
        ->and($fresh->unitLinks()->pluck('unit_id')->all())->toBe([$unit->id]);
});

it('reflects a change to the service default in the inherited effective value, while a custom override stays stable', function () {
    [, $organization, $professionalA] = professionalWithServiceOwnedByOwner();
    $professionalB = Professional::factory()->for($organization)->create();
    $service = serviceFor($organization, ['default_duration_minutes' => 30]);
    $inheritedLink = ProfessionalService::factory()->for($professionalA)->create(['organization_id' => $organization->id, 'service_id' => $service->id]);
    $customLink = ProfessionalService::factory()->for($professionalB)->create(['organization_id' => $organization->id, 'service_id' => $service->id, 'custom_duration_minutes' => 90]);

    $service->update(['default_duration_minutes' => 40]);

    expect($inheritedLink->fresh()->effectiveDurationMinutes())->toBe(40)
        ->and($customLink->fresh()->effectiveDurationMinutes())->toBe(90);
});

it('requires at least one unit when unit_scope is selected_units', function () {
    [$user, $organization, $professional] = professionalWithServiceOwnedByOwner();
    $service = serviceFor($organization);

    $this->actingAs($user)->post("/settings/professionals/{$professional->id}/services", [
        'service_id' => $service->id,
        'unit_scope' => 'selected_units',
    ])->assertSessionHasErrors('unit_ids');
});

it('deactivates and reactivates a link without any primary concept involved', function () {
    [$user, $organization, $professional] = professionalWithServiceOwnedByOwner();
    ProfessionalUnit::factory()->for($professional)->create(['organization_id' => $organization->id, 'unit_id' => unitInOrganization($organization)->id]);
    $service = serviceFor($organization);
    $link = ProfessionalService::factory()->for($professional)->create(['organization_id' => $organization->id, 'service_id' => $service->id]);

    $this->actingAs($user)->patch("/settings/professionals/{$professional->id}/services/{$link->id}/deactivate")
        ->assertRedirect();
    expect($link->fresh()->status)->toBe(RecordStatus::Inactive);

    $this->actingAs($user)->patch("/settings/professionals/{$professional->id}/services/{$link->id}/activate")
        ->assertRedirect();
    expect($link->fresh()->status)->toBe(RecordStatus::Active);
});

it('logically removes a service link, its unit selections, and preserves history', function () {
    [$user, $organization, $professional] = professionalWithServiceOwnedByOwner();
    $unit = unitInOrganization($organization);
    $service = serviceFor($organization);
    $link = ProfessionalService::factory()->for($professional)->create([
        'organization_id' => $organization->id,
        'service_id' => $service->id,
        'unit_scope' => ProfessionalServiceUnitScope::SelectedUnits,
    ]);
    $unitLink = $link->unitLinks()->create(['organization_id' => $organization->id, 'unit_id' => $unit->id]);

    $this->actingAs($user)->delete("/settings/professionals/{$professional->id}/services/{$link->id}")
        ->assertRedirect();

    expect($link->fresh()->trashed())->toBeTrue()
        ->and($unitLink->fresh()->trashed())->toBeTrue()
        ->and(Service::query()->find($link->service_id))->not->toBeNull()
        ->and(Professional::query()->find($professional->id))->not->toBeNull();
});

it('restores a service link always inactive, without unit selections, and detects an active duplicate conflict', function () {
    [$user, $organization, $professional] = professionalWithServiceOwnedByOwner();
    $service = serviceFor($organization);
    $link = ProfessionalService::factory()->for($professional)->create(['organization_id' => $organization->id, 'service_id' => $service->id]);
    $link->delete();

    $this->actingAs($user)->post("/settings/professionals/{$professional->id}/services/{$link->id}/restore")
        ->assertRedirect();
    expect($link->fresh()->trashed())->toBeFalse()
        ->and($link->fresh()->status)->toBe(RecordStatus::Inactive)
        ->and($link->fresh()->unit_scope)->toBe(ProfessionalServiceUnitScope::None);

    $link->delete();
    ProfessionalService::factory()->for($professional)->create(['organization_id' => $organization->id, 'service_id' => $service->id]);

    $this->actingAs($user)->post("/settings/professionals/{$professional->id}/services/{$link->id}/restore")
        ->assertSessionHasErrors('service');
});

it('blocks a member without the manage-services permission', function () {
    $organization = Organization::factory()->create();
    $professional = Professional::factory()->for($organization)->create();
    $user = User::factory()->create();
    OrganizationMembership::factory()->for($organization)->for($user)->create(['status' => OrganizationMembershipStatus::Active]);
    session(['active_organization_id' => $organization->id]);
    $service = serviceFor($organization);

    $this->actingAs($user)->post("/settings/professionals/{$professional->id}/services", [
        'service_id' => $service->id,
        'unit_scope' => 'all_compatible_units',
    ])->assertForbidden();
});

it('allows a member with the professionals.manage-services permission', function () {
    $organization = Organization::factory()->create();
    $professional = Professional::factory()->for($organization)->create();
    $user = User::factory()->create();
    $permission = Permission::query()->create([
        'key' => PermissionKey::ProfessionalsManageServices->value,
        'group' => PermissionKey::ProfessionalsManageServices->group(),
        'label' => PermissionKey::ProfessionalsManageServices->label(),
    ]);
    $role = Role::factory()->for($organization)->create();
    $role->permissions()->attach($permission);
    OrganizationMembership::factory()->for($organization)->for($user)->create([
        'status' => OrganizationMembershipStatus::Active,
        'role_id' => $role->id,
    ]);
    session(['active_organization_id' => $organization->id]);
    $service = serviceFor($organization);

    $this->actingAs($user)->post("/settings/professionals/{$professional->id}/services", [
        'service_id' => $service->id,
        'unit_scope' => 'all_compatible_units',
    ])->assertRedirect();
});

it('blocks acting on a service link that belongs to a professional from another URL scope', function () {
    [$user, $organization, $professionalA] = professionalWithServiceOwnedByOwner();
    $professionalB = Professional::factory()->for($organization)->create();
    $linkOfB = ProfessionalService::factory()->for($professionalB)->create(['organization_id' => $organization->id]);

    $this->actingAs($user)->patch("/settings/professionals/{$professionalA->id}/services/{$linkOfB->id}/deactivate")
        ->assertNotFound();
});

it('blocks access to a professional belonging to another organization', function () {
    $user = actingOwnerWithActiveContext();
    $foreignProfessional = Professional::factory()->for(Organization::factory()->create())->create();

    $this->actingAs($user)->get("/settings/professionals/{$foreignProfessional->id}/services")
        ->assertNotFound();
});
