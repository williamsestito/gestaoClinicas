<?php

declare(strict_types=1);

use App\Models\Organization;
use App\Models\Patient;

it('finds an exact match by document', function () {
    $user = actingOwnerWithActiveContext();
    $organization = $user->organizationMemberships()->first()->organization;
    $patient = Patient::factory()->for($organization)->create(['document' => '52998224725']);

    $this->actingAs($user)
        ->getJson('/settings/patients/duplicates?document=529.982.247-25')
        ->assertOk()
        ->assertJsonPath('matches.0.id', $patient->id);
});

it('finds an exact match by name and birth date combined', function () {
    $user = actingOwnerWithActiveContext();
    $organization = $user->organizationMemberships()->first()->organization;
    $patient = Patient::factory()->for($organization)->create([
        'name' => 'Fulano de Tal',
        'birth_date' => '1985-03-20',
    ]);

    $this->actingAs($user)
        ->getJson('/settings/patients/duplicates?name=Fulano de Tal&birth_date=1985-03-20')
        ->assertOk()
        ->assertJsonPath('matches.0.id', $patient->id);
});

it('never matches by name alone, without birth date', function () {
    $user = actingOwnerWithActiveContext();
    $organization = $user->organizationMemberships()->first()->organization;
    Patient::factory()->for($organization)->create(['name' => 'Fulano de Tal']);

    $this->actingAs($user)
        ->getJson('/settings/patients/duplicates?name=Fulano de Tal')
        ->assertOk()
        ->assertJsonCount(0, 'matches');
});

it('returns no matches when no criteria is usable', function () {
    $user = actingOwnerWithActiveContext();

    $this->actingAs($user)
        ->getJson('/settings/patients/duplicates')
        ->assertOk()
        ->assertJsonCount(0, 'matches');
});

it('never returns a match from another organization', function () {
    $user = actingOwnerWithActiveContext();
    $otherOrganization = Organization::factory()->create();
    Patient::factory()->for($otherOrganization)->create(['document' => '52998224725']);

    $this->actingAs($user)
        ->getJson('/settings/patients/duplicates?document=52998224725')
        ->assertOk()
        ->assertJsonCount(0, 'matches');
});
