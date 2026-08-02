<?php

declare(strict_types=1);

use App\Enums\AuditAction;
use App\Models\Organization;
use App\Models\Professional;
use App\Models\ProfessionalRegistration;
use App\Models\User;
use App\Support\Auditing\AuditLogger;
use App\Support\Authorization\PermissionChecker;
use Database\Factories\LegalEntityFactory;

it('never grants organization membership when linking a professional to a user', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->create();

    Professional::factory()->for($organization)->linkedToUser()->create(['user_id' => $user->id]);

    expect($user->organizationMemberships()->count())->toBe(0);
});

it('never grants any permission when linking a professional to a user', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->create();

    Professional::factory()->for($organization)->linkedToUser()->create(['user_id' => $user->id]);

    $permissions = app(PermissionChecker::class)->effectivePermissions($user, $organization->id);

    expect($permissions)->toBe([]);
});

it('unlinking or deactivating a professional never deletes the linked user', function () {
    $user = User::factory()->create();
    $professional = Professional::factory()->linkedToUser()->create(['user_id' => $user->id]);

    $professional->update(['user_id' => null]);

    expect(User::query()->find($user->id))->not->toBeNull();
});

it('masks the professional document in the audit log', function () {
    $organization = Organization::factory()->create();
    $document = LegalEntityFactory::validCpf();

    $log = app(AuditLogger::class)->log(
        AuditAction::Created,
        organization: $organization,
        after: ['document' => $document, 'name' => 'Fulano da Silva'],
    );

    expect($log->after_data['document'])->not->toBe($document)
        ->and($log->after_data['document'])->toEndWith(substr($document, -2));
});

it('masks the professional registration number in the audit log', function () {
    $organization = Organization::factory()->create();

    $log = app(AuditLogger::class)->log(
        AuditAction::Created,
        organization: $organization,
        after: ['registration_number' => '123456', 'council' => 'CRM'],
    );

    expect($log->after_data['registration_number'])->not->toBe('123456')
        ->and($log->after_data['registration_number'])->toEndWith('56')
        ->and($log->after_data['council'])->toBe('CRM');
});

it('masks the registration number nested inside an aggregate audit payload', function () {
    $organization = Organization::factory()->create();

    $log = app(AuditLogger::class)->log(
        AuditAction::Created,
        organization: $organization,
        after: [
            'professional' => [
                'name' => 'Fulano da Silva',
                'registrations' => [
                    ['council' => 'CRM', 'registration_number' => '654321'],
                ],
            ],
        ],
    );

    expect($log->after_data['professional']['registrations'][0]['registration_number'])->not->toBe('654321')
        ->and($log->after_data['professional']['registrations'][0]['registration_number'])->toEndWith('21');
});

it('hides soft deleted professionals, specialties, services and registrations by default', function () {
    $professional = Professional::factory()->create();
    $registration = ProfessionalRegistration::factory()->for($professional)->create([
        'organization_id' => $professional->organization_id,
    ]);

    $professional->delete();
    $registration->delete();

    expect(Professional::all())->toHaveCount(0)
        ->and(ProfessionalRegistration::all())->toHaveCount(0)
        ->and(Professional::withTrashed()->count())->toBe(1)
        ->and(ProfessionalRegistration::withTrashed()->count())->toBe(1);
});
