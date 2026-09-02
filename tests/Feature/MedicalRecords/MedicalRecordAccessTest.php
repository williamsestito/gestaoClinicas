<?php

declare(strict_types=1);

use App\Actions\Organization\SeedSystemRolesAction;
use App\Enums\OrganizationMembershipStatus;
use App\Enums\PermissionKey;
use App\Enums\RecordStatus;
use App\Enums\SystemRole;
use App\Models\MedicalRecord;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Permission;
use App\Models\Professional;
use App\Models\Role;
use App\Models\User;

// medicalRecordSetup()/medicalRecordStaffUser() vivem em tests/Pest.php —
// compartilhadas com tests/Feature/PatientPortal/MedicalRecordVisibilityTest.php.

it('lets the authoring professional open their own medical record, creating the draft on first visit', function () {
    $setup = medicalRecordSetup();

    $this->actingAs($setup['professionalUser'])
        ->get("/settings/appointments/{$setup['appointment']->id}/prontuario")
        ->assertOk();

    expect(MedicalRecord::query()->where('appointment_id', $setup['appointment']->id)->count())->toBe(1);
});

it('is idempotent when opening the same appointment\'s medical record twice', function () {
    $setup = medicalRecordSetup();

    $this->actingAs($setup['professionalUser'])->get("/settings/appointments/{$setup['appointment']->id}/prontuario")->assertOk();
    $this->actingAs($setup['professionalUser'])->get("/settings/appointments/{$setup['appointment']->id}/prontuario")->assertOk();

    expect(MedicalRecord::query()->where('appointment_id', $setup['appointment']->id)->count())->toBe(1);
});

it('blocks a colleague professional from viewing another professional\'s medical record (RN-006 scope)', function () {
    $setup = medicalRecordSetup();
    $medicalRecord = MedicalRecord::factory()->create(['appointment_id' => $setup['appointment']->id]);

    $colleagueUser = medicalRecordStaffUser($setup['organization'], SystemRole::Professional);
    $colleague = Professional::factory()->for($setup['organization'])->create(['user_id' => $colleagueUser->id, 'status' => RecordStatus::Active]);
    session(['active_organization_id' => $setup['organization']->id]);

    $this->actingAs($colleagueUser)
        ->get("/settings/appointments/{$setup['appointment']->id}/prontuario")
        ->assertForbidden();

    expect($colleague)->not->toBeNull();
    expect($medicalRecord->professional_id)->not->toBe($colleague->id);
});

it('blocks reception from viewing a medical record (RN-006)', function () {
    $setup = medicalRecordSetup();
    MedicalRecord::factory()->create(['appointment_id' => $setup['appointment']->id]);
    $receptionUser = medicalRecordStaffUser($setup['organization'], SystemRole::Reception);
    session(['active_organization_id' => $setup['organization']->id]);

    $this->actingAs($receptionUser)
        ->get("/settings/appointments/{$setup['appointment']->id}/prontuario")
        ->assertForbidden();
});

it('blocks finance from viewing a medical record (RN-006)', function () {
    $setup = medicalRecordSetup();
    MedicalRecord::factory()->create(['appointment_id' => $setup['appointment']->id]);
    $financeUser = medicalRecordStaffUser($setup['organization'], SystemRole::Finance);
    session(['active_organization_id' => $setup['organization']->id]);

    $this->actingAs($financeUser)
        ->get("/settings/appointments/{$setup['appointment']->id}/prontuario")
        ->assertForbidden();
});

it('blocks the organization owner from viewing a colleague\'s medical record without an explicit clinical permission (RN-016)', function () {
    $setup = medicalRecordSetup();
    MedicalRecord::factory()->create(['appointment_id' => $setup['appointment']->id]);

    // Vínculo real de proprietário: `role_id` aponta para o papel
    // "Proprietário" de verdade, gerado por SeedSystemRolesAction (mesmo
    // estado de qualquer dono de organização em produção, via
    // OnboardOrganizationAction) — não um vínculo sintético com
    // `role_id: null`. Isso é deliberado: `SystemRole::Owner->defaultPermissions()`
    // já exclui `medical-records.manage`/`medical-records.manage-own` do
    // conjunto padrão do proprietário justamente para este teste continuar
    // vermelho se essa exclusão for revertida por engano.
    $ownerRole = Role::query()->where('organization_id', $setup['organization']->id)->where('slug', SystemRole::Owner->value)->firstOrFail();
    $ownerUser = User::factory()->create();
    OrganizationMembership::factory()->owner()->for($setup['organization'])->for($ownerUser)->create([
        'status' => OrganizationMembershipStatus::Active,
        'role_id' => $ownerRole->id,
    ]);
    session(['active_organization_id' => $setup['organization']->id]);

    $this->actingAs($ownerUser)
        ->get("/settings/appointments/{$setup['appointment']->id}/prontuario")
        ->assertForbidden();
});

it('blocks an owner who is also the record\'s author from clinical access via the is_owner shortcut (RN-016)', function () {
    $setup = medicalRecordSetup();

    // O proprietário também é o profissional autor do registro (dono da
    // clínica que atende pessoalmente) — `PermissionChecker::can()`
    // liberaria isso via `is_owner`, mas `MedicalRecordPolicy` nunca usa
    // esse atalho: mesmo autor, sem `medical-records.manage-own` real no
    // papel, continua bloqueado.
    $ownerRole = Role::query()->where('organization_id', $setup['organization']->id)->where('slug', SystemRole::Owner->value)->firstOrFail();
    OrganizationMembership::query()
        ->where('organization_id', $setup['organization']->id)
        ->where('user_id', $setup['professionalUser']->id)
        ->update(['is_owner' => true, 'role_id' => $ownerRole->id]);

    $this->actingAs($setup['professionalUser'])
        ->get("/settings/appointments/{$setup['appointment']->id}/prontuario")
        ->assertForbidden();
});

it('blocks the platform admin from viewing a medical record without an explicit clinical permission (RN-015)', function () {
    $setup = medicalRecordSetup();
    MedicalRecord::factory()->create(['appointment_id' => $setup['appointment']->id]);

    // Um administrador da plataforma só ganha vínculo real numa organização
    // ao acessá-la explicitamente (ver PlatformAdminAccessTest) — este
    // vínculo, sem role_id/permissão clínica, é o estado real depois disso.
    $adminUser = User::factory()->create(['is_platform_admin' => true, 'email_verified_at' => now()]);
    OrganizationMembership::factory()->for($setup['organization'])->for($adminUser)->create([
        'status' => OrganizationMembershipStatus::Active,
        'role_id' => null,
    ]);
    session(['active_organization_id' => $setup['organization']->id]);

    $this->actingAs($adminUser)
        ->get("/settings/appointments/{$setup['appointment']->id}/prontuario")
        ->assertForbidden();
});

it('grants access to a user with an explicit medical-records.manage permission on a custom role (e.g. "Responsável técnico")', function () {
    $setup = medicalRecordSetup();
    MedicalRecord::factory()->create(['appointment_id' => $setup['appointment']->id]);

    $permission = Permission::query()->where('key', PermissionKey::MedicalRecordsManage->value)->firstOrFail();
    $customRole = Role::query()->create([
        'organization_id' => $setup['organization']->id,
        'name' => 'Responsável técnico',
        'slug' => 'responsavel-tecnico-teste',
        'is_system' => false,
    ]);
    $customRole->permissions()->attach($permission->id);

    $supervisorUser = User::factory()->create();
    OrganizationMembership::factory()->for($setup['organization'])->for($supervisorUser)->create([
        'status' => OrganizationMembershipStatus::Active,
        'role_id' => $customRole->id,
    ]);
    session(['active_organization_id' => $setup['organization']->id]);

    $this->actingAs($supervisorUser)
        ->get("/settings/appointments/{$setup['appointment']->id}/prontuario")
        ->assertOk();
});

it('blocks access to a medical record from another organization via the middleware guard, even with a matching-shaped session', function () {
    $setup = medicalRecordSetup();
    $medicalRecord = MedicalRecord::factory()->create(['appointment_id' => $setup['appointment']->id]);

    // Vínculo ativo de verdade na outra organização — um usuário pode
    // legitimamente ter Professional em mais de uma organização (mesmo
    // padrão já coberto em MyAppointmentRequestsTest); sem isso, o
    // middleware de resolução de organização nunca chegaria a
    // EnsureMedicalRecordMembership, mascarando o teste com um redirect.
    $otherOrganization = Organization::factory()->create();
    app(SeedSystemRolesAction::class)->handle($otherOrganization);
    $otherRole = Role::query()->where('organization_id', $otherOrganization->id)->where('slug', SystemRole::Professional->value)->firstOrFail();
    OrganizationMembership::factory()->for($otherOrganization)->for($setup['professionalUser'])->create([
        'status' => OrganizationMembershipStatus::Active,
        'role_id' => $otherRole->id,
    ]);
    session(['active_organization_id' => $otherOrganization->id]);

    $this->actingAs($setup['professionalUser'])
        ->patch("/settings/prontuarios/{$medicalRecord->id}", ['has_return_right' => false])
        ->assertNotFound();
});
