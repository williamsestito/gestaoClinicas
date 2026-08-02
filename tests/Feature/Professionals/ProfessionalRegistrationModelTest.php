<?php

declare(strict_types=1);

use App\Models\Professional;
use App\Models\ProfessionalRegistration;
use App\Support\Documents\BrazilianState;
use Illuminate\Database\QueryException;

it('belongs to a professional and casts state/status', function () {
    $professional = Professional::factory()->create();
    $registration = ProfessionalRegistration::factory()->for($professional)->create([
        'organization_id' => $professional->organization_id,
        'state' => 'SP',
    ]);

    expect($registration->professional->is($professional))->toBeTrue()
        ->and($registration->state)->toBe(BrazilianState::SP);
});

it('allows more than one registration per professional', function () {
    $professional = Professional::factory()->create();

    ProfessionalRegistration::factory()->count(2)->for($professional)->create([
        'organization_id' => $professional->organization_id,
    ]);

    expect($professional->registrations()->count())->toBe(2);
});

it('allows at most one primary active registration per professional', function () {
    $professional = Professional::factory()->create();
    ProfessionalRegistration::factory()->primary()->for($professional)->create([
        'organization_id' => $professional->organization_id,
    ]);

    expect(fn () => ProfessionalRegistration::factory()->primary()->for($professional)->create([
        'organization_id' => $professional->organization_id,
    ]))->toThrow(QueryException::class);
});

it('allows a new primary registration after the previous one is soft deleted', function () {
    $professional = Professional::factory()->create();
    $first = ProfessionalRegistration::factory()->primary()->for($professional)->create([
        'organization_id' => $professional->organization_id,
    ]);
    $first->delete();

    $second = ProfessionalRegistration::factory()->primary()->for($professional)->create([
        'organization_id' => $professional->organization_id,
    ]);

    expect($second->exists)->toBeTrue();
});

it('does not allow the same council/number/state pair twice in the same organization', function () {
    $professional = Professional::factory()->create();
    ProfessionalRegistration::factory()->for($professional)->create([
        'organization_id' => $professional->organization_id,
        'council' => 'CRM',
        'registration_number' => '111111',
        'state' => 'SP',
    ]);

    expect(fn () => ProfessionalRegistration::factory()->for($professional)->create([
        'organization_id' => $professional->organization_id,
        'council' => 'CRM',
        'registration_number' => '111111',
        'state' => 'SP',
    ]))->toThrow(QueryException::class);
});

it('rejects an expiration date before the issue date', function () {
    $professional = Professional::factory()->create();

    expect(fn () => ProfessionalRegistration::factory()->for($professional)->create([
        'organization_id' => $professional->organization_id,
        'issued_at' => '2026-06-01',
        'expires_at' => '2026-01-01',
    ]))->toThrow(QueryException::class);
});

it('is soft deleted and preserves its history', function () {
    $registration = ProfessionalRegistration::factory()->create();
    $registration->delete();

    expect(ProfessionalRegistration::query()->find($registration->id))->toBeNull()
        ->and(ProfessionalRegistration::withTrashed()->find($registration->id))->not->toBeNull();
});
