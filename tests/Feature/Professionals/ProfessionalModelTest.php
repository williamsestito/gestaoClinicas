<?php

declare(strict_types=1);

use App\Enums\RecordStatus;
use App\Models\Organization;
use App\Models\Professional;
use App\Models\ProfessionalRegistration;
use App\Models\User;

it('belongs to an organization and casts status to RecordStatus', function () {
    $organization = Organization::factory()->create();
    $professional = Professional::factory()->for($organization)->create();

    expect($professional->organization)->toBeInstanceOf(Organization::class)
        ->and($professional->organization->is($organization))->toBeTrue()
        ->and($professional->status)->toBe(RecordStatus::Active);
});

it('can exist without any linked user', function () {
    $professional = Professional::factory()->create();

    expect($professional->user_id)->toBeNull()
        ->and($professional->user)->toBeNull();
});

it('can optionally be linked to a user without granting any access by itself', function () {
    $user = User::factory()->create();
    $professional = Professional::factory()->linkedToUser()->create(['user_id' => $user->id]);

    expect($professional->user)->toBeInstanceOf(User::class)
        ->and($professional->user->is($user))->toBeTrue()
        ->and($user->organizationMemberships()->count())->toBe(0);
});

it('is soft deleted, not physically removed, and can be restored', function () {
    $professional = Professional::factory()->create();
    $id = $professional->id;

    $professional->delete();

    expect(Professional::query()->find($id))->toBeNull()
        ->and(Professional::withTrashed()->find($id))->not->toBeNull()
        ->and(Professional::withTrashed()->find($id)->trashed())->toBeTrue();

    $professional->restore();

    expect(Professional::query()->find($id))->not->toBeNull()
        ->and(Professional::query()->find($id)->trashed())->toBeFalse();
});

it('exposes its primary registration via a dedicated relation', function () {
    $professional = Professional::factory()->create();
    ProfessionalRegistration::factory()->for($professional)->create([
        'organization_id' => $professional->organization_id,
        'is_primary' => false,
    ]);
    $primary = ProfessionalRegistration::factory()->primary()->for($professional)->create([
        'organization_id' => $professional->organization_id,
    ]);

    expect($professional->registrations()->count())->toBe(2)
        ->and($professional->primaryRegistration->is($primary))->toBeTrue();
});

it('is created with document unmasked in the database', function () {
    $professional = Professional::factory()->create(['document' => '52998224725']);

    expect($professional->fresh()->document)->toBe('52998224725');
});
