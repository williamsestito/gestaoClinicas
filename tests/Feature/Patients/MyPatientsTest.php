<?php

declare(strict_types=1);

use App\Actions\Organization\SeedSystemRolesAction;
use App\Enums\AppointmentRequestStatus;
use App\Enums\AppointmentStatus;
use App\Enums\OrganizationMembershipStatus;
use App\Enums\RecordStatus;
use App\Enums\SystemRole;
use App\Models\Appointment;
use App\Models\AppointmentRequest;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Patient;
use App\Models\Professional;
use App\Models\Role;
use App\Models\User;

/** @return array{0: User, 1: Organization, 2: Professional} */
function myPatientsSetup(): array
{
    $organization = Organization::factory()->create();
    app(SeedSystemRolesAction::class)->handle($organization);
    $role = Role::query()->where('organization_id', $organization->id)->where('slug', SystemRole::Professional->value)->firstOrFail();

    $user = User::factory()->create();
    $professional = Professional::factory()->for($organization)->create(['user_id' => $user->id, 'status' => RecordStatus::Active]);
    OrganizationMembership::factory()->for($organization)->for($user)->create([
        'status' => OrganizationMembershipStatus::Active,
        'role_id' => $role->id,
    ]);
    session(['active_organization_id' => $organization->id]);

    return [$user, $organization, $professional];
}

it('lists only patients whose primary professional is the logged-in professional', function () {
    [$user, $organization, $professional] = myPatientsSetup();
    $ownPatient = Patient::factory()->for($organization)->create(['primary_professional_id' => $professional->id, 'name' => 'Paciente Próprio']);
    Patient::factory()->for($organization)->create(['primary_professional_id' => null, 'name' => 'Paciente de Outro']);

    $response = $this->actingAs($user)->get('/settings/meus-pacientes');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('patients.total', 1)
            ->where('patients.data.0.name', 'Paciente Próprio'));
});

it('shows an empty state for a user without a linked professional', function () {
    $organization = Organization::factory()->create();
    app(SeedSystemRolesAction::class)->handle($organization);
    $role = Role::query()->where('organization_id', $organization->id)->where('slug', SystemRole::Professional->value)->firstOrFail();
    $user = User::factory()->create();
    OrganizationMembership::factory()->for($organization)->for($user)->create([
        'status' => OrganizationMembershipStatus::Active,
        'role_id' => $role->id,
    ]);
    session(['active_organization_id' => $organization->id]);

    $response = $this->actingAs($user)->get('/settings/meus-pacientes');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page->where('patients', null));
});

it('lets the professional open their own patient edit page', function () {
    [$user, $organization, $professional] = myPatientsSetup();
    $patient = Patient::factory()->for($organization)->create(['primary_professional_id' => $professional->id]);

    $this->actingAs($user)->get("/settings/patients/{$patient->id}/edit")->assertOk();
});

it('lists a patient the professional has already attended, even without being the primary professional', function () {
    [$user, $organization, $professional] = myPatientsSetup();
    $patient = Patient::factory()->for($organization)->create(['primary_professional_id' => null, 'name' => 'Já Atendido']);
    Appointment::factory()->for($organization)->for($professional)->for($patient)->create(['status' => AppointmentStatus::Completed]);

    $response = $this->actingAs($user)->get('/settings/meus-pacientes');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('patients.total', 1)
            ->where('patients.data.0.name', 'Já Atendido')
            ->where('patients.data.0.full_access', true)
            ->where('patients.data.0.relationship_label', 'Já atendido'));
});

it('lists a patient with only a pending appointment request, marked without full access', function () {
    [$user, $organization, $professional] = myPatientsSetup();
    $patient = Patient::factory()->for($organization)->create(['primary_professional_id' => null, 'name' => 'Só Pré-agendado']);
    AppointmentRequest::factory()->for($organization)->for($professional)->for($patient)->create(['status' => AppointmentRequestStatus::Pending]);

    $response = $this->actingAs($user)->get('/settings/meus-pacientes');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('patients.total', 1)
            ->where('patients.data.0.name', 'Só Pré-agendado')
            ->where('patients.data.0.full_access', false)
            ->where('patients.data.0.relationship_label', 'Pré-agendamento pendente'));
});

it('lets the professional open the edit page of a patient already attended, even without being the primary professional', function () {
    [$user, $organization, $professional] = myPatientsSetup();
    $patient = Patient::factory()->for($organization)->create(['primary_professional_id' => null]);
    Appointment::factory()->for($organization)->for($professional)->for($patient)->create(['status' => AppointmentStatus::Completed]);

    $this->actingAs($user)->get("/settings/patients/{$patient->id}/edit")->assertOk();
});

it('blocks the professional from opening the edit page of a patient with only a pending appointment request', function () {
    [$user, $organization, $professional] = myPatientsSetup();
    $patient = Patient::factory()->for($organization)->create(['primary_professional_id' => null]);
    AppointmentRequest::factory()->for($organization)->for($professional)->for($patient)->create(['status' => AppointmentRequestStatus::Pending]);

    $this->actingAs($user)->get("/settings/patients/{$patient->id}/edit")->assertForbidden();
});
