<?php

declare(strict_types=1);

use App\Models\AppointmentRequest;
use App\Models\Organization;
use App\Models\Patient;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Carbon;

// O rate limit real (throttle:patient-register) usa o cache Redis do
// ambiente de teste — mesmo padrão de PatientPortalRegistrationTest.
beforeEach(fn () => $this->withoutMiddleware(ThrottleRequests::class));

it('links an existing orphan appointment request by document when staff registers the matching patient', function () {
    $user = actingOwnerWithActiveContext();
    $organization = $user->organizationMemberships()->first()->organization;

    $request = AppointmentRequest::factory()->for($organization)->create([
        'document' => '52998224725',
        'phone' => '(11) 90000-0000',
    ]);

    $this->actingAs($user)->post('/settings/patients', [
        'name' => 'Teste Uchoa',
        'birth_date' => '1990-05-10',
        'document' => '52998224725',
        'phone' => '(11) 91111-1111',
        'emergency_contacts' => [
            ['name' => 'Contato', 'relationship' => 'cônjuge', 'phone_primary' => '11999990000'],
        ],
    ])->assertRedirect('/settings/patients');

    $patient = Patient::query()->where('document', '52998224725')->firstOrFail();
    expect($request->fresh()->patient_id)->toBe($patient->id);
});

it('never links an appointment request by phone/email alone — only an exact document match', function () {
    $user = actingOwnerWithActiveContext();
    $organization = $user->organizationMemberships()->first()->organization;

    // Mesmo telefone e e-mail de duas pessoas diferentes — achado real em
    // dados de teste (telefone reaproveitado por leads não relacionados).
    // Sem CPF batendo, o vínculo automático nunca deve acontecer.
    $request = AppointmentRequest::factory()->for($organization)->create([
        'name' => 'Outra Pessoa Qualquer',
        'document' => null,
        'phone' => '(11) 92222-2222',
        'email' => 'compartilhado@example.com',
    ]);

    $this->actingAs($user)->post('/settings/patients', [
        'name' => 'Paciente Sem CPF No Lead',
        'birth_date' => '1990-05-10',
        'phone' => '(11) 92222-2222',
        'email' => 'compartilhado@example.com',
        'emergency_contacts' => [
            ['name' => 'Contato', 'relationship' => 'cônjuge', 'phone_primary' => '11999990000'],
        ],
    ])->assertRedirect('/settings/patients');

    expect($request->fresh()->patient_id)->toBeNull();
});

it('never links an appointment request when the new patient has no document at all', function () {
    $user = actingOwnerWithActiveContext();
    $organization = $user->organizationMemberships()->first()->organization;

    $request = AppointmentRequest::factory()->for($organization)->create([
        'document' => null,
        'phone' => '(11) 96666-6666',
    ]);

    $this->actingAs($user)->post('/settings/patients', [
        'name' => 'Paciente Sem Documento',
        'birth_date' => '1990-05-10',
        'phone' => '(11) 96666-6666',
        'emergency_contacts' => [
            ['name' => 'Contato', 'relationship' => 'cônjuge', 'phone_primary' => '11999990000'],
        ],
    ])->assertRedirect('/settings/patients');

    expect($request->fresh()->patient_id)->toBeNull();
});

it('never links an appointment request from another organization, even with the same document', function () {
    $user = actingOwnerWithActiveContext();
    $otherOrganization = Organization::factory()->create();

    $request = AppointmentRequest::factory()->for($otherOrganization)->create([
        'document' => '52998224725',
        'phone' => '(11) 93333-3333',
    ]);

    $this->actingAs($user)->post('/settings/patients', [
        'name' => 'Paciente Da Organizacao Certa',
        'birth_date' => '1990-05-10',
        'document' => '52998224725',
        'emergency_contacts' => [
            ['name' => 'Contato', 'relationship' => 'cônjuge', 'phone_primary' => '11999990000'],
        ],
    ])->assertRedirect('/settings/patients');

    expect($request->fresh()->patient_id)->toBeNull();
});

it('never matches an appointment request by name alone', function () {
    $user = actingOwnerWithActiveContext();
    $organization = $user->organizationMemberships()->first()->organization;

    $request = AppointmentRequest::factory()->for($organization)->create([
        'name' => 'Homônimo Da Silva',
        'document' => null,
        'phone' => '(11) 94444-4444',
        'email' => 'homonimo-lead@example.com',
    ]);

    $this->actingAs($user)->post('/settings/patients', [
        'name' => 'Homônimo Da Silva',
        'birth_date' => '1990-05-10',
        'phone' => '(11) 95555-5555',
        'emergency_contacts' => [
            ['name' => 'Contato', 'relationship' => 'cônjuge', 'phone_primary' => '11999990000'],
        ],
    ])->assertRedirect('/settings/patients');

    expect($request->fresh()->patient_id)->toBeNull();
});

it('links an existing orphan appointment request when a patient self-registers on the portal with matching data', function () {
    $organization = Organization::factory()->create();

    $request = AppointmentRequest::factory()->for($organization)->create([
        'name' => 'testeuchoa',
        'document' => '52998224725',
        'phone' => '(55) 47996-9615',
    ]);

    $this->post('/portal/registrar', [
        'name' => 'testeuchoa',
        'email' => 'uchoateste@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'registering_for' => 'self',
        'birth_date' => Carbon::now()->subYears(30)->toDateString(),
        'document' => '52998224725',
        'phone' => '(55) 47996-9615',
    ])->assertRedirect('/portal');

    $patient = Patient::query()->where('document', '52998224725')->firstOrFail();
    expect($request->fresh()->patient_id)->toBe($patient->id);
});
