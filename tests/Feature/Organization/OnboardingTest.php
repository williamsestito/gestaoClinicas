<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\LegalEntity;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Unit;
use App\Models\UnitMembership;
use App\Models\User;
use Database\Factories\LegalEntityFactory;

function onboardingPayload(array $overrides = []): array
{
    return array_merge([
        'organization_name' => 'Clínica Boa Saúde',
        'legal_entity_type' => 'individual',
        'document' => LegalEntityFactory::validCpf(),
        'legal_name' => 'Maria da Silva',
        'trade_name' => null,
        'unit_name' => 'Unidade Centro',
        'unit_phone' => '11999990000',
        'unit_whatsapp' => null,
        'address' => [
            'postal_code' => '01310-100',
            'street' => 'Av. Paulista',
            'number' => '1000',
            'complement' => null,
            'neighborhood' => 'Bela Vista',
            'city' => 'São Paulo',
            'state' => 'SP',
        ],
        'opening_hours' => [
            ['day_of_week' => 1, 'opens_at' => '08:00', 'closes_at' => '18:00'],
            ['day_of_week' => 2, 'opens_at' => '08:00', 'closes_at' => '18:00'],
        ],
    ], $overrides);
}

it('completes the full onboarding transaction and sets the active context', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/onboarding/organization', onboardingPayload());

    $response->assertRedirect(route('dashboard'));

    $organization = Organization::query()->where('name', 'Clínica Boa Saúde')->firstOrFail();
    expect($organization->slug)->not->toBeEmpty();

    $legalEntity = LegalEntity::query()->where('organization_id', $organization->id)->firstOrFail();
    expect($legalEntity->is_primary)->toBeTrue()
        ->and($legalEntity->legal_name)->toBe('Maria da Silva');

    $unit = Unit::query()->where('organization_id', $organization->id)->firstOrFail();
    expect($unit->is_headquarters)->toBeTrue()
        ->and($unit->name)->toBe('Unidade Centro');

    expect($unit->address)->not->toBeNull()
        ->and($unit->address->city)->toBe('São Paulo');

    expect($unit->openingHours()->count())->toBe(2);

    $membership = OrganizationMembership::query()
        ->where('organization_id', $organization->id)
        ->where('user_id', $user->id)
        ->firstOrFail();
    expect($membership->is_owner)->toBeTrue();

    $unitMembership = UnitMembership::query()
        ->where('organization_membership_id', $membership->id)
        ->where('unit_id', $unit->id)
        ->first();
    expect($unitMembership)->not->toBeNull();

    expect(session('active_organization_id'))->toBe($organization->id)
        ->and(session('active_unit_id'))->toBe($unit->id);

    expect(AuditLog::query()->where('organization_id', $organization->id)->count())->toBeGreaterThan(0);
});

it('rolls back everything when onboarding fails partway through', function () {
    $user = User::factory()->create();

    // CPF inválido faz a validação do Form Request falhar antes de qualquer
    // escrita — nada deve ser persistido.
    $payload = onboardingPayload(['document' => '00000000000']);

    $response = $this->actingAs($user)->post('/onboarding/organization', $payload);

    $response->assertSessionHasErrors('document');

    expect(Organization::query()->count())->toBe(0)
        ->and(LegalEntity::query()->count())->toBe(0)
        ->and(Unit::query()->count())->toBe(0)
        ->and(OrganizationMembership::query()->count())->toBe(0);
});

it('rolls back the whole transaction when the database rejects a duplicate document', function () {
    $existingDocument = LegalEntityFactory::validCpf();
    LegalEntity::factory()->create(['document' => $existingDocument]);

    $user = User::factory()->create();

    // O documento é numericamente válido (passa no Form Request) mas já
    // está em uso por outra organização — só a constraint UNIQUE do banco
    // barra isso, no meio da transação de onboarding.
    $payload = onboardingPayload(['document' => $existingDocument]);

    $organizationsBefore = Organization::query()->count();

    $this->withoutExceptionHandling();
    $threw = false;

    try {
        $this->actingAs($user)->post('/onboarding/organization', $payload);
    } catch (Throwable) {
        $threw = true;
    }

    expect($threw)->toBeTrue();
    expect(Organization::query()->count())->toBe($organizationsBefore);
    expect(Organization::query()->where('name', 'Clínica Boa Saúde')->exists())->toBeFalse();
});

it('redirects a user without an organization to onboarding when visiting the dashboard', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/dashboard')->assertRedirect(route('onboarding.organization.create'));
});
