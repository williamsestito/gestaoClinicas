<?php

declare(strict_types=1);

use App\Enums\AppointmentRequestStatus;
use App\Models\AppointmentRequest;
use App\Models\LegalEntity;
use App\Models\Organization;
use App\Models\SiteService;
use App\Models\Unit;
use Illuminate\Routing\Middleware\ThrottleRequests;

// O rate limit real (throttle:5,1) é aplicado na rota — desabilitado aqui
// porque o estado do limiter usa o cache Redis do ambiente de teste, que
// não é resetado por RefreshDatabase e vaza entre execuções da suíte.
beforeEach(fn () => $this->withoutMiddleware(ThrottleRequests::class));

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
    ])->assertSessionHasErrors('terms_accepted');

    expect(AppointmentRequest::query()->count())->toBe(0);
});

it('requires name and phone', function () {
    $this->post('/agendamento', [
        'terms_accepted' => true,
    ])->assertSessionHasErrors(['name', 'phone']);
});

it('rejects a service_id that does not exist', function () {
    $service = SiteService::factory()->create();

    $this->post('/agendamento', [
        'service_id' => $service->id + 1000,
        'name' => 'Paciente Teste',
        'phone' => '(47) 99999-0000',
        'terms_accepted' => true,
    ])->assertSessionHasErrors('service_id');
});
