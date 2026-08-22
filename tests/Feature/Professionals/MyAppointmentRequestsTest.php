<?php

declare(strict_types=1);

use App\Actions\Organization\SeedSystemRolesAction;
use App\Enums\AppointmentStatus;
use App\Enums\OrganizationMembershipStatus;
use App\Enums\RecordStatus;
use App\Enums\SystemRole;
use App\Models\Appointment;
use App\Models\AppointmentRequest;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Professional;
use App\Models\Role;
use App\Models\User;

/** @return array{0: User, 1: Organization, 2: Professional} */
function myAppointmentRequestsSetup(): array
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

it('lists only appointment requests linked to the logged-in professional', function () {
    [$user, $organization, $professional] = myAppointmentRequestsSetup();
    AppointmentRequest::factory()->for($organization)->for($professional)->create(['name' => 'Meu Lead']);
    AppointmentRequest::factory()->for($organization)->create(['name' => 'Lead de Outro', 'professional_id' => null]);

    $response = $this->actingAs($user)->get('/settings/meus-pre-agendamentos');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('requests.total', 1)
            ->where('requests.data.0.name', 'Meu Lead'));
});

it('shows the status of the linked real appointment, kept live from the same record', function () {
    [$user, $organization, $professional] = myAppointmentRequestsSetup();
    $appointment = Appointment::factory()->for($organization)->for($professional)->create(['status' => AppointmentStatus::Confirmed]);
    $request = AppointmentRequest::factory()->for($organization)->for($professional)->create(['appointment_id' => $appointment->id]);

    $response = $this->actingAs($user)->get('/settings/meus-pre-agendamentos');

    $response->assertOk()->assertInertia(fn ($page) => $page
        ->where('requests.data.0.id', $request->id)
        ->where('requests.data.0.appointment_status', 'confirmed')
        ->where('requests.data.0.appointment_status_label', 'Confirmado'));
});

it('leaves the appointment status null for a lead that was never converted', function () {
    [$user, $organization, $professional] = myAppointmentRequestsSetup();
    AppointmentRequest::factory()->for($organization)->for($professional)->create(['appointment_id' => null]);

    $response = $this->actingAs($user)->get('/settings/meus-pre-agendamentos');

    $response->assertOk()->assertInertia(fn ($page) => $page
        ->where('requests.data.0.appointment_status', null)
        ->where('requests.data.0.appointment_status_label', null));
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

    $response = $this->actingAs($user)->get('/settings/meus-pre-agendamentos');

    $response->assertOk()->assertInertia(fn ($page) => $page->where('requests', null));
});

it('lets the professional update the status of their own appointment request', function () {
    [$user, $organization, $professional] = myAppointmentRequestsSetup();
    $request = AppointmentRequest::factory()->for($organization)->for($professional)->create();

    $this->actingAs($user)->patch("/settings/meus-pre-agendamentos/{$request->id}/status", [
        'status' => 'contacted',
    ])->assertRedirect();

    expect($request->fresh()->status->value)->toBe('contacted');
});

it('lets the professional save an internal note on their own appointment request', function () {
    [$user, $organization, $professional] = myAppointmentRequestsSetup();
    $request = AppointmentRequest::factory()->for($organization)->for($professional)->create();

    $this->actingAs($user)->patch("/settings/meus-pre-agendamentos/{$request->id}/notes", [
        'internal_notes' => 'Liguei e confirmei.',
    ])->assertRedirect();

    expect($request->fresh()->internal_notes)->toBe('Liguei e confirmei.');
});

it('blocks a professional from updating a colleague\'s appointment request', function () {
    [$user, $organization] = myAppointmentRequestsSetup();
    $colleague = Professional::factory()->for($organization)->create(['status' => RecordStatus::Active]);
    $request = AppointmentRequest::factory()->for($organization)->for($colleague)->create();

    $this->actingAs($user)->patch("/settings/meus-pre-agendamentos/{$request->id}/status", [
        'status' => 'contacted',
    ])->assertForbidden();

    expect($request->fresh()->status->value)->not->toBe('contacted');
});

it('blocks updating an appointment request with no professional linked at all', function () {
    [$user, $organization] = myAppointmentRequestsSetup();
    $request = AppointmentRequest::factory()->for($organization)->create(['professional_id' => null]);

    $this->actingAs($user)->patch("/settings/meus-pre-agendamentos/{$request->id}/status", [
        'status' => 'contacted',
    ])->assertForbidden();
});

it('blocks updating an appointment request from another organization even when the same user has an active professional record there', function () {
    // Um usuário pode ter Professional ativo em mais de uma organização
    // (user_id é único só por organização, não globalmente) — o vínculo
    // com o profissional sozinho não pode bastar, a organização ativa
    // precisa bater com a da solicitação (ver
    // UpdateOwnAppointmentRequestStatusRequest::authorize()).
    [$user, $organization, $professional] = myAppointmentRequestsSetup();

    $otherOrganization = Organization::factory()->create();
    app(SeedSystemRolesAction::class)->handle($otherOrganization);
    $otherRole = Role::query()->where('organization_id', $otherOrganization->id)->where('slug', SystemRole::Professional->value)->firstOrFail();
    $otherProfessional = Professional::factory()->for($otherOrganization)->create(['user_id' => $user->id, 'status' => RecordStatus::Active]);
    OrganizationMembership::factory()->for($otherOrganization)->for($user)->create([
        'status' => OrganizationMembershipStatus::Active,
        'role_id' => $otherRole->id,
    ]);
    $foreignRequest = AppointmentRequest::factory()->for($otherOrganization)->for($otherProfessional)->create();

    // Sessão ainda ativa na organização original ($organization), não na
    // $otherOrganization onde a solicitação realmente vive.
    $this->actingAs($user)->patch("/settings/meus-pre-agendamentos/{$foreignRequest->id}/status", [
        'status' => 'contacted',
    ])->assertForbidden();

    expect($foreignRequest->fresh()->status->value)->not->toBe('contacted');
});
