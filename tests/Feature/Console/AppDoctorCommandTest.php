<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;

function infrastructureIsReachable(): bool
{
    try {
        DB::connection()->getPdo();

        return true;
    } catch (Throwable) {
        return false;
    }
}

it('runs the infrastructure diagnostic and reports success when the environment is healthy', function () {
    $this->artisan('app:doctor')->assertExitCode(0);
})->skip(fn () => ! infrastructureIsReachable(), 'Requer PostgreSQL/Redis acessíveis (ambiente Docker).');
