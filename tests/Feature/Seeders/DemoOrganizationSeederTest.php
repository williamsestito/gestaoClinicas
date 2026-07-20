<?php

declare(strict_types=1);

use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DemoOrganizationSeeder;
use Illuminate\Support\Facades\Hash;

it('does nothing in production', function () {
    app()['env'] = 'production';

    // --force evita o prompt de confirmacao do proprio comando `db:seed`
    // em producao (nivel Artisan) — o que estamos testando aqui e a
    // guarda interna do seeder, nao esse prompt.
    $this->artisan('db:seed', ['--class' => DemoOrganizationSeeder::class, '--force' => true]);

    expect(Organization::query()->count())->toBe(0);

    app()['env'] = 'testing';
});

it('creates an organization with a headquarters and the system roles when none exists', function () {
    $this->seed(DemoOrganizationSeeder::class);

    $organization = Organization::query()->first();

    expect($organization)->not->toBeNull()
        ->and($organization->headquarters()->exists())->toBeTrue()
        ->and(Role::query()->where('organization_id', $organization->id)->count())->toBe(7);
});

it('fixes an existing clinic admin user that was incorrectly marked as platform admin, without touching their password', function () {
    config(['demo_environment.clinic_admin_email' => 'admin@gestao-clinicas.local']);
    $existingHash = Hash::make('senha-original-do-usuario');
    $user = User::factory()->create([
        'email' => 'admin@gestao-clinicas.local',
        'password' => $existingHash,
    ]);
    $user->forceFill(['is_platform_admin' => true])->save();

    $this->seed(DemoOrganizationSeeder::class);

    $fresh = $user->fresh();
    expect($fresh->is_platform_admin)->toBeFalse()
        ->and($fresh->password)->toBe($existingHash);

    $membership = OrganizationMembership::query()->where('user_id', $user->id)->first();
    expect($membership)->not->toBeNull()
        ->and($membership->is_owner)->toBeTrue();
});

it('grants an existing platform admin a non-owner membership so they can use the operational system', function () {
    $admin = User::factory()->create(['is_platform_admin' => true]);

    $this->seed(DemoOrganizationSeeder::class);

    $membership = OrganizationMembership::query()->where('user_id', $admin->id)->first();

    expect($membership)->not->toBeNull()
        ->and($membership->is_owner)->toBeFalse()
        ->and($admin->fresh()->is_platform_admin)->toBeTrue();
});

it('is idempotent — running it twice does not create duplicate organizations or memberships', function () {
    $this->seed(DemoOrganizationSeeder::class);
    $this->seed(DemoOrganizationSeeder::class);

    expect(Organization::query()->count())->toBe(1)
        ->and(OrganizationMembership::query()->count())->toBe(0);
});

it('never creates the clinic admin user without a configured password', function () {
    config(['demo_environment.clinic_admin_password' => null]);

    $this->seed(DemoOrganizationSeeder::class);

    expect(User::query()->where('email', config('demo_environment.clinic_admin_email'))->exists())->toBeFalse();
});

it('creates the clinic admin user when a password is configured and none exists yet', function () {
    config([
        'demo_environment.clinic_admin_email' => 'nova-clinica@example.com',
        'demo_environment.clinic_admin_password' => 'senha-segura-123',
    ]);

    $this->seed(DemoOrganizationSeeder::class);

    $user = User::query()->where('email', 'nova-clinica@example.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->is_platform_admin)->toBeFalse()
        ->and(Hash::check('senha-segura-123', $user->password))->toBeTrue();

    $membership = OrganizationMembership::query()->where('user_id', $user->id)->first();
    expect($membership->is_owner)->toBeTrue();
});
