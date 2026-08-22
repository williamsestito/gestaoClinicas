<?php

declare(strict_types=1);

use App\Actions\Organization\SeedSystemRolesAction;
use App\Enums\OrganizationMembershipStatus;
use App\Enums\RecordStatus;
use App\Enums\SystemRole;
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
