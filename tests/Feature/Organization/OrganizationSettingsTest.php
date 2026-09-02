<?php

declare(strict_types=1);

use App\Enums\OrganizationMembershipStatus;
use App\Enums\SystemRole;
use App\Models\Role;
use App\Models\User;

it('lets the owner edit the organization and reports canUpdate as true', function () {
    $ctx = ownerActingInOrganization();

    $this->actingAs($ctx['user'])
        ->get(route('settings.organization.edit'))
        ->assertInertia(fn ($page) => $page->where('canUpdate', true));

    $this->actingAs($ctx['user'])
        ->put(route('settings.organization.update'), [
            'name' => 'Nova Razão Social',
            'default_timezone' => 'America/Sao_Paulo',
            'default_currency' => 'BRL',
            'locale' => 'pt_BR',
        ])
        ->assertSessionHasNoErrors();

    expect($ctx['organization']->fresh()->name)->toBe('Nova Razão Social');
});

it('lets a non-owner Administrador da clínica edit the organization', function () {
    $ctx = ownerActingInOrganization();
    $nonOwner = nonOwnerActingWithRole($ctx['organization'], SystemRole::ClinicAdmin);

    $this->actingAs($nonOwner['user'])
        ->get(route('settings.organization.edit'))
        ->assertInertia(fn ($page) => $page->where('canUpdate', true));

    $this->actingAs($nonOwner['user'])
        ->put(route('settings.organization.update'), [
            'name' => 'Editado pelo administrador',
            'default_timezone' => 'America/Sao_Paulo',
            'default_currency' => 'BRL',
            'locale' => 'pt_BR',
        ])
        ->assertSessionHasNoErrors();

    expect($ctx['organization']->fresh()->name)->toBe('Editado pelo administrador');
});

it('blocks a member without organization.update from editing, and reports canUpdate as false', function () {
    $ctx = ownerActingInOrganization();
    seedSystemRoles($ctx['organization']);
    $auditorRole = Role::query()->where('organization_id', $ctx['organization']->id)->where('slug', SystemRole::Auditor->value)->firstOrFail();

    $member = User::factory()->create();
    $ctx['organization']->memberships()->create([
        'user_id' => $member->id,
        'role_id' => $auditorRole->id,
        'status' => OrganizationMembershipStatus::Active,
    ]);

    $this->actingAs($member)
        ->get(route('settings.organization.edit'))
        ->assertInertia(fn ($page) => $page->where('canUpdate', false));

    $this->actingAs($member)
        ->put(route('settings.organization.update'), ['name' => 'Não deveria funcionar'])
        ->assertForbidden();
});
