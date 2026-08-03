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
use App\Models\ProfessionalService;
use App\Models\Role;
use App\Models\Service;
use App\Models\ServiceSpecialty;
use App\Models\SiteService;
use App\Models\Specialty;
use App\Models\Unit;
use App\Models\User;

it('creates a service with specialties for the active organization', function () {
    $user = actingOwnerWithActiveContext();
    $organization = $user->organizationMemberships()->first()->organization;
    $specialty = Specialty::factory()->for($organization)->create(['status' => RecordStatus::Active]);

    $this->actingAs($user)->post('/settings/services', [
        'name' => 'Consulta Padrão',
        'code' => 'cons-01',
        'default_duration_minutes' => 30,
        'buffer_before_minutes' => 5,
        'buffer_after_minutes' => 5,
        'default_price' => 150.5,
        'unit_availability_scope' => 'all_units',
        'specialty_ids' => [$specialty->id],
    ])->assertRedirect('/settings/services');

    $service = Service::query()->where('code', 'CONS-01')->firstOrFail();
    expect($service->name)->toBe('Consulta Padrão')
        ->and($service->default_price_cents)->toBe(15050)
        ->and($service->specialtyLinks()->count())->toBe(1)
        ->and(AuditLog::query()->where('auditable_id', $service->id)->where('action', AuditAction::Created)->exists())->toBeTrue();
});

it('treats an empty price as null, never as zero', function () {
    $user = actingOwnerWithActiveContext();

    $this->actingAs($user)->post('/settings/services', [
        'name' => 'Avaliação',
        'code' => 'AVAL-01',
        'default_duration_minutes' => 20,
        'unit_availability_scope' => 'all_units',
    ])->assertRedirect('/settings/services');

    $service = Service::query()->where('code', 'AVAL-01')->firstOrFail();
    expect($service->default_price_cents)->toBeNull();
});

it('rejects a negative price, zero or invalid duration, and an out-of-range buffer sum', function () {
    $user = actingOwnerWithActiveContext();

    $this->actingAs($user)->post('/settings/services', [
        'name' => 'Serviço X', 'code' => 'X-01', 'default_duration_minutes' => 30, 'default_price' => -10,
    ])->assertSessionHasErrors('default_price');

    $this->actingAs($user)->post('/settings/services', [
        'name' => 'Serviço X', 'code' => 'X-02', 'default_duration_minutes' => 0,
    ])->assertSessionHasErrors('default_duration_minutes');

    $this->actingAs($user)->post('/settings/services', [
        'name' => 'Serviço X', 'code' => 'X-03', 'default_duration_minutes' => 1000,
        'buffer_before_minutes' => 800, 'unit_availability_scope' => 'all_units',
    ])->assertSessionHasErrors('default_duration_minutes');
});

it('rejects a duplicate code within the same clinic but allows it in another clinic', function () {
    $user = actingOwnerWithActiveContext();
    $organization = $user->organizationMemberships()->first()->organization;
    Service::factory()->for($organization)->create(['code' => 'CONS-01']);

    $this->actingAs($user)->post('/settings/services', [
        'name' => 'Outro', 'code' => 'CONS-01', 'default_duration_minutes' => 30, 'unit_availability_scope' => 'all_units',
    ])->assertSessionHasErrors('code');

    $otherOrganization = Organization::factory()->create();
    Service::factory()->for($otherOrganization)->create(['code' => 'CONS-01']);
    expect(Service::query()->where('code', 'CONS-01')->count())->toBe(2);
});

it('rejects a specialty id belonging to another organization', function () {
    $user = actingOwnerWithActiveContext();
    $foreignSpecialty = Specialty::factory()->for(Organization::factory()->create())->create();

    $this->actingAs($user)->post('/settings/services', [
        'name' => 'Serviço', 'code' => 'SRV-01', 'default_duration_minutes' => 30,
        'unit_availability_scope' => 'all_units', 'specialty_ids' => [$foreignSpecialty->id],
    ])->assertSessionHasErrors('specialty_ids.0');
});

it('rejects a unit id belonging to another organization when scope is selected_units', function () {
    $user = actingOwnerWithActiveContext();
    $foreignOrganization = Organization::factory()->create();
    $foreignLegalEntity = LegalEntity::factory()->for($foreignOrganization)->create();
    $foreignUnit = Unit::factory()->for($foreignOrganization)->for($foreignLegalEntity, 'legalEntity')->create();

    $this->actingAs($user)->post('/settings/services', [
        'name' => 'Serviço', 'code' => 'SRV-02', 'default_duration_minutes' => 30,
        'unit_availability_scope' => 'selected_units', 'unit_ids' => [$foreignUnit->id],
    ])->assertSessionHasErrors('unit_ids.0');
});

it('persists the service and its links atomically', function () {
    $user = actingOwnerWithActiveContext();
    $organization = $user->organizationMemberships()->first()->organization;
    $specialtyA = Specialty::factory()->for($organization)->create();
    $specialtyB = Specialty::factory()->for($organization)->create();

    $this->actingAs($user)->post('/settings/services', [
        'name' => 'Serviço Completo', 'code' => 'COMP-01', 'default_duration_minutes' => 40,
        'unit_availability_scope' => 'all_units', 'specialty_ids' => [$specialtyA->id, $specialtyB->id],
    ])->assertRedirect('/settings/services');

    $service = Service::query()->where('code', 'COMP-01')->firstOrFail();
    expect($service->specialtyLinks()->count())->toBe(2);
});

it('updates a service and its specialty links', function () {
    $user = actingOwnerWithActiveContext();
    $organization = $user->organizationMemberships()->first()->organization;
    $service = Service::factory()->for($organization)->create();
    $specialtyA = Specialty::factory()->for($organization)->create();
    $specialtyB = Specialty::factory()->for($organization)->create();
    ServiceSpecialty::factory()->for($service)->create(['organization_id' => $organization->id, 'specialty_id' => $specialtyA->id]);

    $this->actingAs($user)->put("/settings/services/{$service->id}", [
        'name' => 'Novo Nome', 'code' => $service->code, 'default_duration_minutes' => 45,
        'unit_availability_scope' => 'all_units', 'specialty_ids' => [$specialtyB->id],
    ])->assertRedirect('/settings/services');

    expect($service->fresh()->name)->toBe('Novo Nome')
        ->and($service->specialtyLinks()->pluck('specialty_id')->all())->toBe([$specialtyB->id]);
});

it('activates and deactivates a service without touching SiteService', function () {
    $user = actingOwnerWithActiveContext();
    $service = Service::factory()->for($user->organizationMemberships()->first()->organization)->create(['status' => RecordStatus::Active]);

    $this->actingAs($user)->patch("/settings/services/{$service->id}/deactivate")->assertRedirect();
    expect($service->fresh()->status)->toBe(RecordStatus::Inactive);

    $this->actingAs($user)->patch("/settings/services/{$service->id}/activate")->assertRedirect();
    expect($service->fresh()->status)->toBe(RecordStatus::Active);

    expect(SiteService::query()->count())->toBe(0);
});

it('blocks deleting a service linked to a professional', function () {
    $user = actingOwnerWithActiveContext();
    $organization = $user->organizationMemberships()->first()->organization;
    $service = Service::factory()->for($organization)->create();
    $professional = Professional::factory()->for($organization)->create();
    ProfessionalService::factory()->for($professional)->create(['organization_id' => $organization->id, 'service_id' => $service->id]);

    $this->actingAs($user)->delete("/settings/services/{$service->id}")
        ->assertSessionHasErrors('service');

    expect($service->fresh()->trashed())->toBeFalse();
});

it('logically deletes an unlinked service preserving specialty history', function () {
    $user = actingOwnerWithActiveContext();
    $organization = $user->organizationMemberships()->first()->organization;
    $service = Service::factory()->for($organization)->create();
    $specialty = Specialty::factory()->for($organization)->create();
    ServiceSpecialty::factory()->for($service)->create(['organization_id' => $organization->id, 'specialty_id' => $specialty->id]);

    $this->actingAs($user)->delete("/settings/services/{$service->id}")->assertRedirect();

    expect($service->fresh()->trashed())->toBeTrue()
        ->and(Specialty::query()->find($specialty->id))->not->toBeNull();
});

it('restores a deleted service as inactive and detects a code conflict', function () {
    $user = actingOwnerWithActiveContext();
    $organization = $user->organizationMemberships()->first()->organization;
    $service = Service::factory()->for($organization)->create(['code' => 'REST-01', 'status' => RecordStatus::Active]);
    $service->delete();

    $this->actingAs($user)->post("/settings/services/{$service->id}/restore")->assertRedirect();
    expect($service->fresh()->trashed())->toBeFalse()
        ->and($service->fresh()->status)->toBe(RecordStatus::Inactive);

    $service->delete();
    Service::factory()->for($organization)->create(['code' => 'REST-01']);

    $this->actingAs($user)->post("/settings/services/{$service->id}/restore")
        ->assertSessionHasErrors('service');
});

it('never exposes internal_notes in the listing props', function () {
    $user = actingOwnerWithActiveContext();
    Service::factory()->for($user->organizationMemberships()->first()->organization)->create([
        'internal_notes' => 'Segredo interno da clínica',
    ]);

    $response = $this->actingAs($user)->get('/settings/services');
    $response->assertInertia(fn ($page) => $page
        ->component('settings/services/Index')
        ->where('services.0.name', fn ($value) => is_string($value)));

    expect($response->viewData('page')['props']['services'][0])->not->toHaveKey('internal_notes');
});

it('exposes specialty, unit and availability filter data on the index page', function () {
    $user = actingOwnerWithActiveContext();
    $organization = $user->organizationMemberships()->first()->organization;

    $unit = Unit::factory()->for($organization)->create(['status' => RecordStatus::Active]);
    $specialty = Specialty::factory()->for($organization)->create();

    $available = Service::factory()->for($organization)->create(['unit_availability_scope' => 'all_units', 'is_public' => true]);
    ServiceSpecialty::factory()->for($organization)->for($available, 'service')->for($specialty)->create();

    $unavailable = Service::factory()->for($organization)->create(['unit_availability_scope' => 'none', 'is_public' => false]);

    $professional = Professional::factory()->for($organization)->create();
    ProfessionalService::factory()->for($organization)->for($professional)->for($available, 'service')->create(['status' => RecordStatus::Active]);

    $response = $this->actingAs($user)->get('/settings/services');

    $services = collect($response->viewData('page')['props']['services'])->keyBy('id');

    expect($services[$available->id]['specialty_ids'])->toContain($specialty->id)
        ->and($services[$available->id]['unit_ids'])->toContain($unit->id)
        ->and($services[$available->id]['has_available_unit'])->toBeTrue()
        ->and($services[$available->id]['professionals_count'])->toBe(1)
        ->and($services[$available->id]['is_public'])->toBeTrue()
        ->and($services[$unavailable->id]['has_available_unit'])->toBeFalse()
        ->and($services[$unavailable->id]['professionals_count'])->toBe(0);
});

it('blocks a member without the manage permission from creating a service', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->create();
    OrganizationMembership::factory()->for($organization)->for($user)->create(['status' => OrganizationMembershipStatus::Active]);
    session(['active_organization_id' => $organization->id]);

    $this->actingAs($user)->post('/settings/services', ['name' => 'X', 'code' => 'X-1', 'default_duration_minutes' => 10])
        ->assertForbidden();
});

it('allows a member with the services.manage permission to create a service', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->create();
    $permission = Permission::query()->create([
        'key' => PermissionKey::ServicesManage->value,
        'group' => PermissionKey::ServicesManage->group(),
        'label' => PermissionKey::ServicesManage->label(),
    ]);
    $role = Role::factory()->for($organization)->create();
    $role->permissions()->attach($permission);
    OrganizationMembership::factory()->for($organization)->for($user)->create([
        'status' => OrganizationMembershipStatus::Active,
        'role_id' => $role->id,
    ]);
    session(['active_organization_id' => $organization->id]);

    $this->actingAs($user)->post('/settings/services', [
        'name' => 'Serviço', 'code' => 'SRV-1', 'default_duration_minutes' => 20, 'unit_availability_scope' => 'all_units',
    ])->assertRedirect('/settings/services');
});

it('blocks access to a service belonging to another organization even with a valid id', function () {
    $user = actingOwnerWithActiveContext();
    $foreignService = Service::factory()->for(Organization::factory()->create())->create();

    $this->actingAs($user)->get("/settings/services/{$foreignService->id}/edit")->assertNotFound();
    $this->actingAs($user)->put("/settings/services/{$foreignService->id}", ['name' => 'Hackeado'])->assertNotFound();
});
