<?php

declare(strict_types=1);

use App\Models\Organization;
use App\Models\Patient;
use App\Models\PatientEmergencyContact;
use App\Models\PatientResponsible;
use App\Models\PatientUser;
use App\Models\PatientUserLink;
use Database\Factories\LegalEntityFactory;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

// O rate limit real (throttle:patient-register) usa o cache Redis do
// ambiente de teste, que não é resetado por RefreshDatabase e vaza entre
// execuções da suíte — mesmo padrão de AppointmentRequestSubmissionTest.
beforeEach(fn () => $this->withoutMiddleware(ThrottleRequests::class));

function baseRegistrationPayload(): array
{
    return [
        'name' => 'Maria Souza',
        'email' => 'maria@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'registering_for' => 'self',
        'birth_date' => Carbon::now()->subYears(30)->toDateString(),
    ];
}

it('renders the registration page with organizationConfigured true when an organization exists', function () {
    Organization::factory()->create();

    $this->get('/portal/registrar')->assertOk();
});

it('prefills the form from query string data sent by the public scheduling form', function () {
    Organization::factory()->create();

    $response = $this->get('/portal/registrar?'.http_build_query([
        'name' => 'Maria Souza',
        'phone' => '(47) 99999-0000',
        'email' => 'maria@example.com',
        'document' => '529.982.247-25',
    ]));

    $response->assertOk()->assertInertia(fn ($page) => $page
        ->where('prefill.name', 'Maria Souza')
        ->where('prefill.phone', '(47) 99999-0000')
        ->where('prefill.email', 'maria@example.com')
        ->where('prefill.document', '52998224725'));
});

it('leaves prefill fields null when no query string data is sent', function () {
    Organization::factory()->create();

    $response = $this->get('/portal/registrar');

    $response->assertOk()->assertInertia(fn ($page) => $page
        ->where('prefill.name', null)
        ->where('prefill.phone', null)
        ->where('prefill.email', null)
        ->where('prefill.document', null));
});

it('accepts an optional photo on self-registration, stored on the private disk', function () {
    Storage::fake('local');
    Organization::factory()->create();
    $payload = baseRegistrationPayload();
    $payload['photo'] = UploadedFile::fake()->image('foto.jpg', 200, 200);

    $this->post('/portal/registrar', $payload)->assertRedirect('/portal');

    $patientUser = PatientUser::query()->where('email', 'maria@example.com')->firstOrFail();
    $link = PatientUserLink::query()->where('patient_user_id', $patientUser->id)->firstOrFail();
    $patient = Patient::query()->findOrFail($link->patient_id);

    expect($patient->photo_path)->not->toBeNull();
    Storage::disk('local')->assertExists($patient->photo_path);
});

it('registers a self patient user with a linked patient and no forced emergency contact', function () {
    $organization = Organization::factory()->create();

    $response = $this->post('/portal/registrar', baseRegistrationPayload());

    $response->assertRedirect('/portal');

    $patientUser = PatientUser::query()->where('email', 'maria@example.com')->firstOrFail();
    expect($patientUser->organization_id)->toBe($organization->id);

    $link = PatientUserLink::query()->where('patient_user_id', $patientUser->id)->firstOrFail();
    expect($link->role->value)->toBe('self');

    $patient = Patient::query()->findOrFail($link->patient_id);
    expect($patient->name)->toBe('Maria Souza')
        ->and($patient->origin)->toBe('patient-portal')
        ->and(PatientEmergencyContact::query()->where('patient_id', $patient->id)->count())->toBe(0)
        ->and(PatientResponsible::query()->where('patient_id', $patient->id)->count())->toBe(0);

    expect(Auth::guard('patient')->check())->toBeTrue();
});

it('blocks a minor from self-registering as the account holder', function () {
    Organization::factory()->create();

    $payload = baseRegistrationPayload();
    $payload['birth_date'] = Carbon::now()->subYears(10)->toDateString();

    $this->post('/portal/registrar', $payload)->assertSessionHasErrors('birth_date');

    expect(PatientUser::query()->count())->toBe(0);
});

it('registers a dependent, auto-creating an emergency contact and, for a minor, a legal guardian responsible', function () {
    Organization::factory()->create();

    $payload = [
        'name' => 'João Pai',
        'email' => 'joao@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'registering_for' => 'dependent',
        'dependent_name' => 'Joãozinho',
        'dependent_birth_date' => Carbon::now()->subYears(8)->toDateString(),
        'relationship' => 'Pai',
        'responsible_phone' => '(47) 99696-1511',
    ];

    $this->post('/portal/registrar', $payload)->assertRedirect('/portal');

    $patientUser = PatientUser::query()->where('email', 'joao@example.com')->firstOrFail();

    expect(PatientUserLink::query()->where('patient_user_id', $patientUser->id)->where('role', 'self')->count())->toBe(0);

    $link = PatientUserLink::query()->where('patient_user_id', $patientUser->id)->where('role', 'dependent')->firstOrFail();
    $patient = Patient::query()->findOrFail($link->patient_id);

    expect($patient->name)->toBe('Joãozinho')
        ->and($patient->isMinor())->toBeTrue();

    $contact = PatientEmergencyContact::query()->where('patient_id', $patient->id)->firstOrFail();
    expect($contact->name)->toBe('João Pai')
        ->and($contact->phone_primary)->toBe('(47) 99696-1511');

    $responsible = PatientResponsible::query()->where('patient_id', $patient->id)->firstOrFail();
    expect($responsible->is_legal_guardian)->toBeTrue()
        ->and($responsible->name)->toBe('João Pai');
});

it('registers an adult dependent without forcing a legal guardian responsible', function () {
    Organization::factory()->create();

    $payload = [
        'name' => 'Carla Filha',
        'email' => 'carla@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'registering_for' => 'dependent',
        'dependent_name' => 'Avó Idosa',
        'dependent_birth_date' => Carbon::now()->subYears(80)->toDateString(),
        'relationship' => 'Filha',
        'responsible_phone' => '(47) 99696-1511',
    ];

    $this->post('/portal/registrar', $payload)->assertRedirect('/portal');

    $patientUser = PatientUser::query()->where('email', 'carla@example.com')->firstOrFail();
    $link = PatientUserLink::query()->where('patient_user_id', $patientUser->id)->firstOrFail();
    $patient = Patient::query()->findOrFail($link->patient_id);

    expect($patient->isMinor())->toBeFalse()
        ->and(PatientResponsible::query()->where('patient_id', $patient->id)->count())->toBe(0)
        ->and(PatientEmergencyContact::query()->where('patient_id', $patient->id)->count())->toBe(1);
});

it('rejects a duplicate patient user email', function () {
    Organization::factory()->create();
    PatientUser::factory()->create(['email' => 'maria@example.com']);

    $this->post('/portal/registrar', baseRegistrationPayload())
        ->assertSessionHasErrors('email');
});

it('rejects a document colliding with an existing patient without confirming the collision', function () {
    $organization = Organization::factory()->create();
    $cpf = LegalEntityFactory::validCpf();
    $existing = Patient::factory()->for($organization)->create(['document' => $cpf]);

    $payload = baseRegistrationPayload();
    $payload['document'] = $cpf;

    $response = $this->post('/portal/registrar', $payload);

    $response->assertSessionHasErrors('document');
    expect(session('errors')->get('document')[0])->not->toContain('Já existe um paciente');
    expect(PatientUser::query()->count())->toBe(0);
    expect(Patient::query()->count())->toBe(1);
    expect($existing->fresh())->not->toBeNull();
});

it('silently ignores a honeypot-triggered submission without creating anything', function () {
    Organization::factory()->create();

    $payload = baseRegistrationPayload();
    $payload['website'] = 'https://spam.example.com';

    $this->post('/portal/registrar', $payload)->assertRedirect('/login');

    expect(PatientUser::query()->count())->toBe(0);
});
