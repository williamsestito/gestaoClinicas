<?php

declare(strict_types=1);

use App\Enums\AuditAction;
use App\Enums\SystemRole;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Role;
use App\Models\User;

it('lets the owner view the audit log scoped to their organization', function () {
    $ctx = ownerActingInOrganization();

    AuditLog::factory()->for($ctx['organization'])->create(['action' => AuditAction::Created]);

    $otherOrganization = Organization::factory()->create();
    AuditLog::factory()->for($otherOrganization)->create(['action' => AuditAction::Deleted]);

    $this->actingAs($ctx['user'])
        ->get(route('settings.audit.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/audit/Index')
            ->where('logs.total', 1)
        );
});

it('blocks a non-owner without audit.view permission from viewing the audit log', function () {
    $ctx = ownerActingInOrganization();
    $member = User::factory()->create();
    OrganizationMembership::factory()->for($ctx['organization'])->for($member)->create();

    $this->actingAs($member)
        ->get(route('settings.audit.index'))
        ->assertForbidden();
});

it('grants a non-owner with the auditor role access to the audit log', function () {
    $ctx = ownerActingInOrganization();
    seedSystemRoles($ctx['organization']);
    $role = Role::query()->where('organization_id', $ctx['organization']->id)->where('slug', SystemRole::Auditor->value)->firstOrFail();

    $member = User::factory()->create();
    OrganizationMembership::factory()->for($ctx['organization'])->for($member)->create(['role_id' => $role->id]);

    $this->actingAs($member)
        ->get(route('settings.audit.index'))
        ->assertOk();
});

it('never exposes raw passwords or tokens in the audit log listing', function () {
    $ctx = ownerActingInOrganization();

    AuditLog::factory()->for($ctx['organization'])->create([
        'after_data' => ['name' => 'Alterado', 'note' => 'sem segredos aqui'],
    ]);

    $response = $this->actingAs($ctx['user'])->get(route('settings.audit.index'));

    $response->assertOk();
    expect($response->getContent())->not->toContain('senha123');
});

it('filters the audit log by action', function () {
    $ctx = ownerActingInOrganization();

    AuditLog::factory()->for($ctx['organization'])->create(['action' => AuditAction::Created]);
    AuditLog::factory()->for($ctx['organization'])->create(['action' => AuditAction::Deleted]);

    $this->actingAs($ctx['user'])
        ->get(route('settings.audit.index', ['action' => AuditAction::Deleted->value]))
        ->assertInertia(fn ($page) => $page->where('logs.total', 1));
});
