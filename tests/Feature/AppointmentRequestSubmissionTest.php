<?php

declare(strict_types=1);

use App\Enums\AppointmentRequestStatus;
use App\Models\AppointmentRequest;
use App\Models\LegalEntity;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\SiteService;
use App\Models\Unit;
use App\Models\User;
use App\Notifications\NewAppointmentRequestNotification;
use Illuminate\Contracts\Notifications\Dispatcher;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Notification as NotificationFacade;

// O rate limit real (throttle:5,1) é aplicado na rota — desabilitado aqui
// porque o estado do limiter usa o cache Redis do ambiente de teste, que
// não é resetado por RefreshDatabase e vaza entre execuções da suíte.
beforeEach(fn () => $this->withoutMiddleware(ThrottleRequests::class));

function renderedAtMs(int $millisecondsAgo = 5000): int
{
    return (int) (microtime(true) * 1000) - $millisecondsAgo;
}

it('creates a pending appointment request lead from the public form', function () {
    $organization = Organization::factory()->create();
    $legalEntity = LegalEntity::factory()->primary()->for($organization)->create();
    $headquarters = Unit::factory()->headquarters()->for($organization)->for($legalEntity, 'legalEntity')->create();
    $service = SiteService::factory()->create();

    $this->post('/agendamento', [
        'service_id' => $service->id,
        'name' => 'Paciente Teste',
        'phone' => '(47) 99999-0000',
        'email' => 'paciente@example.com',
        'preferred_period' => 'Manhã',
        'terms_accepted' => true,
        'form_rendered_at' => renderedAtMs(),
    ])->assertRedirect();

    $request = AppointmentRequest::query()->where('name', 'Paciente Teste')->firstOrFail();
    expect($request->status)->toBe(AppointmentRequestStatus::Pending)
        ->and($request->organization_id)->toBe($organization->id)
        ->and($request->unit_id)->toBe($headquarters->id)
        ->and($request->service_id)->toBe($service->id)
        ->and($request->terms_accepted_at)->not->toBeNull();
});

it('requires accepting the terms', function () {
    $this->post('/agendamento', [
        'name' => 'Paciente Teste',
        'phone' => '(47) 99999-0000',
        'form_rendered_at' => renderedAtMs(),
    ])->assertSessionHasErrors('terms_accepted');

    expect(AppointmentRequest::query()->count())->toBe(0);
});

it('requires name and phone', function () {
    $this->post('/agendamento', [
        'terms_accepted' => true,
        'form_rendered_at' => renderedAtMs(),
    ])->assertSessionHasErrors(['name', 'phone']);
});

it('rejects a service_id that does not exist', function () {
    $service = SiteService::factory()->create();

    $this->post('/agendamento', [
        'service_id' => $service->id + 1000,
        'name' => 'Paciente Teste',
        'phone' => '(47) 99999-0000',
        'terms_accepted' => true,
        'form_rendered_at' => renderedAtMs(),
    ])->assertSessionHasErrors('service_id');
});

it('rejects an inactive service_id', function () {
    $service = SiteService::factory()->create(['is_active' => false]);

    $this->post('/agendamento', [
        'service_id' => $service->id,
        'name' => 'Paciente Teste',
        'phone' => '(47) 99999-0000',
        'terms_accepted' => true,
        'form_rendered_at' => renderedAtMs(),
    ])->assertSessionHasErrors('service_id');

    expect(AppointmentRequest::query()->count())->toBe(0);
});

it('normalizes phone numbers into a consistent local format', function (string $input, string $expected) {
    $this->post('/agendamento', [
        'name' => 'Paciente Teste',
        'phone' => $input,
        'terms_accepted' => true,
        'form_rendered_at' => renderedAtMs(),
    ])->assertSessionHasNoErrors();

    expect(AppointmentRequest::query()->where('name', 'Paciente Teste')->firstOrFail()->phone)->toBe($expected);
})->with([
    ['(47) 99999-0000', '(47) 99999-0000'],
    ['47999990000', '(47) 99999-0000'],
    ['+55 47 99999-0000', '(47) 99999-0000'],
    ['55 47 9999-0000', '(47) 9999-0000'],
]);

it('rejects a phone number with an invalid digit count', function (string $phone) {
    $this->post('/agendamento', [
        'name' => 'Paciente Teste',
        'phone' => $phone,
        'terms_accepted' => true,
        'form_rendered_at' => renderedAtMs(),
    ])->assertSessionHasErrors('phone');
})->with([
    '123',
    '123456789012345',
]);

it('rejects a preferred_date in the past', function () {
    $this->post('/agendamento', [
        'name' => 'Paciente Teste',
        'phone' => '(47) 99999-0000',
        'preferred_date' => now()->subDay()->toDateString(),
        'terms_accepted' => true,
        'form_rendered_at' => renderedAtMs(),
    ])->assertSessionHasErrors('preferred_date');
});

it('rejects a preferred_date too far in the future', function () {
    $this->post('/agendamento', [
        'name' => 'Paciente Teste',
        'phone' => '(47) 99999-0000',
        'preferred_date' => now()->addDays(200)->toDateString(),
        'terms_accepted' => true,
        'form_rendered_at' => renderedAtMs(),
    ])->assertSessionHasErrors('preferred_date');
});

it('accepts a valid preferred_date within the allowed window', function () {
    $this->post('/agendamento', [
        'name' => 'Paciente Teste',
        'phone' => '(47) 99999-0000',
        'preferred_date' => now()->addDays(5)->toDateString(),
        'terms_accepted' => true,
        'form_rendered_at' => renderedAtMs(),
    ])->assertSessionHasNoErrors();

    expect(AppointmentRequest::query()->where('name', 'Paciente Teste')->firstOrFail()->preferred_date->toDateString())
        ->toBe(now()->addDays(5)->toDateString());
});

it('rejects an arbitrary preferred_period value', function () {
    $this->post('/agendamento', [
        'name' => 'Paciente Teste',
        'phone' => '(47) 99999-0000',
        'preferred_period' => 'Madrugada às 3h',
        'terms_accepted' => true,
        'form_rendered_at' => renderedAtMs(),
    ])->assertSessionHasErrors('preferred_period');
});

it('rejects a message over the maximum length', function () {
    $this->post('/agendamento', [
        'name' => 'Paciente Teste',
        'phone' => '(47) 99999-0000',
        'notes' => str_repeat('a', 1001),
        'terms_accepted' => true,
        'form_rendered_at' => renderedAtMs(),
    ])->assertSessionHasErrors('notes');
});

it('silently accepts without persisting when the honeypot field is filled', function () {
    $this->post('/agendamento', [
        'name' => 'Bot Teste',
        'phone' => '(47) 99999-0000',
        'terms_accepted' => true,
        'website' => 'https://spam.example.com',
        'form_rendered_at' => renderedAtMs(),
    ])->assertRedirect();

    expect(AppointmentRequest::query()->count())->toBe(0);
});

it('silently accepts without persisting when the submission is faster than a human fill time', function () {
    $this->post('/agendamento', [
        'name' => 'Bot Rápido',
        'phone' => '(47) 99999-0000',
        'terms_accepted' => true,
        'form_rendered_at' => renderedAtMs(500),
    ])->assertRedirect();

    expect(AppointmentRequest::query()->count())->toBe(0);
});

it('reuses a recent identical submission instead of creating a duplicate', function () {
    $payload = [
        'name' => 'Paciente Duplicado',
        'phone' => '(47) 99999-0000',
        'preferred_period' => 'Manhã',
        'terms_accepted' => true,
        'form_rendered_at' => renderedAtMs(),
    ];

    $this->post('/agendamento', $payload)->assertRedirect();
    $this->post('/agendamento', $payload)->assertRedirect();

    expect(AppointmentRequest::query()->where('name', 'Paciente Duplicado')->count())->toBe(1);
});

it('does not treat the same phone with a different service as a duplicate', function () {
    $serviceA = SiteService::factory()->create();
    $serviceB = SiteService::factory()->create();

    $this->post('/agendamento', [
        'service_id' => $serviceA->id,
        'name' => 'Paciente Teste',
        'phone' => '(47) 99999-0000',
        'terms_accepted' => true,
        'form_rendered_at' => renderedAtMs(),
    ])->assertRedirect();

    $this->post('/agendamento', [
        'service_id' => $serviceB->id,
        'name' => 'Paciente Teste',
        'phone' => '(47) 99999-0000',
        'terms_accepted' => true,
        'form_rendered_at' => renderedAtMs(),
    ])->assertRedirect();

    expect(AppointmentRequest::query()->where('phone', '(47) 99999-0000')->count())->toBe(2);
});

it('creates a new request once the duplicate window has passed', function () {
    $existing = AppointmentRequest::factory()->create([
        'phone' => '(47) 99999-0000',
        'service_id' => null,
        'preferred_date' => null,
        'preferred_period' => null,
        'created_at' => now()->subMinutes(30),
    ]);

    $this->post('/agendamento', [
        'name' => 'Paciente Teste',
        'phone' => '(47) 99999-0000',
        'terms_accepted' => true,
        'form_rendered_at' => renderedAtMs(),
    ])->assertRedirect();

    expect(AppointmentRequest::query()->where('phone', '(47) 99999-0000')->count())->toBe(2)
        ->and(AppointmentRequest::query()->find($existing->id))->not->toBeNull();
});

it('persists utm and origin parameters', function () {
    $this->post('/agendamento', [
        'name' => 'Paciente Teste',
        'phone' => '(47) 99999-0000',
        'terms_accepted' => true,
        'form_rendered_at' => renderedAtMs(),
        'utm' => [
            'utm_source' => 'google',
            'utm_medium' => 'cpc',
            'page_url' => 'https://example.test/?utm_source=google',
        ],
    ])->assertSessionHasNoErrors();

    $request = AppointmentRequest::query()->where('name', 'Paciente Teste')->firstOrFail();
    expect($request->utm_data)->toBe([
        'utm_source' => 'google',
        'utm_medium' => 'cpc',
        'page_url' => 'https://example.test/?utm_source=google',
    ]);
});

it('notifies the organization owner about a new appointment request', function () {
    NotificationFacade::fake();

    $organization = Organization::factory()->create();
    $owner = User::factory()->create();
    OrganizationMembership::factory()->owner()->for($organization)->for($owner)->create();

    $this->post('/agendamento', [
        'name' => 'Paciente Teste',
        'phone' => '(47) 99999-0000',
        'terms_accepted' => true,
        'form_rendered_at' => renderedAtMs(),
    ])->assertRedirect();

    NotificationFacade::assertSentTo($owner, NewAppointmentRequestNotification::class);
});

it('keeps the appointment request even when notifying the owner fails', function () {
    $organization = Organization::factory()->create();
    $owner = User::factory()->create();
    OrganizationMembership::factory()->owner()->for($organization)->for($owner)->create();

    $this->app->bind(Dispatcher::class, fn () => new class implements Dispatcher
    {
        public function send($notifiables, $notification): void
        {
            throw new RuntimeException('Simulated mail failure.');
        }

        public function sendNow($notifiables, $notification, ?array $channels = null): void
        {
            throw new RuntimeException('Simulated mail failure.');
        }
    });

    $this->post('/agendamento', [
        'name' => 'Paciente Teste',
        'phone' => '(47) 99999-0000',
        'terms_accepted' => true,
        'form_rendered_at' => renderedAtMs(),
    ])->assertRedirect();

    expect(AppointmentRequest::query()->where('name', 'Paciente Teste')->exists())->toBeTrue();
});
