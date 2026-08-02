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
use App\Models\ProfessionalRegistration;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\QueryException;

it('creates a professional registration', function () {
    $user = actingOwnerWithActiveContext();
    $organization = $user->organizationMemberships()->first()->organization;
    $professional = Professional::factory()->for($organization)->create();

    $this->actingAs($user)->post("/settings/professionals/{$professional->id}/registrations", [
        'council' => 'CRM',
        'registration_number' => '123456',
        'state' => 'sp',
    ])->assertRedirect();

    $registration = ProfessionalRegistration::query()->where('professional_id', $professional->id)->firstOrFail();
    expect($registration->council)->toBe('CRM')
        ->and($registration->state->value)->toBe('SP')
        ->and(AuditLog::query()->where('auditable_id', $registration->id)->where('action', AuditAction::Created)->exists())->toBeTrue();
});

it('allows a national council registration without a state', function () {
    $user = actingOwnerWithActiveContext();
    $organization = $user->organizationMemberships()->first()->organization;
    $professional = Professional::factory()->for($organization)->create();

    $this->actingAs($user)->post("/settings/professionals/{$professional->id}/registrations", [
        'council' => 'Conselho Nacional',
        'registration_number' => 'ABC-123',
    ])->assertRedirect();

    $registration = ProfessionalRegistration::query()->where('professional_id', $professional->id)->firstOrFail();
    expect($registration->state)->toBeNull();
});

it('rejects an invalid UF and an expiration date before the issue date', function () {
    $user = actingOwnerWithActiveContext();
    $organization = $user->organizationMemberships()->first()->organization;
    $professional = Professional::factory()->for($organization)->create();

    $this->actingAs($user)->post("/settings/professionals/{$professional->id}/registrations", [
        'council' => 'CRM', 'registration_number' => '1', 'state' => 'ZZ',
    ])->assertSessionHasErrors('state');

    $this->actingAs($user)->post("/settings/professionals/{$professional->id}/registrations", [
        'council' => 'CRM', 'registration_number' => '2', 'state' => 'SP',
        'issued_at' => '2026-06-01', 'expires_at' => '2026-01-01',
    ])->assertSessionHasErrors('expires_at');
});

it('rejects a duplicate council/number/state within the same clinic', function () {
    $user = actingOwnerWithActiveContext();
    $organization = $user->organizationMemberships()->first()->organization;
    $professional = Professional::factory()->for($organization)->create();
    ProfessionalRegistration::factory()->for($professional)->create([
        'organization_id' => $organization->id, 'council' => 'CRM', 'registration_number' => '999', 'state' => 'SP',
    ]);

    $this->actingAs($user)->post("/settings/professionals/{$professional->id}/registrations", [
        'council' => 'CRM', 'registration_number' => '999', 'state' => 'SP',
    ])->assertSessionHasErrors('registration_number');
});

it('keeps the existing registration number when the field is left blank on update', function () {
    $user = actingOwnerWithActiveContext();
    $organization = $user->organizationMemberships()->first()->organization;
    $professional = Professional::factory()->for($organization)->create();
    $registration = ProfessionalRegistration::factory()->for($professional)->create([
        'organization_id' => $organization->id,
        'registration_number' => '445566',
    ]);

    $this->actingAs($user)->put("/settings/professionals/{$professional->id}/registrations/{$registration->id}", [
        'council' => $registration->council,
        'state' => $registration->state->value,
    ])->assertRedirect();

    expect($registration->fresh()->registration_number)->toBe('445566');
});

it('computes the validity status: valid, expiring soon, expired and no expiration', function () {
    $organization = Organization::factory()->create();
    $professional = Professional::factory()->for($organization)->create();

    $valid = ProfessionalRegistration::factory()->for($professional)->create([
        'organization_id' => $organization->id, 'expires_at' => now()->addYear(),
    ]);
    $expiringSoon = ProfessionalRegistration::factory()->for($professional)->create([
        'organization_id' => $organization->id, 'registration_number' => 'X2', 'expires_at' => now()->addDays(10),
    ]);
    $expired = ProfessionalRegistration::factory()->for($professional)->create([
        'organization_id' => $organization->id, 'registration_number' => 'X3', 'expires_at' => now()->subDay(),
    ]);
    $noExpiration = ProfessionalRegistration::factory()->for($professional)->create([
        'organization_id' => $organization->id, 'registration_number' => 'X4', 'expires_at' => null,
    ]);

    expect($valid->validityStatus()->value)->toBe('valid')
        ->and($expiringSoon->validityStatus()->value)->toBe('expiring_soon')
        ->and($expired->validityStatus()->value)->toBe('expired')
        ->and($noExpiration->validityStatus()->value)->toBe('no_expiration');
});

it('sets and swaps the primary registration transactionally', function () {
    $user = actingOwnerWithActiveContext();
    $organization = $user->organizationMemberships()->first()->organization;
    $professional = Professional::factory()->for($organization)->create();
    $regA = ProfessionalRegistration::factory()->primary()->for($professional)->create(['organization_id' => $organization->id]);
    $regB = ProfessionalRegistration::factory()->for($professional)->create(['organization_id' => $organization->id, 'registration_number' => 'B1']);

    $this->actingAs($user)->patch("/settings/professionals/{$professional->id}/registrations/{$regB->id}/primary")
        ->assertRedirect();

    expect($regA->fresh()->is_primary)->toBeFalse()
        ->and($regB->fresh()->is_primary)->toBeTrue();
});

it('never allows two active primary registrations for the same professional', function () {
    $organization = Organization::factory()->create();
    $professional = Professional::factory()->for($organization)->create();
    ProfessionalRegistration::factory()->primary()->for($professional)->create(['organization_id' => $organization->id]);

    expect(fn () => ProfessionalRegistration::factory()->primary()->for($professional)->create(['organization_id' => $organization->id, 'registration_number' => 'B2']))
        ->toThrow(QueryException::class);
});

it('blocks deleting the primary registration when other active registrations exist', function () {
    $user = actingOwnerWithActiveContext();
    $organization = $user->organizationMemberships()->first()->organization;
    $professional = Professional::factory()->for($organization)->create();
    $primary = ProfessionalRegistration::factory()->primary()->for($professional)->create(['organization_id' => $organization->id]);
    ProfessionalRegistration::factory()->for($professional)->create(['organization_id' => $organization->id, 'registration_number' => 'B3']);

    $this->actingAs($user)->delete("/settings/professionals/{$professional->id}/registrations/{$primary->id}")
        ->assertSessionHasErrors('registration');

    expect($primary->fresh()->trashed())->toBeFalse();
});

it('logically deletes and restores a registration, detecting conflicts', function () {
    $user = actingOwnerWithActiveContext();
    $organization = $user->organizationMemberships()->first()->organization;
    $professional = Professional::factory()->for($organization)->create();
    $registration = ProfessionalRegistration::factory()->for($professional)->create(['organization_id' => $organization->id, 'council' => 'CRM', 'registration_number' => '777', 'state' => 'SP']);

    $this->actingAs($user)->delete("/settings/professionals/{$professional->id}/registrations/{$registration->id}")->assertRedirect();
    expect($registration->fresh()->trashed())->toBeTrue();

    $this->actingAs($user)->post("/settings/professionals/{$professional->id}/registrations/{$registration->id}/restore")->assertRedirect();
    expect($registration->fresh()->trashed())->toBeFalse()
        ->and($registration->fresh()->status)->toBe(RecordStatus::Inactive);

    $registration->delete();
    ProfessionalRegistration::factory()->for($professional)->create(['organization_id' => $organization->id, 'council' => 'CRM', 'registration_number' => '777', 'state' => 'SP']);

    $this->actingAs($user)->post("/settings/professionals/{$professional->id}/registrations/{$registration->id}/restore")
        ->assertSessionHasErrors('registration');
});

it('never sends the full registration number in the specialties page props', function () {
    $user = actingOwnerWithActiveContext();
    $organization = $user->organizationMemberships()->first()->organization;
    $professional = Professional::factory()->for($organization)->create();
    ProfessionalRegistration::factory()->for($professional)->create(['organization_id' => $organization->id, 'registration_number' => '9988776655']);

    $response = $this->actingAs($user)->get("/settings/professionals/{$professional->id}/specialties");
    $response->assertInertia(fn ($page) => $page
        ->where('registrations.0.masked_registration_number', fn ($value) => ! str_contains((string) $value, '9988776655')));

    expect($response->viewData('page')['props']['registrations'][0])->not->toHaveKey('registration_number');
});

it('requires the view-sensitive permission to reveal the full registration number', function () {
    $user = actingOwnerWithActiveContext();
    $organization = $user->organizationMemberships()->first()->organization;
    $professional = Professional::factory()->for($organization)->create();
    $registration = ProfessionalRegistration::factory()->for($professional)->create(['organization_id' => $organization->id, 'registration_number' => '5544332211']);

    $response = $this->actingAs($user)->get("/settings/professionals/{$professional->id}/registrations/{$registration->id}/reveal");
    $response->assertOk()->assertJson(['registration_number' => '5544332211']);
});

it('never stores the full registration number in the audit log', function () {
    $user = actingOwnerWithActiveContext();
    $organization = $user->organizationMemberships()->first()->organization;
    $professional = Professional::factory()->for($organization)->create();

    $this->actingAs($user)->post("/settings/professionals/{$professional->id}/registrations", [
        'council' => 'CRM', 'registration_number' => '1122334455', 'state' => 'SP',
    ])->assertRedirect();

    $registration = ProfessionalRegistration::query()->where('registration_number', '1122334455')->firstOrFail();
    $log = AuditLog::query()->where('auditable_id', $registration->id)->where('action', AuditAction::Created)->firstOrFail();

    expect($log->after_data['registration_number'])->not->toBe('1122334455')
        ->and($log->after_data['registration_number'])->toEndWith(substr('1122334455', -2));
});

it('blocks a member without the registrations.manage permission', function () {
    $organization = Organization::factory()->create();
    $professional = Professional::factory()->for($organization)->create();
    $user = User::factory()->create();
    OrganizationMembership::factory()->for($organization)->for($user)->create(['status' => OrganizationMembershipStatus::Active]);
    session(['active_organization_id' => $organization->id]);

    $this->actingAs($user)->post("/settings/professionals/{$professional->id}/registrations", [
        'council' => 'CRM', 'registration_number' => '1',
    ])->assertForbidden();
});

it('allows a member with the professional-registrations.manage permission', function () {
    $organization = Organization::factory()->create();
    $professional = Professional::factory()->for($organization)->create();
    $user = User::factory()->create();
    $permission = Permission::query()->create([
        'key' => PermissionKey::ProfessionalRegistrationsManage->value,
        'group' => PermissionKey::ProfessionalRegistrationsManage->group(),
        'label' => PermissionKey::ProfessionalRegistrationsManage->label(),
    ]);
    $role = Role::factory()->for($organization)->create();
    $role->permissions()->attach($permission);
    OrganizationMembership::factory()->for($organization)->for($user)->create([
        'status' => OrganizationMembershipStatus::Active,
        'role_id' => $role->id,
    ]);
    session(['active_organization_id' => $organization->id]);

    $this->actingAs($user)->post("/settings/professionals/{$professional->id}/registrations", [
        'council' => 'CRM', 'registration_number' => '1',
    ])->assertRedirect();
});

it('blocks acting on a registration that belongs to a different professional', function () {
    $user = actingOwnerWithActiveContext();
    $organization = $user->organizationMemberships()->first()->organization;
    $professionalA = Professional::factory()->for($organization)->create();
    $professionalB = Professional::factory()->for($organization)->create();
    $registrationOfB = ProfessionalRegistration::factory()->for($professionalB)->create(['organization_id' => $organization->id]);

    $this->actingAs($user)->delete("/settings/professionals/{$professionalA->id}/registrations/{$registrationOfB->id}")
        ->assertNotFound();
});

it('blocks cross-tenant access to a registration via a tampered professional id', function () {
    $user = actingOwnerWithActiveContext();
    $foreignProfessional = Professional::factory()->for(Organization::factory()->create())->create();

    $this->actingAs($user)->post("/settings/professionals/{$foreignProfessional->id}/registrations", [
        'council' => 'CRM', 'registration_number' => '1',
    ])->assertNotFound();
});
