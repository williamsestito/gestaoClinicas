<?php

declare(strict_types=1);

use App\Actions\Organization\SeedSystemRolesAction;
use App\Enums\OrganizationMembershipStatus;
use App\Enums\RecordStatus;
use App\Enums\SystemRole;
use App\Models\LegalEntity;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Professional;
use App\Models\ProfessionalDashboardReminder;
use App\Models\Role;
use App\Models\Unit;
use App\Models\UnitMembership;
use App\Models\User;

/** @return array{0: User, 1: Organization, 2: Professional} */
function dashboardReminderSetup(): array
{
    $organization = Organization::factory()->create();
    $legalEntity = LegalEntity::factory()->primary()->for($organization)->create();
    $headquarters = Unit::factory()->headquarters()->for($organization)->for($legalEntity, 'legalEntity')->create();
    app(SeedSystemRolesAction::class)->handle($organization);
    $role = Role::query()->where('organization_id', $organization->id)->where('slug', SystemRole::Professional->value)->firstOrFail();

    $user = User::factory()->create();
    $professional = Professional::factory()->for($organization)->create(['user_id' => $user->id, 'status' => RecordStatus::Active]);
    $membership = OrganizationMembership::factory()->for($organization)->for($user)->create([
        'status' => OrganizationMembershipStatus::Active,
        'role_id' => $role->id,
    ]);
    UnitMembership::factory()->for($membership, 'organizationMembership')->for($headquarters, 'unit')->create();
    session(['active_organization_id' => $organization->id, 'active_unit_id' => $headquarters->id]);

    return [$user, $organization, $professional];
}

it('lets a professional create a reminder for themselves', function () {
    [$user, $organization, $professional] = dashboardReminderSetup();

    $this->actingAs($user)->post('/dashboard/lembretes', [
        'body' => 'Ligar para o laboratório.',
        'color' => 'pink',
    ])->assertRedirect();

    expect(ProfessionalDashboardReminder::query()->where('organization_id', $organization->id)->where('professional_id', $professional->id)->count())->toBe(1);
});

it('lets a professional set an alarm time on a reminder', function () {
    [$user, $organization, $professional] = dashboardReminderSetup();

    $this->actingAs($user)->post('/dashboard/lembretes', [
        'body' => 'Tomar remédio',
        'color' => 'yellow',
        'alarm_at' => '2026-08-23T15:00:00.000Z',
    ])->assertRedirect();

    $reminder = ProfessionalDashboardReminder::query()->where('organization_id', $organization->id)->where('professional_id', $professional->id)->firstOrFail();
    expect($reminder->alarm_at->toIso8601String())->toBe('2026-08-23T15:00:00+00:00');
});

it('leaves the alarm time null when none is given', function () {
    [$user, $organization, $professional] = dashboardReminderSetup();

    $this->actingAs($user)->post('/dashboard/lembretes', [
        'body' => 'Sem alarme',
        'color' => 'yellow',
    ])->assertRedirect();

    $reminder = ProfessionalDashboardReminder::query()->where('organization_id', $organization->id)->where('professional_id', $professional->id)->firstOrFail();
    expect($reminder->alarm_at)->toBeNull();
});

it('requires a non-empty body and a valid color', function () {
    [$user] = dashboardReminderSetup();

    $this->actingAs($user)->post('/dashboard/lembretes', [
        'body' => '',
        'color' => 'purple',
    ])->assertSessionHasErrors(['body', 'color']);
});

it('blocks creating a reminder for a user without a linked professional record', function () {
    $organization = Organization::factory()->create();
    $legalEntity = LegalEntity::factory()->primary()->for($organization)->create();
    $headquarters = Unit::factory()->headquarters()->for($organization)->for($legalEntity, 'legalEntity')->create();
    app(SeedSystemRolesAction::class)->handle($organization);
    $role = Role::query()->where('organization_id', $organization->id)->where('slug', SystemRole::Professional->value)->firstOrFail();
    $user = User::factory()->create();
    $membership = OrganizationMembership::factory()->for($organization)->for($user)->create([
        'status' => OrganizationMembershipStatus::Active,
        'role_id' => $role->id,
    ]);
    UnitMembership::factory()->for($membership, 'organizationMembership')->for($headquarters, 'unit')->create();
    session(['active_organization_id' => $organization->id, 'active_unit_id' => $headquarters->id]);

    $this->actingAs($user)->post('/dashboard/lembretes', [
        'body' => 'Teste',
        'color' => 'yellow',
    ])->assertForbidden();
});

it('lets a professional delete their own reminder', function () {
    [$user, $organization, $professional] = dashboardReminderSetup();
    $reminder = ProfessionalDashboardReminder::factory()->for($organization)->for($professional)->create();

    $this->actingAs($user)->delete("/dashboard/lembretes/{$reminder->id}")->assertRedirect();

    expect(ProfessionalDashboardReminder::query()->find($reminder->id))->toBeNull();
});

it('blocks a professional from deleting a colleague\'s reminder', function () {
    [$user, $organization] = dashboardReminderSetup();
    $colleague = Professional::factory()->for($organization)->create(['status' => RecordStatus::Active]);
    $reminder = ProfessionalDashboardReminder::factory()->for($organization)->for($colleague)->create();

    $this->actingAs($user)->delete("/dashboard/lembretes/{$reminder->id}")->assertForbidden();

    expect(ProfessionalDashboardReminder::query()->find($reminder->id))->not->toBeNull();
});

it('lets a professional silence the alarm on their own reminder, keeping the reminder itself', function () {
    [$user, $organization, $professional] = dashboardReminderSetup();
    $reminder = ProfessionalDashboardReminder::factory()->for($organization)->for($professional)->create(['alarm_at' => now()]);

    $this->actingAs($user)->patch("/dashboard/lembretes/{$reminder->id}/silenciar-alarme")->assertRedirect();

    $reminder->refresh();
    expect($reminder->alarm_at)->toBeNull();
    expect(ProfessionalDashboardReminder::query()->find($reminder->id))->not->toBeNull();
});

it('blocks a professional from silencing a colleague\'s alarm', function () {
    [$user, $organization] = dashboardReminderSetup();
    $colleague = Professional::factory()->for($organization)->create(['status' => RecordStatus::Active]);
    $reminder = ProfessionalDashboardReminder::factory()->for($organization)->for($colleague)->create(['alarm_at' => now()]);

    $this->actingAs($user)->patch("/dashboard/lembretes/{$reminder->id}/silenciar-alarme")->assertForbidden();

    expect($reminder->fresh()->alarm_at)->not->toBeNull();
});
