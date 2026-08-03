<?php

declare(strict_types=1);

use App\Actions\Organization\SeedSystemRolesAction;
use App\Enums\AuditAction;
use App\Enums\OrganizationMembershipStatus;
use App\Enums\RecordStatus;
use App\Enums\SystemRole;
use App\Enums\Weekday;
use App\Models\AuditLog;
use App\Models\LegalEntity;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Professional;
use App\Models\ProfessionalUnit;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;

/** @return array{0: User, 1: Organization, 2: Professional, 3: Unit, 4: ProfessionalUnit} */
function selfServiceSetup(): array
{
    $organization = Organization::factory()->create();
    app(SeedSystemRolesAction::class)->handle($organization);
    $role = Role::query()->where('organization_id', $organization->id)->where('slug', SystemRole::Professional->value)->firstOrFail();

    $legalEntity = LegalEntity::factory()->for($organization)->create();
    $unit = Unit::factory()->for($organization)->create(['legal_entity_id' => $legalEntity->id, 'status' => RecordStatus::Active]);
    $unit->openingHours()->create([
        'organization_id' => $organization->id,
        'day_of_week' => Weekday::Monday->value,
        'opens_at' => '08:00',
        'closes_at' => '18:00',
    ]);

    $user = User::factory()->create();
    $professional = Professional::factory()->for($organization)->create(['user_id' => $user->id, 'status' => RecordStatus::Active]);
    OrganizationMembership::factory()->for($organization)->for($user)->create([
        'status' => OrganizationMembershipStatus::Active,
        'role_id' => $role->id,
    ]);
    session(['active_organization_id' => $organization->id]);

    $link = ProfessionalUnit::factory()->for($professional)->create(['organization_id' => $organization->id, 'unit_id' => $unit->id, 'status' => RecordStatus::Active]);

    return [$user, $organization, $professional, $unit, $link];
}

it('redirects "Minha agenda" to the professional own availability page when linked', function () {
    [$user, , $professional] = selfServiceSetup();

    $this->actingAs($user)->get('/settings/minha-agenda')
        ->assertRedirect("/settings/professionals/{$professional->id}/availability");
});

it('redirects "Minha agenda" ausências to the professional own time-blocks page when linked', function () {
    [$user, , $professional] = selfServiceSetup();

    $this->actingAs($user)->get('/settings/minha-agenda/ausencias')
        ->assertRedirect("/settings/professionals/{$professional->id}/time-blocks");
});

it('shows an empty state on "Minha agenda" for a user without a linked professional', function () {
    $user = actingOwnerWithActiveContext();

    $this->actingAs($user)->get('/settings/minha-agenda')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/my-schedule/Show'));
});

it('lets a linked professional configure their own weekly agenda in batch', function () {
    [$user, , $professional, , $link] = selfServiceSetup();

    $this->actingAs($user)->post("/settings/professionals/{$professional->id}/units/{$link->id}/working-hours/configure", [
        'weekdays' => [1],
        'intervals' => [['starts_at' => '08:00', 'ends_at' => '12:00']],
        'effective_from' => '2026-08-01',
        'effective_until' => '2026-08-30',
    ])->assertRedirect();

    expect($professional->workingHours()->count())->toBe(1);
});

it('records the professional as the audit actor of their own self-service agenda change, distinguishable from an admin edit', function () {
    [$user, , $professional, , $link] = selfServiceSetup();

    $this->actingAs($user)->post("/settings/professionals/{$professional->id}/units/{$link->id}/working-hours/configure", [
        'weekdays' => [1],
        'intervals' => [['starts_at' => '08:00', 'ends_at' => '12:00']],
        'effective_from' => '2026-08-01',
        'effective_until' => '2026-08-30',
    ])->assertRedirect();

    $log = AuditLog::query()->where('action', AuditAction::Created)->latest()->first();

    // O ator é sempre o usuário autenticado (Auth::id()) — o mesmo campo
    // usado para edições de administrador. Comparar com o user_id vinculado
    // ao profissional é o que permite distinguir "profissional" de
    // "administrador" na revisão do log, sem precisar de um campo extra.
    expect($log->actor_user_id)->toBe($user->id)
        ->and($log->actor_user_id)->toBe($professional->user_id);
});

it('blocks a professional from configuring the agenda of another professional in the same clinic', function () {
    [$user, $organization] = selfServiceSetup();
    $otherProfessional = Professional::factory()->for($organization)->create(['status' => RecordStatus::Active]);
    $otherUnit = Unit::factory()->for($organization)->create(['legal_entity_id' => LegalEntity::factory()->for($organization)->create()->id, 'status' => RecordStatus::Active]);
    $otherLink = ProfessionalUnit::factory()->for($otherProfessional)->create(['organization_id' => $organization->id, 'unit_id' => $otherUnit->id, 'status' => RecordStatus::Active]);

    $this->actingAs($user)->post("/settings/professionals/{$otherProfessional->id}/units/{$otherLink->id}/working-hours/configure", [
        'weekdays' => [1],
        'intervals' => [['starts_at' => '08:00', 'ends_at' => '12:00']],
        'effective_from' => '2026-08-01',
        'effective_until' => '2026-08-30',
    ])->assertForbidden();

    expect($otherProfessional->workingHours()->count())->toBe(0);
});

it('blocks a professional from another organization even with a self-linked record', function () {
    [$user] = selfServiceSetup();
    $foreignOrganization = Organization::factory()->create();
    $foreignProfessional = Professional::factory()->for($foreignOrganization)->create(['user_id' => $user->id, 'status' => RecordStatus::Active]);
    $foreignLegalEntity = LegalEntity::factory()->for($foreignOrganization)->create();
    $foreignUnit = Unit::factory()->for($foreignOrganization)->create(['legal_entity_id' => $foreignLegalEntity->id, 'status' => RecordStatus::Active]);
    $foreignLink = ProfessionalUnit::factory()->for($foreignProfessional)->create(['organization_id' => $foreignOrganization->id, 'unit_id' => $foreignUnit->id, 'status' => RecordStatus::Active]);

    $this->actingAs($user)->post("/settings/professionals/{$foreignProfessional->id}/units/{$foreignLink->id}/working-hours/configure", [
        'weekdays' => [1],
        'intervals' => [['starts_at' => '08:00', 'ends_at' => '12:00']],
        'effective_from' => '2026-08-01',
        'effective_until' => '2026-08-30',
    ])->assertNotFound();
});

it('lets a linked professional create and manage their own time blocks', function () {
    [$user, , $professional] = selfServiceSetup();

    $this->actingAs($user)->post("/settings/professionals/{$professional->id}/time-blocks", [
        'type' => 'day_off',
        'scope' => 'all_units',
        'is_all_day' => true,
        'starts_date' => '2026-08-10',
        'ends_date' => '2026-08-10',
    ])->assertRedirect();

    expect($professional->timeBlocks()->count())->toBe(1);
});

it('blocks an inactive user from managing their own agenda even when linked', function () {
    [$user, , $professional, , $link] = selfServiceSetup();
    $user->update(['is_active' => false]);

    $this->actingAs($user)->post("/settings/professionals/{$professional->id}/units/{$link->id}/working-hours/configure", [
        'weekdays' => [1],
        'intervals' => [['starts_at' => '08:00', 'ends_at' => '12:00']],
        'effective_from' => '2026-08-01',
        'effective_until' => '2026-08-30',
    ])->assertRedirect('/');
});
