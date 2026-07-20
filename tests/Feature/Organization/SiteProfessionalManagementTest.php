<?php

declare(strict_types=1);

use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\SiteProfessional;
use App\Models\User;

it('lets the owner create and delete a professional', function () {
    $user = actingOwnerWithActiveContext();

    $this->actingAs($user)->post('/settings/site/professionals', [
        'name' => 'Dra. Camila Andrade',
        'role_title' => 'Dermatologista',
    ])->assertRedirect();

    $professional = SiteProfessional::query()->where('name', 'Dra. Camila Andrade')->firstOrFail();

    $this->actingAs($user)->delete("/settings/site/professionals/{$professional->id}")
        ->assertRedirect();

    expect(SiteProfessional::query()->find($professional->id))->toBeNull();
});

it('validates social links as urls', function () {
    $user = actingOwnerWithActiveContext();

    $this->actingAs($user)->post('/settings/site/professionals', [
        'name' => 'Profissional',
        'instagram_url' => 'not-a-url',
    ])->assertSessionHasErrors('instagram_url');
});

it('blocks a non-owner without site.update from managing professionals', function () {
    actingOwnerWithActiveContext();
    $organization = Organization::find(session('active_organization_id'));
    $member = User::factory()->create();
    OrganizationMembership::factory()->for($organization)->for($member)->create();

    $this->actingAs($member)->post('/settings/site/professionals', [
        'name' => 'Não autorizado',
    ])->assertForbidden();
});
