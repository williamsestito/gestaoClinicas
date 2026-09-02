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
use App\Models\Service;
use App\Models\ServiceSpecialty;
use App\Models\Specialty;
use App\Models\User;

it('shows an empty listing for a brand new clinic', function () {
    $user = actingOwnerWithActiveContext();

    $this->actingAs($user)
        ->get('/settings/specialties')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/specialties/Index')
            ->where('specialties', []));
});

it('creates a specialty for the active organization', function () {
    $user = actingOwnerWithActiveContext();

    $this->actingAs($user)->post('/settings/specialties', [
        'name' => 'Cardiologia',
        'code' => 'cardio',
        'description' => 'Atendimento cardiológico',
        'display_order' => 1,
    ])->assertRedirect('/settings/specialties');

    $specialty = Specialty::query()->where('name', 'Cardiologia')->firstOrFail();
    expect($specialty->code)->toBe('CARDIO')
        ->and($specialty->status)->toBe(RecordStatus::Active)
        ->and(AuditLog::query()->where('auditable_id', $specialty->id)->where('action', AuditAction::Created)->exists())->toBeTrue();
});

it('normalizes extra whitespace in the name and rejects a whitespace-only name', function () {
    $user = actingOwnerWithActiveContext();

    $this->actingAs($user)->post('/settings/specialties', [
        'name' => '  Fisioterapia   Esportiva  ',
    ])->assertRedirect('/settings/specialties');

    expect(Specialty::query()->where('name', 'Fisioterapia Esportiva')->exists())->toBeTrue();

    $this->actingAs($user)->post('/settings/specialties', [
        'name' => '   ',
    ])->assertSessionHasErrors('name');
});

it('rejects a duplicate name within the same clinic but allows it in another clinic', function () {
    $user = actingOwnerWithActiveContext();
    Specialty::factory()->for($user->organizationMemberships()->first()->organization)->create(['name' => 'Cardiologia']);

    $this->actingAs($user)->post('/settings/specialties', [
        'name' => 'Cardiologia',
    ])->assertSessionHasErrors('name');

    $otherOrganization = Organization::factory()->create();
    Specialty::factory()->for($otherOrganization)->create(['name' => 'Cardiologia']);
    expect(Specialty::query()->where('name', 'Cardiologia')->count())->toBe(2);
});

it('rejects a code with invalid characters', function () {
    $user = actingOwnerWithActiveContext();

    $this->actingAs($user)->post('/settings/specialties', [
        'name' => 'Nutrologia',
        'code' => 'inválido#',
    ])->assertSessionHasErrors('code');
});

it('updates a specialty', function () {
    $user = actingOwnerWithActiveContext();
    $specialty = Specialty::factory()->for($user->organizationMemberships()->first()->organization)->create();

    $this->actingAs($user)->put("/settings/specialties/{$specialty->id}", [
        'name' => 'Novo Nome',
        'code' => $specialty->code,
        'display_order' => 5,
    ])->assertRedirect('/settings/specialties');

    expect($specialty->fresh()->name)->toBe('Novo Nome')
        ->and($specialty->fresh()->display_order)->toBe(5);
});

it('activates and deactivates a specialty', function () {
    $user = actingOwnerWithActiveContext();
    $specialty = Specialty::factory()->for($user->organizationMemberships()->first()->organization)->create(['status' => RecordStatus::Active]);

    $this->actingAs($user)->patch("/settings/specialties/{$specialty->id}/deactivate")
        ->assertRedirect();
    expect($specialty->fresh()->status)->toBe(RecordStatus::Inactive);

    $this->actingAs($user)->patch("/settings/specialties/{$specialty->id}/activate")
        ->assertRedirect();
    expect($specialty->fresh()->status)->toBe(RecordStatus::Active);
});

it('deactivating a specialty preserves existing professional and service links', function () {
    $user = actingOwnerWithActiveContext();
    $organization = $user->organizationMemberships()->first()->organization;
    $specialty = Specialty::factory()->for($organization)->create();
    $professional = Professional::factory()->for($organization)->create();
    $link = ProfessionalSpecialty::factory()->for($professional)->create([
        'organization_id' => $organization->id,
        'specialty_id' => $specialty->id,
    ]);

    $this->actingAs($user)->patch("/settings/specialties/{$specialty->id}/deactivate")->assertRedirect();

    expect(ProfessionalSpecialty::query()->find($link->id))->not->toBeNull();
});

it('blocks deleting a specialty linked to a professional or service', function () {
    $user = actingOwnerWithActiveContext();
    $organization = $user->organizationMemberships()->first()->organization;
    $specialty = Specialty::factory()->for($organization)->create();
    $service = Service::factory()->for($organization)->create();
    ServiceSpecialty::factory()->for($service)->create([
        'organization_id' => $organization->id,
        'specialty_id' => $specialty->id,
    ]);

    $this->actingAs($user)->delete("/settings/specialties/{$specialty->id}")
        ->assertSessionHasErrors('specialty');

    expect($specialty->fresh()->trashed())->toBeFalse();
});

it('logically deletes an unlinked specialty and preserves its history', function () {
    $user = actingOwnerWithActiveContext();
    $specialty = Specialty::factory()->for($user->organizationMemberships()->first()->organization)->create();

    $this->actingAs($user)->delete("/settings/specialties/{$specialty->id}")
        ->assertRedirect();

    expect($specialty->fresh()->trashed())->toBeTrue()
        ->and(Specialty::query()->find($specialty->id))->toBeNull()
        ->and(Specialty::withTrashed()->find($specialty->id))->not->toBeNull();
});

it('restores a deleted specialty as inactive', function () {
    $user = actingOwnerWithActiveContext();
    $specialty = Specialty::factory()->for($user->organizationMemberships()->first()->organization)->create(['status' => RecordStatus::Active]);
    $specialty->delete();

    $this->actingAs($user)->post("/settings/specialties/{$specialty->id}/restore")
        ->assertRedirect();

    expect($specialty->fresh()->trashed())->toBeFalse()
        ->and($specialty->fresh()->status)->toBe(RecordStatus::Inactive);
});

it('blocks restoring a specialty when a newer active record has the same name', function () {
    $user = actingOwnerWithActiveContext();
    $organization = $user->organizationMemberships()->first()->organization;
    $specialty = Specialty::factory()->for($organization)->create(['name' => 'Cardiologia']);
    $specialty->delete();

    Specialty::factory()->for($organization)->create(['name' => 'Cardiologia']);

    $this->actingAs($user)->post("/settings/specialties/{$specialty->id}/restore")
        ->assertSessionHasErrors('specialty');

    expect($specialty->fresh()->trashed())->toBeTrue();
});

it('blocks a member without the manage permission from creating a specialty', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->create();
    OrganizationMembership::factory()->for($organization)->for($user)->create(['status' => OrganizationMembershipStatus::Active]);
    session(['active_organization_id' => $organization->id]);

    $this->actingAs($user)->post('/settings/specialties', ['name' => 'Cardiologia'])
        ->assertForbidden();
});

it('allows a member with the specialties.manage permission to create a specialty', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->create();
    $permission = Permission::query()->create([
        'key' => PermissionKey::SpecialtiesManage->value,
        'group' => PermissionKey::SpecialtiesManage->group(),
        'label' => PermissionKey::SpecialtiesManage->label(),
    ]);
    $role = Role::factory()->for($organization)->create();
    $role->permissions()->attach($permission);
    OrganizationMembership::factory()->for($organization)->for($user)->create([
        'status' => OrganizationMembershipStatus::Active,
        'role_id' => $role->id,
    ]);
    session(['active_organization_id' => $organization->id]);

    $this->actingAs($user)->post('/settings/specialties', ['name' => 'Cardiologia'])
        ->assertRedirect('/settings/specialties');
});

it('blocks access to a specialty belonging to another organization even with a valid id', function () {
    $user = actingOwnerWithActiveContext();
    $otherOrganization = Organization::factory()->create();
    $foreignSpecialty = Specialty::factory()->for($otherOrganization)->create();

    $this->actingAs($user)->get("/settings/specialties/{$foreignSpecialty->id}/edit")
        ->assertNotFound();

    $this->actingAs($user)->put("/settings/specialties/{$foreignSpecialty->id}", ['name' => 'Hackeado'])
        ->assertNotFound();
});
