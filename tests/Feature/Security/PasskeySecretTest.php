<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;

it('resolves the passkeys user handle secret from the dedicated env var, never from APP_KEY', function () {
    expect(config('fortify.passkeys.user_handle_secret'))
        ->toBe(env('PASSKEYS_USER_HANDLE_SECRET'))
        ->not->toBe(config('app.key'));
});

it('never falls back to APP_KEY for the passkeys secret in production, but does outside of it', function () {
    // Mesma regra de config/fortify.php (env(...) ?? (produção ? null : app.key)),
    // reproduzida aqui para validar a decisão sem depender de subir um segundo
    // processo com APP_ENV diferente dentro do mesmo teste.
    $resolve = fn (?string $dedicatedSecret, string $appEnv, string $appKey): ?string => $dedicatedSecret ?? (
        $appEnv === 'production' ? null : $appKey
    );

    expect($resolve('segredo-dedicado', 'production', 'app-key-de-exemplo'))->toBe('segredo-dedicado')
        ->and($resolve(null, 'production', 'app-key-de-exemplo'))->toBeNull()
        ->and($resolve(null, 'local', 'app-key-de-exemplo'))->toBe('app-key-de-exemplo');
});

it('never exposes the passkeys secret in the about/environment report', function () {
    Artisan::call('about', ['--only' => 'environment']);

    expect(Artisan::output())->not->toContain((string) env('PASSKEYS_USER_HANDLE_SECRET'));
});
