<?php

declare(strict_types=1);

use App\Models\User;

it('creates a new platform admin interactively', function () {
    $this->artisan('app:create-platform-admin')
        ->expectsQuestion('Nome completo', 'Admin Teste')
        ->expectsQuestion('E-mail', 'admin@example.com')
        ->expectsQuestion('Senha (mínimo 12 caracteres, com letras, números e símbolos)', 'Sup3rSecure!1')
        ->expectsQuestion('Confirme a senha', 'Sup3rSecure!1')
        ->assertExitCode(0);

    $user = User::query()->where('email', 'admin@example.com')->firstOrFail();

    expect($user->is_active)->toBeTrue()
        ->and($user->is_platform_admin)->toBeTrue()
        ->and($user->email_verified_at)->not->toBeNull();
});

it('normalizes name and email (trim + lowercase) and matches existing users case-insensitively', function () {
    $existing = User::factory()->create(['email' => 'existing@example.com']);

    $this->artisan('app:create-platform-admin')
        ->expectsQuestion('Nome completo', '  Admin Teste  ')
        ->expectsQuestion('E-mail', '  Existing@Example.com  ')
        ->expectsConfirmation(
            'Já existe um usuário com o e-mail existing@example.com. Deseja promovê-lo a administrador da plataforma?',
            'yes',
        )
        ->expectsQuestion('Senha (mínimo 12 caracteres, com letras, números e símbolos)', 'Sup3rSecure!1')
        ->expectsQuestion('Confirme a senha', 'Sup3rSecure!1')
        ->assertExitCode(0);

    $existing->refresh();

    expect($existing->name)->toBe('Admin Teste')
        ->and($existing->email)->toBe('existing@example.com')
        ->and($existing->is_platform_admin)->toBeTrue();

    expect(User::query()->where('email', 'existing@example.com')->count())->toBe(1);
});

it('requires confirmation before promoting an existing user', function () {
    $existing = User::factory()->create(['email' => 'existing@example.com']);

    $this->artisan('app:create-platform-admin')
        ->expectsQuestion('Nome completo', $existing->name)
        ->expectsQuestion('E-mail', 'existing@example.com')
        ->expectsConfirmation(
            'Já existe um usuário com o e-mail existing@example.com. Deseja promovê-lo a administrador da plataforma?',
            'no',
        )
        ->assertExitCode(1);

    expect($existing->fresh()->is_platform_admin)->toBeFalse();
});
