<?php

declare(strict_types=1);

use App\Actions\Organization\ChangeOrganizationStatusAction;
use App\Actions\Organization\CreateLegalEntityAction;
use App\Actions\Organization\UpdateOrganizationAction;
use App\Data\Organization\AddressData;
use App\Enums\AuditAction;
use App\Enums\LegalEntityType;
use App\Enums\OrganizationMembershipStatus;
use App\Enums\OrganizationStatus;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use Database\Factories\LegalEntityFactory;

it('logs organization updates', function () {
    $organization = Organization::factory()->create();

    app(UpdateOrganizationAction::class)->handle($organization, ['name' => 'Novo Nome']);

    expect(AuditLog::query()
        ->where('organization_id', $organization->id)
        ->where('action', AuditAction::Updated)
        ->exists(),
    )->toBeTrue();
});

it('logs organization status changes as activated/deactivated', function () {
    $organization = Organization::factory()->create(['status' => OrganizationStatus::Active]);

    app(ChangeOrganizationStatusAction::class)->handle($organization, OrganizationStatus::Suspended);

    expect(AuditLog::query()
        ->where('organization_id', $organization->id)
        ->where('action', AuditAction::Deactivated)
        ->exists(),
    )->toBeTrue();
});

it('logs the organization context switch', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->create();
    OrganizationMembership::factory()
        ->owner()
        ->for($organization)
        ->for($user)
        ->create(['status' => OrganizationMembershipStatus::Active]);

    $this->actingAs($user)->put('/context/organization', ['organization_id' => $organization->id]);

    expect(AuditLog::query()
        ->where('organization_id', $organization->id)
        ->where('action', AuditAction::OrganizationContextSwitched)
        ->exists(),
    )->toBeTrue();
});

it('masks the document in the audit log after_data', function () {
    $organization = Organization::factory()->create();
    $document = LegalEntityFactory::validCpf();

    app(CreateLegalEntityAction::class)->handle(
        organization: $organization,
        type: LegalEntityType::Individual,
        document: $document,
        legalName: 'Maria da Silva',
        tradeName: null,
        address: AddressData::fromArray([
            'postal_code' => '01310100',
            'street' => 'Av. Paulista',
            'number' => '1000',
            'neighborhood' => 'Bela Vista',
            'city' => 'São Paulo',
            'state' => 'SP',
        ]),
        isPrimary: true,
    );

    $log = AuditLog::query()->where('organization_id', $organization->id)->where('action', AuditAction::Created)->latest('created_at')->firstOrFail();

    expect($log->after_data['document'])->not->toBe($document)
        ->and($log->after_data['document'])->toEndWith(substr($document, -2))
        ->and($log->after_data['document'])->not->toContain(substr($document, 0, 5));
});

it('never allows an audit log to be updated or deleted', function () {
    $log = AuditLog::factory()->create();

    expect(fn () => $log->update(['action' => AuditAction::Updated]))
        ->toThrow(LogicException::class);

    expect(fn () => $log->delete())
        ->toThrow(LogicException::class);
});
