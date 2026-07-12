<?php

declare(strict_types=1);

use App\Jobs\InfrastructureSmokeJob;
use Illuminate\Support\Facades\Cache;

it('writes a cache entry when processed', function () {
    $key = 'infrastructure-smoke:test';

    (new InfrastructureSmokeJob($key))->handle();

    expect(Cache::has($key))->toBeTrue();

    Cache::forget($key);
});

it('times out when no queue worker processes the job', function () {
    $this->artisan('app:test-queue', ['--timeout' => 1])->assertExitCode(1);
});
