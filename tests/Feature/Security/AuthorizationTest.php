<?php

declare(strict_types=1);

use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;

/**
 * Varredura direta de autorização (backend) em rotas administrativas
 * sensíveis: para cada uma, um membro sem permissão/papel algum deve ser
 * rejeitado no servidor mesmo que soubesse a URL exata — nunca confiar
 * apenas na ausência de um botão/menu no frontend (ver seção 17 da Etapa
 * 0.8). As permissões concedidas propositalmente (owner) já são cobertas
 * pelos testes de cada módulo; aqui o foco é confirmar que a ausência de
 * papel/permissão é, de fato, respeitada em cada endpoint.
 */
function memberWithNoRole(Organization $organization): User
{
    $member = User::factory()->create();
    OrganizationMembership::factory()->for($organization)->for($member)->create();

    return $member;
}

it('blocks a member with no role from every administrative write action across modules', function () {
    $owner = actingOwnerWithActiveContext();
    $organization = Organization::find(session('active_organization_id'));
    $unit = Unit::query()->where('organization_id', $organization->id)->firstOrFail();
    $member = memberWithNoRole($organization);

    // Unidades
    $this->actingAs($member)
        ->put("/settings/units/{$unit->id}", ['name' => 'Tentativa não autorizada'])
        ->assertForbidden();

    // Organização
    $this->actingAs($member)
        ->put('/settings/organization', ['name' => 'Tentativa não autorizada'])
        ->assertForbidden();

    // Papéis
    $this->actingAs($member)
        ->post('/settings/roles', ['name' => 'Papel forjado'])
        ->assertForbidden();

    // Usuários (convite)
    $this->actingAs($member)
        ->post(route('settings.users.invite'), ['email' => 'forjado@example.com'])
        ->assertForbidden();

    // Site institucional
    $this->actingAs($member)
        ->put('/settings/site', ['business_name' => 'Nome forjado'])
        ->assertForbidden();

    // Auditoria (somente leitura, mas também deve ser bloqueada)
    $this->actingAs($member)
        ->get('/settings/audit')
        ->assertForbidden();

    expect($organization->fresh()->name)->not->toBe('Tentativa não autorizada')
        ->and($unit->fresh()->name)->not->toBe('Tentativa não autorizada')
        ->and(Role::query()->where('name', 'Papel forjado')->exists())->toBeFalse();
});

it('rejects a guest (unauthenticated) request to every administrative route', function () {
    $this->get('/settings/organization')->assertRedirect('/login');
    $this->get('/settings/units')->assertRedirect('/login');
    $this->get('/settings/roles')->assertRedirect('/login');
    $this->get('/settings/users')->assertRedirect('/login');
    $this->get('/settings/audit')->assertRedirect('/login');
});
