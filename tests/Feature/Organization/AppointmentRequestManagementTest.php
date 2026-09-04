<?php

declare(strict_types=1);

use App\Enums\RecordStatus;
use App\Enums\SystemRole;
use App\Models\Appointment;
use App\Models\AppointmentRequest;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Professional;
use App\Models\Role;
use App\Models\User;

it('lets the owner view appointment requests scoped to their organization', function () {
    $user = actingOwnerWithActiveContext();
    $organization = Organization::find(session('active_organization_id'));

    AppointmentRequest::factory()->for($organization)->create(['name' => 'Da minha clínica']);
    AppointmentRequest::factory()->for(Organization::factory()->create())->create(['name' => 'De outra clínica']);

    $this->actingAs($user)->get('/settings/site/appointment-requests')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/site/appointment-requests/Index')
            ->where('requests.data.0.name', 'Da minha clínica')
            ->has('requests.data', 1));
});

it('lets the owner update the status of a request', function () {
    $user = actingOwnerWithActiveContext();
    $organization = Organization::find(session('active_organization_id'));
    $request = AppointmentRequest::factory()->for($organization)->create();

    $this->actingAs($user)->patch("/settings/site/appointment-requests/{$request->id}/status", [
        'status' => 'contacted',
    ])->assertRedirect();

    expect($request->fresh()->status->value)->toBe('contacted');
});

it('blocks changing the status once the lead has already been converted into a real appointment', function () {
    // Achado real: o select permitia trocar o status de volta para
    // "Contato realizado"/"Cancelado" mesmo depois da conversão, deixando
    // `appointment_id` preenchido mas o status desalinhado — o registro
    // sumia do filtro "Agendado" e uma nova tentativa de confirmar batia no
    // bloqueio de "já foi confirmado por outro usuário", sem ninguém ter
    // feito isso de fato.
    $user = actingOwnerWithActiveContext();
    $organization = Organization::find(session('active_organization_id'));
    $request = AppointmentRequest::factory()->for($organization)->create([
        'status' => 'scheduled',
        'appointment_id' => Appointment::factory()->for($organization)->create()->id,
    ]);

    $this->actingAs($user)->patch("/settings/site/appointment-requests/{$request->id}/status", [
        'status' => 'contacted',
    ])->assertSessionHasErrors('status');

    expect($request->fresh()->status->value)->toBe('scheduled');
});

it('rejects an invalid status value', function () {
    $user = actingOwnerWithActiveContext();
    $organization = Organization::find(session('active_organization_id'));
    $request = AppointmentRequest::factory()->for($organization)->create();

    $this->actingAs($user)->patch("/settings/site/appointment-requests/{$request->id}/status", [
        'status' => 'not-a-real-status',
    ])->assertSessionHasErrors('status');
});

it('lets the owner save an internal note without exposing it publicly', function () {
    $user = actingOwnerWithActiveContext();
    $organization = Organization::find(session('active_organization_id'));
    $request = AppointmentRequest::factory()->for($organization)->create();

    $this->actingAs($user)->patch("/settings/site/appointment-requests/{$request->id}/notes", [
        'internal_notes' => 'Já ligamos duas vezes, sem retorno.',
    ])->assertRedirect();

    expect($request->fresh()->internal_notes)->toBe('Já ligamos duas vezes, sem retorno.');
});

it('lets the owner reassign the professional of a request', function () {
    $user = actingOwnerWithActiveContext();
    $organization = Organization::find(session('active_organization_id'));
    $request = AppointmentRequest::factory()->for($organization)->create();
    $newProfessional = Professional::factory()->for($organization)->create(['status' => RecordStatus::Active]);

    $this->actingAs($user)->patch("/settings/site/appointment-requests/{$request->id}/professional", [
        'professional_id' => $newProfessional->id,
    ])->assertRedirect();

    expect($request->fresh()->professional_id)->toBe($newProfessional->id);
});

it('lets the owner clear the professional of a request, leaving it unassigned', function () {
    $user = actingOwnerWithActiveContext();
    $organization = Organization::find(session('active_organization_id'));
    $professional = Professional::factory()->for($organization)->create();
    $request = AppointmentRequest::factory()->for($organization)->create(['professional_id' => $professional->id]);

    $this->actingAs($user)->patch("/settings/site/appointment-requests/{$request->id}/professional", [
        'professional_id' => null,
    ])->assertRedirect();

    expect($request->fresh()->professional_id)->toBeNull();
});

it('rejects reassigning to a professional that was logically deleted — exactly the scenario that needs a manual fix', function () {
    $user = actingOwnerWithActiveContext();
    $organization = Organization::find(session('active_organization_id'));
    $deletedProfessional = Professional::factory()->for($organization)->create();
    $deletedProfessional->delete();
    $request = AppointmentRequest::factory()->for($organization)->create();

    $this->actingAs($user)->patch("/settings/site/appointment-requests/{$request->id}/professional", [
        'professional_id' => $deletedProfessional->id,
    ])->assertSessionHasErrors('professional_id');

    expect($request->fresh()->professional_id)->toBeNull();
});

it('rejects reassigning to a professional from another organization', function () {
    $user = actingOwnerWithActiveContext();
    $organization = Organization::find(session('active_organization_id'));
    $foreignProfessional = Professional::factory()->for(Organization::factory()->create())->create();
    $request = AppointmentRequest::factory()->for($organization)->create();

    $this->actingAs($user)->patch("/settings/site/appointment-requests/{$request->id}/professional", [
        'professional_id' => $foreignProfessional->id,
    ])->assertSessionHasErrors('professional_id');
});

it('blocks reassigning the professional once the lead has already been converted into a real appointment', function () {
    $user = actingOwnerWithActiveContext();
    $organization = Organization::find(session('active_organization_id'));
    $originalProfessional = Professional::factory()->for($organization)->create();
    $newProfessional = Professional::factory()->for($organization)->create();
    $request = AppointmentRequest::factory()->for($organization)->create([
        'professional_id' => $originalProfessional->id,
        'appointment_id' => Appointment::factory()->for($organization)->create()->id,
    ]);

    $this->actingAs($user)->patch("/settings/site/appointment-requests/{$request->id}/professional", [
        'professional_id' => $newProfessional->id,
    ])->assertSessionHasErrors('professional_id');

    expect($request->fresh()->professional_id)->toBe($originalProfessional->id);
});

it('blocks a non-owner without site.appointments.manage from reassigning the professional', function () {
    actingOwnerWithActiveContext();
    $organization = Organization::find(session('active_organization_id'));
    $request = AppointmentRequest::factory()->for($organization)->create();
    $member = User::factory()->create();
    OrganizationMembership::factory()->for($organization)->for($member)->create();

    $this->actingAs($member)->patch("/settings/site/appointment-requests/{$request->id}/professional", [
        'professional_id' => null,
    ])->assertForbidden();
});

it('blocks reassigning the professional of an appointment request that belongs to another organization', function () {
    $user = actingOwnerWithActiveContext();
    $professional = Professional::factory()->for($professionalOrganization = Organization::factory()->create())->create();
    $foreignRequest = AppointmentRequest::factory()->for($professionalOrganization)->create(['professional_id' => $professional->id]);

    $this->actingAs($user)->patch("/settings/site/appointment-requests/{$foreignRequest->id}/professional", [
        'professional_id' => null,
    ])->assertNotFound();

    expect($foreignRequest->fresh()->professional_id)->toBe($professional->id);
});

it('flags professional_removed so the front-end can offer reassignment when the requested professional no longer exists', function () {
    $user = actingOwnerWithActiveContext();
    $organization = Organization::find(session('active_organization_id'));
    $deletedProfessional = Professional::factory()->for($organization)->create(['display_name' => 'Dra Removida']);
    $deletedProfessional->delete();
    AppointmentRequest::factory()->for($organization)->create(['professional_id' => $deletedProfessional->id]);

    $this->actingAs($user)->get('/settings/site/appointment-requests')
        ->assertInertia(fn ($page) => $page
            ->where('requests.data.0.professional_name', 'Dra Removida')
            ->where('requests.data.0.professional_removed', true));
});

it('filters appointment requests by status', function () {
    $user = actingOwnerWithActiveContext();
    $organization = Organization::find(session('active_organization_id'));
    AppointmentRequest::factory()->for($organization)->create(['name' => 'Pendente']);
    AppointmentRequest::factory()->contacted()->for($organization)->create(['name' => 'Contatado']);

    $this->actingAs($user)->get('/settings/site/appointment-requests?status=contacted')
        ->assertInertia(fn ($page) => $page
            ->has('requests.data', 1)
            ->where('requests.data.0.name', 'Contatado'));
});

it('searches appointment requests by name or phone', function () {
    $user = actingOwnerWithActiveContext();
    $organization = Organization::find(session('active_organization_id'));
    AppointmentRequest::factory()->for($organization)->create(['name' => 'Ana Souza', 'phone' => '(47) 91111-1111']);
    AppointmentRequest::factory()->for($organization)->create(['name' => 'Bruno Lima', 'phone' => '(47) 92222-2222']);

    $this->actingAs($user)->get('/settings/site/appointment-requests?search=Ana')
        ->assertInertia(fn ($page) => $page->has('requests.data', 1)->where('requests.data.0.name', 'Ana Souza'));

    $this->actingAs($user)->get('/settings/site/appointment-requests?search=92222')
        ->assertInertia(fn ($page) => $page->has('requests.data', 1)->where('requests.data.0.name', 'Bruno Lima'));
});

it('blocks a non-owner without site.appointments.view from listing requests', function () {
    actingOwnerWithActiveContext();
    $organization = Organization::find(session('active_organization_id'));
    $member = User::factory()->create();
    OrganizationMembership::factory()->for($organization)->for($member)->create();

    $this->actingAs($member)->get('/settings/site/appointment-requests')->assertForbidden();
});

it('blocks a non-owner without site.appointments.manage from updating notes', function () {
    actingOwnerWithActiveContext();
    $organization = Organization::find(session('active_organization_id'));
    $request = AppointmentRequest::factory()->for($organization)->create();
    $member = User::factory()->create();
    OrganizationMembership::factory()->for($organization)->for($member)->create();

    $this->actingAs($member)->patch("/settings/site/appointment-requests/{$request->id}/notes", [
        'internal_notes' => 'Não deveria funcionar',
    ])->assertForbidden();
});

it('blocks updating the status of an appointment request that belongs to another organization', function () {
    $user = actingOwnerWithActiveContext();
    $foreignRequest = AppointmentRequest::factory()->for(Organization::factory()->create())->create();

    $this->actingAs($user)->patch("/settings/site/appointment-requests/{$foreignRequest->id}/status", [
        'status' => 'contacted',
    ])->assertNotFound();

    expect($foreignRequest->fresh()->status->value)->toBe('pending');
});

it('blocks updating the internal notes of an appointment request that belongs to another organization', function () {
    $user = actingOwnerWithActiveContext();
    $foreignRequest = AppointmentRequest::factory()->for(Organization::factory()->create())->create(['internal_notes' => null]);

    $this->actingAs($user)->patch("/settings/site/appointment-requests/{$foreignRequest->id}/notes", [
        'internal_notes' => 'Não deveria conseguir gravar isso',
    ])->assertNotFound();

    expect($foreignRequest->fresh()->internal_notes)->toBeNull();
});

it('lists the organization\'s active professionals for the filter and narrows the listing when one is selected, so admin/atendimento can separate leads by professional', function () {
    $user = actingOwnerWithActiveContext();
    $organization = Organization::find(session('active_organization_id'));
    $professionalA = Professional::factory()->for($organization)->create(['status' => RecordStatus::Active, 'display_name' => 'Dra Juliana Cruz']);
    $professionalB = Professional::factory()->for($organization)->create(['status' => RecordStatus::Active, 'display_name' => 'Dr João Paiva']);
    AppointmentRequest::factory()->for($organization)->create(['name' => 'Lead A', 'professional_id' => $professionalA->id]);
    AppointmentRequest::factory()->for($organization)->create(['name' => 'Lead B', 'professional_id' => $professionalB->id]);

    $this->actingAs($user)->get('/settings/site/appointment-requests')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('professionals.0.display_name', 'Dr João Paiva')
            ->where('professionals.1.display_name', 'Dra Juliana Cruz')
            ->has('requests.data', 2));

    $this->actingAs($user)->get('/settings/site/appointment-requests?professional_id='.$professionalA->id)
        ->assertInertia(fn ($page) => $page
            ->has('requests.data', 1)
            ->where('requests.data.0.name', 'Lead A'));
});

it('exposes which professional a lead was requested for, and the structured fields needed to confirm it in one click', function () {
    $user = actingOwnerWithActiveContext();
    $organization = Organization::find(session('active_organization_id'));
    $professional = Professional::factory()->for($organization)->create(['display_name' => 'Dra Juliana Cruz']);
    AppointmentRequest::factory()->for($organization)->create([
        'professional_id' => $professional->id,
        'unit_id' => $organization->units()->first()->id,
    ]);

    $this->actingAs($user)->get('/settings/site/appointment-requests')
        ->assertInertia(fn ($page) => $page
            ->where('requests.data.0.professional_id', $professional->id)
            ->where('requests.data.0.professional_name', 'Dra Juliana Cruz')
            ->where('requests.data.0.professional_removed', false));
});

it('hides cancelled requests from the default listing, but shows them when explicitly filtered', function () {
    $user = actingOwnerWithActiveContext();
    $organization = Organization::find(session('active_organization_id'));
    AppointmentRequest::factory()->for($organization)->create(['name' => 'Pendente']);
    AppointmentRequest::factory()->for($organization)->create(['name' => 'Cancelado', 'status' => 'cancelled']);

    $this->actingAs($user)->get('/settings/site/appointment-requests')
        ->assertInertia(fn ($page) => $page
            ->has('requests.data', 1)
            ->where('requests.data.0.name', 'Pendente'));

    $this->actingAs($user)->get('/settings/site/appointment-requests?status=cancelled')
        ->assertInertia(fn ($page) => $page
            ->has('requests.data', 1)
            ->where('requests.data.0.name', 'Cancelado'));
});

it('grants the reception role access to manage appointment requests', function () {
    actingOwnerWithActiveContext();
    $organization = Organization::find(session('active_organization_id'));
    seedSystemRoles($organization);
    $role = Role::query()->where('organization_id', $organization->id)->where('slug', SystemRole::Reception->value)->firstOrFail();

    $member = User::factory()->create();
    OrganizationMembership::factory()->for($organization)->for($member)->create(['role_id' => $role->id]);
    $request = AppointmentRequest::factory()->for($organization)->create();

    $this->actingAs($member)->get('/settings/site/appointment-requests')->assertOk();
    $this->actingAs($member)->patch("/settings/site/appointment-requests/{$request->id}/status", [
        'status' => 'scheduled',
    ])->assertRedirect();
});
