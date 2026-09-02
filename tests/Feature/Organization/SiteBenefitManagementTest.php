<?php

declare(strict_types=1);

use App\Enums\PermissionKey;
use App\Enums\SystemRole;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Role;
use App\Models\SiteBenefit;
use App\Models\User;
use App\Support\Authorization\PermissionChecker;

it('lets the owner create a benefit and auto-assigns the next order', function () {
    $user = actingOwnerWithActiveContext();
    SiteBenefit::factory()->create(['order' => 0]);

    $this->actingAs($user)->post('/settings/site/benefits', [
        'icon' => 'heart-pulse',
        'title' => 'Atendimento humanizado',
        'description' => 'Cada paciente é único.',
    ])->assertRedirect();

    $benefit = SiteBenefit::query()->where('title', 'Atendimento humanizado')->firstOrFail();
    expect($benefit->order)->toBe(1)
        ->and($benefit->is_active)->toBeTrue();
});

it('lets the owner update, toggle and delete a benefit', function () {
    $user = actingOwnerWithActiveContext();
    $benefit = SiteBenefit::factory()->create(['title' => 'Original']);

    $this->actingAs($user)->put("/settings/site/benefits/{$benefit->id}", [
        'title' => 'Atualizado',
        'description' => null,
    ])->assertRedirect();
    expect($benefit->fresh()->title)->toBe('Atualizado');

    $this->actingAs($user)->patch("/settings/site/benefits/{$benefit->id}/toggle")
        ->assertRedirect();
    expect($benefit->fresh()->is_active)->toBeFalse();

    $this->actingAs($user)->delete("/settings/site/benefits/{$benefit->id}")
        ->assertRedirect();
    expect(SiteBenefit::query()->find($benefit->id))->toBeNull()
        ->and(SiteBenefit::withTrashed()->find($benefit->id))->not->toBeNull();
});

it('reorders benefits based on the submitted id order', function () {
    $user = actingOwnerWithActiveContext();
    $first = SiteBenefit::factory()->create(['order' => 0]);
    $second = SiteBenefit::factory()->create(['order' => 1]);

    $this->actingAs($user)->patch('/settings/site/benefits/reorder', [
        'ids' => [$second->id, $first->id],
    ])->assertRedirect();

    expect($second->fresh()->order)->toBe(0)
        ->and($first->fresh()->order)->toBe(1);
});

it('blocks a non-owner without site.update from creating a benefit', function () {
    $user = actingOwnerWithActiveContext();
    $organizationId = session('active_organization_id');
    $member = User::factory()->create();

    OrganizationMembership::factory()
        ->for(Organization::find($organizationId))
        ->for($member)
        ->create();

    $this->actingAs($member)->post('/settings/site/benefits', [
        'title' => 'Não autorizado',
    ])->assertForbidden();
});

it('grants a member with site.update permission access to manage benefits', function () {
    $user = actingOwnerWithActiveContext();
    $organization = Organization::find(session('active_organization_id'));
    seedSystemRoles($organization);
    $role = Role::query()->where('organization_id', $organization->id)->where('slug', SystemRole::ClinicAdmin->value)->firstOrFail();

    $member = User::factory()->create();
    OrganizationMembership::factory()->for($organization)->for($member)->create(['role_id' => $role->id]);

    expect(app(PermissionChecker::class)->can($member, PermissionKey::SiteUpdate, $organization->id))->toBeTrue();

    $this->actingAs($member)->post('/settings/site/benefits', [
        'title' => 'Criado por gestor',
    ])->assertRedirect();

    expect(SiteBenefit::query()->where('title', 'Criado por gestor')->exists())->toBeTrue();
});

it('validates required fields', function () {
    $user = actingOwnerWithActiveContext();

    $this->actingAs($user)->post('/settings/site/benefits', [])
        ->assertSessionHasErrors('title');
});
