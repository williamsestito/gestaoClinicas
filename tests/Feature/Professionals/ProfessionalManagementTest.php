<?php

declare(strict_types=1);

use App\Enums\AuditAction;
use App\Enums\OrganizationMembershipStatus;
use App\Enums\PermissionKey;
use App\Enums\RecordStatus;
use App\Enums\Weekday;
use App\Models\AuditLog;
use App\Models\LegalEntity;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Permission;
use App\Models\Professional;
use App\Models\ProfessionalUnit;
use App\Models\ProfessionalWorkingHour;
use App\Models\Role;
use App\Models\SiteProfessional;
use App\Models\Unit;
use App\Models\User;
use Database\Factories\LegalEntityFactory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('creates a professional without a linked user', function () {
    $user = actingOwnerWithActiveContext();

    $this->actingAs($user)->post('/settings/professionals', [
        'name' => 'Dra. Ana Souza',
    ])->assertRedirect('/settings/professionals');

    $professional = Professional::query()->where('name', 'Dra. Ana Souza')->firstOrFail();
    expect($professional->display_name)->toBe('Dra. Ana Souza')
        ->and($professional->user_id)->toBeNull()
        ->and($professional->status)->toBe(RecordStatus::Active)
        ->and(AuditLog::query()->where('auditable_id', $professional->id)->where('action', AuditAction::Created)->exists())->toBeTrue();
});

it('creates a professional linked to an eligible user', function () {
    $user = actingOwnerWithActiveContext();
    $organization = $user->organizationMemberships()->first()->organization;
    $eligibleUser = User::factory()->create();
    OrganizationMembership::factory()->for($organization)->for($eligibleUser)->create(['status' => OrganizationMembershipStatus::Active]);

    $this->actingAs($user)->post('/settings/professionals', [
        'name' => 'Dr. João Lima',
        'user_id' => $eligibleUser->id,
    ])->assertRedirect('/settings/professionals');

    $professional = Professional::query()->where('name', 'Dr. João Lima')->firstOrFail();
    expect($professional->user_id)->toBe($eligibleUser->id);
});

it('rejects a user from another organization', function () {
    $user = actingOwnerWithActiveContext();
    $otherOrganization = Organization::factory()->create();
    $foreignUser = User::factory()->create();
    OrganizationMembership::factory()->for($otherOrganization)->for($foreignUser)->create(['status' => OrganizationMembershipStatus::Active]);

    $this->actingAs($user)->post('/settings/professionals', [
        'name' => 'Profissional', 'user_id' => $foreignUser->id,
    ])->assertSessionHasErrors('user_id');
});

it('rejects a user without an active membership in the clinic', function () {
    $user = actingOwnerWithActiveContext();
    $organization = $user->organizationMemberships()->first()->organization;
    $inactiveUser = User::factory()->create();
    OrganizationMembership::factory()->for($organization)->for($inactiveUser)->create(['status' => OrganizationMembershipStatus::Inactive]);

    $this->actingAs($user)->post('/settings/professionals', [
        'name' => 'Profissional', 'user_id' => $inactiveUser->id,
    ])->assertSessionHasErrors('user_id');
});

it('rejects a user already linked to another professional in the same clinic', function () {
    $user = actingOwnerWithActiveContext();
    $organization = $user->organizationMemberships()->first()->organization;
    $eligibleUser = User::factory()->create();
    OrganizationMembership::factory()->for($organization)->for($eligibleUser)->create(['status' => OrganizationMembershipStatus::Active]);
    Professional::factory()->for($organization)->linkedToUser()->create(['user_id' => $eligibleUser->id]);

    $this->actingAs($user)->post('/settings/professionals', [
        'name' => 'Outro Profissional', 'user_id' => $eligibleUser->id,
    ])->assertSessionHasErrors('user_id');
});

it('linking a professional to a user never grants membership or role', function () {
    $user = actingOwnerWithActiveContext();
    $organization = $user->organizationMemberships()->first()->organization;
    $eligibleUser = User::factory()->create();
    $membership = OrganizationMembership::factory()->for($organization)->for($eligibleUser)->create(['status' => OrganizationMembershipStatus::Active]);

    $professional = Professional::factory()->for($organization)->create();

    $this->actingAs($user)->put("/settings/professionals/{$professional->id}/user", [
        'user_id' => $eligibleUser->id,
    ])->assertRedirect();

    expect($professional->fresh()->user_id)->toBe($eligibleUser->id)
        ->and($membership->fresh()->role_id)->toBeNull()
        ->and($eligibleUser->organizationMemberships()->count())->toBe(1);
});

it('unlinking a professional from a user never deletes or deactivates the user', function () {
    $user = actingOwnerWithActiveContext();
    $organization = $user->organizationMemberships()->first()->organization;
    $eligibleUser = User::factory()->create(['is_active' => true]);
    OrganizationMembership::factory()->for($organization)->for($eligibleUser)->create(['status' => OrganizationMembershipStatus::Active]);
    $professional = Professional::factory()->for($organization)->create(['user_id' => $eligibleUser->id]);

    $this->actingAs($user)->delete("/settings/professionals/{$professional->id}/user")
        ->assertRedirect();

    expect($professional->fresh()->user_id)->toBeNull()
        ->and(User::query()->find($eligibleUser->id))->not->toBeNull()
        ->and($eligibleUser->fresh()->is_active)->toBeTrue();
});

it('normalizes a masked CPF before validating and storing it', function () {
    $user = actingOwnerWithActiveContext();
    $cpf = LegalEntityFactory::validCpf();
    $masked = substr($cpf, 0, 3).'.'.substr($cpf, 3, 3).'.'.substr($cpf, 6, 3).'-'.substr($cpf, 9, 2);

    $this->actingAs($user)->post('/settings/professionals', [
        'name' => 'Profissional Documentado', 'document' => $masked,
    ])->assertRedirect('/settings/professionals');

    $professional = Professional::query()->where('name', 'Profissional Documentado')->firstOrFail();
    expect($professional->document)->toBe($cpf);
});

it('never sends the unmasked document in the index or edit inertia props', function () {
    $user = actingOwnerWithActiveContext();
    $organization = $user->organizationMemberships()->first()->organization;
    $document = LegalEntityFactory::validCpf();
    $professional = Professional::factory()->for($organization)->create(['document' => $document]);

    $indexResponse = $this->actingAs($user)->get('/settings/professionals');
    $indexResponse->assertInertia(fn ($page) => $page
        ->where('professionals.0.document', fn ($value) => ! str_contains((string) $value, substr($document, 0, 5))));

    $editResponse = $this->actingAs($user)->get("/settings/professionals/{$professional->id}/edit");
    $editResponse->assertInertia(fn ($page) => $page
        ->where('professional.document', fn ($value) => ! str_contains((string) $value, substr($document, 0, 5))));
});

it('exposes the operational summary on the edit page', function () {
    $user = actingOwnerWithActiveContext();
    $organization = $user->organizationMemberships()->first()->organization;
    $professional = Professional::factory()->for($organization)->create();

    $response = $this->actingAs($user)->get("/settings/professionals/{$professional->id}/edit");

    $response->assertInertia(fn ($page) => $page
        ->where('operationalSummary.status', 'incomplete')
        ->where('operationalSummary.is_operational', false)
        ->where('operationalSummary.active_units_count', 0)
        ->has('operationalSummary.reasons'));
});

it('exposes unit, specialty, service and operational filter data on the index page', function () {
    $user = actingOwnerWithActiveContext();
    $organization = $user->organizationMemberships()->first()->organization;
    $legalEntity = LegalEntity::factory()->for($organization)->create();
    $unit = Unit::factory()->for($organization)->create(['legal_entity_id' => $legalEntity->id, 'status' => RecordStatus::Active]);
    $professional = Professional::factory()->for($organization)->create(['status' => RecordStatus::Active]);
    $link = ProfessionalUnit::factory()->for($professional)->create(['organization_id' => $organization->id, 'unit_id' => $unit->id]);
    ProfessionalWorkingHour::factory()->for($link, 'professionalUnit')->create([
        'organization_id' => $organization->id,
        'weekday' => Weekday::Monday,
    ]);

    $response = $this->actingAs($user)->get('/settings/professionals');

    $response->assertInertia(fn ($page) => $page
        ->where('professionals.0.unit_names.0', $unit->name)
        ->where('professionals.0.has_working_hours', true)
        ->where('professionals.0.operational_status', 'operational')
        ->has('units')
        ->has('specialties'));
});

it('never stores the full document in the audit log', function () {
    $user = actingOwnerWithActiveContext();
    $document = LegalEntityFactory::validCpf();

    $this->actingAs($user)->post('/settings/professionals', [
        'name' => 'Profissional Auditado', 'document' => $document,
    ])->assertRedirect();

    $professional = Professional::query()->where('name', 'Profissional Auditado')->firstOrFail();
    $log = AuditLog::query()->where('auditable_id', $professional->id)->where('action', AuditAction::Created)->firstOrFail();

    expect($log->after_data['document'])->not->toBe($document)
        ->and($log->after_data['document'])->toEndWith(substr($document, -2));
});

it('keeps the existing document when the field is left blank on update', function () {
    $user = actingOwnerWithActiveContext();
    $organization = $user->organizationMemberships()->first()->organization;
    $document = LegalEntityFactory::validCpf();
    $professional = Professional::factory()->for($organization)->create(['document' => $document]);

    $this->actingAs($user)->put("/settings/professionals/{$professional->id}", [
        'name' => $professional->name,
        'display_name' => $professional->display_name,
    ])->assertRedirect('/settings/professionals');

    expect($professional->fresh()->document)->toBe($document);
});

it('uploads a professional photo using the safe file replacer', function () {
    Storage::fake('public');
    $user = actingOwnerWithActiveContext();
    $professional = Professional::factory()->for($user->organizationMemberships()->first()->organization)->create();
    $file = UploadedFile::fake()->image('photo.jpg', 200, 200);

    $this->actingAs($user)->post("/settings/professionals/{$professional->id}/photo", [
        'photo' => $file,
    ])->assertRedirect();

    $path = $professional->fresh()->photo_path;
    expect($path)->not->toBeNull();
    Storage::disk('public')->assertExists($path);
});

it('activates and deactivates a professional without touching the linked user', function () {
    $user = actingOwnerWithActiveContext();
    $organization = $user->organizationMemberships()->first()->organization;
    $linkedUser = User::factory()->create(['is_active' => true]);
    OrganizationMembership::factory()->for($organization)->for($linkedUser)->create(['status' => OrganizationMembershipStatus::Active]);
    $professional = Professional::factory()->for($organization)->create(['status' => RecordStatus::Active, 'user_id' => $linkedUser->id]);

    $this->actingAs($user)->patch("/settings/professionals/{$professional->id}/deactivate")->assertRedirect();
    expect($professional->fresh()->status)->toBe(RecordStatus::Inactive)
        ->and($linkedUser->fresh()->is_active)->toBeTrue();

    $this->actingAs($user)->patch("/settings/professionals/{$professional->id}/activate")->assertRedirect();
    expect($professional->fresh()->status)->toBe(RecordStatus::Active);
});

it('logically deletes a professional without deleting the linked user, memberships or SiteProfessional', function () {
    $user = actingOwnerWithActiveContext();
    $organization = $user->organizationMemberships()->first()->organization;
    $linkedUser = User::factory()->create();
    $membership = OrganizationMembership::factory()->for($organization)->for($linkedUser)->create(['status' => OrganizationMembershipStatus::Active]);
    $professional = Professional::factory()->for($organization)->create(['user_id' => $linkedUser->id]);
    $siteProfessional = SiteProfessional::factory()->create();

    $this->actingAs($user)->delete("/settings/professionals/{$professional->id}")->assertRedirect();

    expect($professional->fresh()->trashed())->toBeTrue()
        ->and(User::query()->find($linkedUser->id))->not->toBeNull()
        ->and(OrganizationMembership::query()->find($membership->id))->not->toBeNull()
        ->and(SiteProfessional::query()->find($siteProfessional->id))->not->toBeNull();
});

it('restores a deleted professional as inactive and detects a document conflict', function () {
    $user = actingOwnerWithActiveContext();
    $organization = $user->organizationMemberships()->first()->organization;
    $document = LegalEntityFactory::validCpf();
    $professional = Professional::factory()->for($organization)->create(['document' => $document, 'status' => RecordStatus::Active]);
    $professional->delete();

    $this->actingAs($user)->post("/settings/professionals/{$professional->id}/restore")->assertRedirect();
    expect($professional->fresh()->trashed())->toBeFalse()
        ->and($professional->fresh()->status)->toBe(RecordStatus::Inactive);

    $professional->delete();
    Professional::factory()->for($organization)->create(['document' => $document]);

    $this->actingAs($user)->post("/settings/professionals/{$professional->id}/restore")
        ->assertSessionHasErrors('professional');
});

it('blocks a member without the manage permission from creating a professional', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->create();
    OrganizationMembership::factory()->for($organization)->for($user)->create(['status' => OrganizationMembershipStatus::Active]);
    session(['active_organization_id' => $organization->id]);

    $this->actingAs($user)->post('/settings/professionals', ['name' => 'X'])->assertForbidden();
});

it('allows a member with the professionals.manage permission to create a professional', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->create();
    $permission = Permission::query()->create([
        'key' => PermissionKey::ProfessionalsManage->value,
        'group' => PermissionKey::ProfessionalsManage->group(),
        'label' => PermissionKey::ProfessionalsManage->label(),
    ]);
    $role = Role::factory()->for($organization)->create();
    $role->permissions()->attach($permission);
    OrganizationMembership::factory()->for($organization)->for($user)->create([
        'status' => OrganizationMembershipStatus::Active,
        'role_id' => $role->id,
    ]);
    session(['active_organization_id' => $organization->id]);

    $this->actingAs($user)->post('/settings/professionals', ['name' => 'Novo Profissional'])
        ->assertRedirect('/settings/professionals');
});

it('blocks access to a professional belonging to another organization even with a valid id', function () {
    $user = actingOwnerWithActiveContext();
    $foreignProfessional = Professional::factory()->for(Organization::factory()->create())->create();

    $this->actingAs($user)->get("/settings/professionals/{$foreignProfessional->id}/edit")->assertNotFound();
    $this->actingAs($user)->put("/settings/professionals/{$foreignProfessional->id}", ['name' => 'Hackeado', 'display_name' => 'Hackeado'])->assertNotFound();
});
