<?php

declare(strict_types=1);

use App\Enums\AuditAction;
use App\Enums\OrganizationMembershipStatus;
use App\Enums\PermissionKey;
use App\Enums\RecordStatus;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Patient;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;

function validEmergencyContacts(): array
{
    return [
        ['name' => 'Contato Um', 'relationship' => 'cônjuge', 'phone_primary' => '11999990000'],
    ];
}

it('shows an empty listing for a brand new clinic', function () {
    $user = actingOwnerWithActiveContext();

    $this->actingAs($user)
        ->get('/settings/patients')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/patients/Index')
            ->where('patients.data', []));
});

it('creates an adult patient with an emergency contact', function () {
    $user = actingOwnerWithActiveContext();

    $this->actingAs($user)->post('/settings/patients', [
        'name' => 'Maria da Silva',
        'birth_date' => '1990-05-10',
        'document' => '52998224725',
        'emergency_contacts' => validEmergencyContacts(),
    ])->assertRedirect('/settings/patients');

    $patient = Patient::query()->where('name', 'Maria da Silva')->firstOrFail();
    expect($patient->status)->toBe(RecordStatus::Active)
        ->and($patient->emergencyContacts()->count())->toBe(1)
        ->and($patient->responsibles()->count())->toBe(0)
        ->and(AuditLog::query()->where('auditable_id', $patient->id)->where('action', AuditAction::Created)->exists())->toBeTrue();
});

it('rejects a patient without any emergency contact', function () {
    $user = actingOwnerWithActiveContext();

    $this->actingAs($user)->post('/settings/patients', [
        'name' => 'Sem Contato',
        'birth_date' => '1990-05-10',
    ])->assertSessionHasErrors('emergency_contacts');

    expect(Patient::query()->where('name', 'Sem Contato')->exists())->toBeFalse();
});

it('rejects a minor patient without a legal guardian', function () {
    $user = actingOwnerWithActiveContext();

    $this->actingAs($user)->post('/settings/patients', [
        'name' => 'Criança Teste',
        'birth_date' => now()->subYears(10)->format('Y-m-d'),
        'emergency_contacts' => validEmergencyContacts(),
    ])->assertSessionHasErrors('responsibles');

    expect(Patient::query()->where('name', 'Criança Teste')->exists())->toBeFalse();
});

it('creates a minor patient when a legal guardian is provided', function () {
    $user = actingOwnerWithActiveContext();

    $this->actingAs($user)->post('/settings/patients', [
        'name' => 'Criança Com Responsável',
        'birth_date' => now()->subYears(10)->format('Y-m-d'),
        'emergency_contacts' => validEmergencyContacts(),
        'responsibles' => [
            [
                'name' => 'Mãe Teste',
                'phone' => '11988887777',
                'relationship' => 'mãe',
                'is_legal_guardian' => true,
            ],
        ],
    ])->assertRedirect('/settings/patients');

    $patient = Patient::query()->where('name', 'Criança Com Responsável')->firstOrFail();
    expect($patient->responsibles()->where('is_legal_guardian', true)->count())->toBe(1);
});

it('creates a patient with a complete address block', function () {
    $user = actingOwnerWithActiveContext();

    $this->actingAs($user)->post('/settings/patients', [
        'name' => 'Paciente Com Endereco',
        'birth_date' => '1990-05-10',
        'emergency_contacts' => validEmergencyContacts(),
        'address' => [
            'postal_code' => '01310-100',
            'street' => 'Av. Paulista',
            'number' => '1000',
            'neighborhood' => 'Bela Vista',
            'city' => 'São Paulo',
            'state' => 'SP',
        ],
    ])->assertRedirect('/settings/patients');

    $patient = Patient::query()->where('name', 'Paciente Com Endereco')->firstOrFail();
    expect($patient->address)->not->toBeNull()
        ->and($patient->address->city)->toBe('São Paulo');
});

it('rejects an incomplete address block', function () {
    $user = actingOwnerWithActiveContext();

    $this->actingAs($user)->post('/settings/patients', [
        'name' => 'Endereco Incompleto',
        'birth_date' => '1990-05-10',
        'emergency_contacts' => validEmergencyContacts(),
        'address' => ['street' => 'Av. Paulista'],
    ])->assertSessionHasErrors(['address.number', 'address.neighborhood', 'address.city', 'address.state']);
});

it('rejects a duplicate document within the same clinic but allows it in another clinic', function () {
    $user = actingOwnerWithActiveContext();
    $organization = $user->organizationMemberships()->first()->organization;
    Patient::factory()->for($organization)->create(['document' => '52998224725']);

    $this->actingAs($user)->post('/settings/patients', [
        'name' => 'Duplicado',
        'birth_date' => '1990-05-10',
        'document' => '52998224725',
        'emergency_contacts' => validEmergencyContacts(),
    ])->assertSessionHasErrors('document');

    $otherOrganization = Organization::factory()->create();
    Patient::factory()->for($otherOrganization)->create(['document' => '52998224725']);
    expect(Patient::query()->where('document', '52998224725')->count())->toBe(2);
});

it('updates a patient', function () {
    $user = actingOwnerWithActiveContext();
    $patient = Patient::factory()->for($user->organizationMemberships()->first()->organization)->create();

    $this->actingAs($user)->put("/settings/patients/{$patient->id}", [
        'name' => 'Nome Atualizado',
        'birth_date' => $patient->birth_date->toDateString(),
    ])->assertRedirect('/settings/patients');

    expect($patient->fresh()->name)->toBe('Nome Atualizado');
});

it('blocks marking an existing adult patient as minor without an already registered legal guardian', function () {
    $user = actingOwnerWithActiveContext();
    $patient = Patient::factory()->for($user->organizationMemberships()->first()->organization)->create();

    $this->actingAs($user)->put("/settings/patients/{$patient->id}", [
        'name' => $patient->name,
        'birth_date' => now()->subYears(10)->format('Y-m-d'),
    ])->assertSessionHasErrors('birth_date');
});

it('activates and deactivates a patient', function () {
    $user = actingOwnerWithActiveContext();
    $patient = Patient::factory()->for($user->organizationMemberships()->first()->organization)->create(['status' => RecordStatus::Active]);

    $this->actingAs($user)->patch("/settings/patients/{$patient->id}/deactivate")->assertRedirect();
    expect($patient->fresh()->status)->toBe(RecordStatus::Inactive);

    $this->actingAs($user)->patch("/settings/patients/{$patient->id}/activate")->assertRedirect();
    expect($patient->fresh()->status)->toBe(RecordStatus::Active);
});

it('logically deletes a patient and preserves its history', function () {
    $user = actingOwnerWithActiveContext();
    $patient = Patient::factory()->for($user->organizationMemberships()->first()->organization)->create();

    $this->actingAs($user)->delete("/settings/patients/{$patient->id}")->assertRedirect();

    expect($patient->fresh()->trashed())->toBeTrue()
        ->and(Patient::query()->find($patient->id))->toBeNull()
        ->and(Patient::withTrashed()->find($patient->id))->not->toBeNull();
});

it('restores a deleted patient as inactive', function () {
    $user = actingOwnerWithActiveContext();
    $patient = Patient::factory()->for($user->organizationMemberships()->first()->organization)->create(['status' => RecordStatus::Active]);
    $patient->delete();

    $this->actingAs($user)->post("/settings/patients/{$patient->id}/restore")->assertRedirect();

    expect($patient->fresh()->trashed())->toBeFalse()
        ->and($patient->fresh()->status)->toBe(RecordStatus::Inactive);
});

it('blocks a member without the patients.manage permission from creating a patient', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->create();
    OrganizationMembership::factory()->for($organization)->for($user)->create(['status' => OrganizationMembershipStatus::Active]);
    session(['active_organization_id' => $organization->id]);

    $this->actingAs($user)->post('/settings/patients', [
        'name' => 'Sem Permissao',
        'birth_date' => '1990-05-10',
        'emergency_contacts' => validEmergencyContacts(),
    ])->assertForbidden();
});

it('allows a member with the patients.manage permission to create a patient', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->create();
    $permission = Permission::query()->create([
        'key' => PermissionKey::PatientsManage->value,
        'group' => PermissionKey::PatientsManage->group(),
        'label' => PermissionKey::PatientsManage->label(),
    ]);
    $role = Role::factory()->for($organization)->create();
    $role->permissions()->attach($permission);
    OrganizationMembership::factory()->for($organization)->for($user)->create([
        'status' => OrganizationMembershipStatus::Active,
        'role_id' => $role->id,
    ]);
    session(['active_organization_id' => $organization->id]);

    $this->actingAs($user)->post('/settings/patients', [
        'name' => 'Com Permissao',
        'birth_date' => '1990-05-10',
        'emergency_contacts' => validEmergencyContacts(),
    ])->assertRedirect('/settings/patients');
});

it('blocks a member with only patients.view from creating or viewing a patient outside the index', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->create();
    $permission = Permission::query()->create([
        'key' => PermissionKey::PatientsView->value,
        'group' => PermissionKey::PatientsView->group(),
        'label' => PermissionKey::PatientsView->label(),
    ]);
    $role = Role::factory()->for($organization)->create();
    $role->permissions()->attach($permission);
    OrganizationMembership::factory()->for($organization)->for($user)->create([
        'status' => OrganizationMembershipStatus::Active,
        'role_id' => $role->id,
    ]);
    session(['active_organization_id' => $organization->id]);

    $this->actingAs($user)->get('/settings/patients')->assertOk();

    $this->actingAs($user)->post('/settings/patients', [
        'name' => 'Bloqueado',
        'birth_date' => '1990-05-10',
        'emergency_contacts' => validEmergencyContacts(),
    ])->assertForbidden();
});

it('blocks a member without any patients permission from viewing the index', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->create();
    OrganizationMembership::factory()->for($organization)->for($user)->create(['status' => OrganizationMembershipStatus::Active]);
    session(['active_organization_id' => $organization->id]);

    $this->actingAs($user)->get('/settings/patients')->assertForbidden();
});

it('blocks access to a patient belonging to another organization even with a valid id', function () {
    $user = actingOwnerWithActiveContext();
    $otherOrganization = Organization::factory()->create();
    $foreignPatient = Patient::factory()->for($otherOrganization)->create();

    $this->actingAs($user)->get("/settings/patients/{$foreignPatient->id}/edit")
        ->assertNotFound();

    $this->actingAs($user)->put("/settings/patients/{$foreignPatient->id}", [
        'name' => 'Hackeado',
        'birth_date' => '1990-05-10',
    ])->assertNotFound();
});
