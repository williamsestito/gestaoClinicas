<?php

declare(strict_types=1);

use App\Enums\AuditAction;
use App\Enums\OrganizationMembershipStatus;
use App\Enums\PermissionKey;
use App\Enums\RecordStatus;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Permission;
use App\Models\Professional;
use App\Models\ProfessionalSpecialty;
use App\Models\Role;
use App\Models\Specialty;
use App\Models\User;
use Illuminate\Database\QueryException;

function professionalOwnedByOwner(): array
{
    $user = actingOwnerWithActiveContext();
    $organization = $user->organizationMemberships()->first()->organization;
    $professional = Professional::factory()->for($organization)->create();

    return [$user, $organization, $professional];
}

it('assigns a specialty to a professional', function () {
    [$user, $organization, $professional] = professionalOwnedByOwner();
    $specialty = Specialty::factory()->for($organization)->create(['status' => RecordStatus::Active]);

    $this->actingAs($user)->post("/settings/professionals/{$professional->id}/specialties", [
        'specialty_id' => $specialty->id,
    ])->assertRedirect();

    expect(ProfessionalSpecialty::query()->where('professional_id', $professional->id)->where('specialty_id', $specialty->id)->exists())->toBeTrue()
        ->and(AuditLog::query()->where('action', AuditAction::Created)->whereNotNull('auditable_id')->exists())->toBeTrue();
});

it('allows several specialties for the same professional', function () {
    [$user, $organization, $professional] = professionalOwnedByOwner();
    $specialties = Specialty::factory()->count(3)->for($organization)->create(['status' => RecordStatus::Active]);

    foreach ($specialties as $specialty) {
        $this->actingAs($user)->post("/settings/professionals/{$professional->id}/specialties", [
            'specialty_id' => $specialty->id,
        ])->assertRedirect();
    }

    expect($professional->specialtyLinks()->count())->toBe(3);
});

it('rejects assigning an inactive or deleted specialty', function () {
    [$user, $organization, $professional] = professionalOwnedByOwner();
    $inactive = Specialty::factory()->for($organization)->create(['status' => RecordStatus::Inactive]);
    $deleted = Specialty::factory()->for($organization)->create(['status' => RecordStatus::Active]);
    $deleted->delete();

    $this->actingAs($user)->post("/settings/professionals/{$professional->id}/specialties", [
        'specialty_id' => $inactive->id,
    ])->assertSessionHasErrors('specialty_id');

    $this->actingAs($user)->post("/settings/professionals/{$professional->id}/specialties", [
        'specialty_id' => $deleted->id,
    ])->assertSessionHasErrors('specialty_id');
});

it('rejects a specialty belonging to another clinic', function () {
    [$user, , $professional] = professionalOwnedByOwner();
    $foreignSpecialty = Specialty::factory()->for(Organization::factory()->create())->create();

    $this->actingAs($user)->post("/settings/professionals/{$professional->id}/specialties", [
        'specialty_id' => $foreignSpecialty->id,
    ])->assertSessionHasErrors('specialty_id');
});

it('rejects a duplicate active link', function () {
    [$user, $organization, $professional] = professionalOwnedByOwner();
    $specialty = Specialty::factory()->for($organization)->create(['status' => RecordStatus::Active]);
    ProfessionalSpecialty::factory()->for($professional)->create(['organization_id' => $organization->id, 'specialty_id' => $specialty->id]);

    $this->actingAs($user)->post("/settings/professionals/{$professional->id}/specialties", [
        'specialty_id' => $specialty->id,
    ])->assertSessionHasErrors('specialty_id');
});

it('sets and swaps the primary specialty transactionally', function () {
    [$user, $organization, $professional] = professionalOwnedByOwner();
    $linkA = ProfessionalSpecialty::factory()->primary()->for($professional)->create(['organization_id' => $organization->id]);
    $linkB = ProfessionalSpecialty::factory()->for($professional)->create(['organization_id' => $organization->id]);

    $this->actingAs($user)->patch("/settings/professionals/{$professional->id}/specialties/{$linkB->id}/primary")
        ->assertRedirect();

    expect($linkA->fresh()->is_primary)->toBeFalse()
        ->and($linkB->fresh()->is_primary)->toBeTrue();
});

it('never allows two active primary specialties for the same professional', function () {
    [, $organization, $professional] = professionalOwnedByOwner();
    ProfessionalSpecialty::factory()->primary()->for($professional)->create(['organization_id' => $organization->id]);

    expect(fn () => ProfessionalSpecialty::factory()->primary()->for($professional)->create(['organization_id' => $organization->id]))
        ->toThrow(QueryException::class);
});

it('blocks deactivating the primary specialty when other active links exist', function () {
    [$user, $organization, $professional] = professionalOwnedByOwner();
    $primary = ProfessionalSpecialty::factory()->primary()->for($professional)->create(['organization_id' => $organization->id]);
    ProfessionalSpecialty::factory()->for($professional)->create(['organization_id' => $organization->id]);

    $this->actingAs($user)->patch("/settings/professionals/{$professional->id}/specialties/{$primary->id}/deactivate")
        ->assertSessionHasErrors('specialty');

    expect($primary->fresh()->status)->toBe(RecordStatus::Active);
});

it('allows deactivating the primary specialty when it is the only active link', function () {
    [$user, $organization, $professional] = professionalOwnedByOwner();
    $primary = ProfessionalSpecialty::factory()->primary()->for($professional)->create(['organization_id' => $organization->id]);

    $this->actingAs($user)->patch("/settings/professionals/{$professional->id}/specialties/{$primary->id}/deactivate")
        ->assertRedirect();

    expect($primary->fresh()->status)->toBe(RecordStatus::Inactive);
});

it('logically removes a specialty link and preserves history', function () {
    [$user, $organization, $professional] = professionalOwnedByOwner();
    $link = ProfessionalSpecialty::factory()->for($professional)->create(['organization_id' => $organization->id]);

    $this->actingAs($user)->delete("/settings/professionals/{$professional->id}/specialties/{$link->id}")
        ->assertRedirect();

    expect($link->fresh()->trashed())->toBeTrue()
        ->and(Specialty::query()->find($link->specialty_id))->not->toBeNull()
        ->and(Professional::query()->find($professional->id))->not->toBeNull();
});

it('restores a specialty link and detects an active duplicate conflict', function () {
    [$user, $organization, $professional] = professionalOwnedByOwner();
    $specialty = Specialty::factory()->for($organization)->create(['status' => RecordStatus::Active]);
    $link = ProfessionalSpecialty::factory()->for($professional)->create(['organization_id' => $organization->id, 'specialty_id' => $specialty->id]);
    $link->delete();

    $this->actingAs($user)->post("/settings/professionals/{$professional->id}/specialties/{$link->id}/restore")
        ->assertRedirect();
    expect($link->fresh()->trashed())->toBeFalse()
        ->and($link->fresh()->status)->toBe(RecordStatus::Inactive);

    $link->delete();
    ProfessionalSpecialty::factory()->for($professional)->create(['organization_id' => $organization->id, 'specialty_id' => $specialty->id]);

    $this->actingAs($user)->post("/settings/professionals/{$professional->id}/specialties/{$link->id}/restore")
        ->assertSessionHasErrors('specialty');
});

it('blocks a member without the manage-specialties permission', function () {
    $organization = Organization::factory()->create();
    $professional = Professional::factory()->for($organization)->create();
    $user = User::factory()->create();
    OrganizationMembership::factory()->for($organization)->for($user)->create(['status' => OrganizationMembershipStatus::Active]);
    session(['active_organization_id' => $organization->id]);
    $specialty = Specialty::factory()->for($organization)->create(['status' => RecordStatus::Active]);

    $this->actingAs($user)->post("/settings/professionals/{$professional->id}/specialties", [
        'specialty_id' => $specialty->id,
    ])->assertForbidden();
});

it('allows a member with the professionals.manage-specialties permission', function () {
    $organization = Organization::factory()->create();
    $professional = Professional::factory()->for($organization)->create();
    $user = User::factory()->create();
    $permission = Permission::query()->create([
        'key' => PermissionKey::ProfessionalsManageSpecialties->value,
        'group' => PermissionKey::ProfessionalsManageSpecialties->group(),
        'label' => PermissionKey::ProfessionalsManageSpecialties->label(),
    ]);
    $role = Role::factory()->for($organization)->create();
    $role->permissions()->attach($permission);
    OrganizationMembership::factory()->for($organization)->for($user)->create([
        'status' => OrganizationMembershipStatus::Active,
        'role_id' => $role->id,
    ]);
    session(['active_organization_id' => $organization->id]);
    $specialty = Specialty::factory()->for($organization)->create(['status' => RecordStatus::Active]);

    $this->actingAs($user)->post("/settings/professionals/{$professional->id}/specialties", [
        'specialty_id' => $specialty->id,
    ])->assertRedirect();
});

it('blocks acting on a specialty link that belongs to a professional from another URL scope', function () {
    [$user, $organization, $professionalA] = professionalOwnedByOwner();
    $professionalB = Professional::factory()->for($organization)->create();
    $linkOfB = ProfessionalSpecialty::factory()->for($professionalB)->create(['organization_id' => $organization->id]);

    $this->actingAs($user)->patch("/settings/professionals/{$professionalA->id}/specialties/{$linkOfB->id}/deactivate")
        ->assertNotFound();
});

it('blocks access to a professional belonging to another organization', function () {
    $user = actingOwnerWithActiveContext();
    $foreignProfessional = Professional::factory()->for(Organization::factory()->create())->create();

    $this->actingAs($user)->get("/settings/professionals/{$foreignProfessional->id}/specialties")
        ->assertNotFound();
});
