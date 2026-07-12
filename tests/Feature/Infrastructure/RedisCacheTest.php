<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Redis;

it('writes and reads through the Redis cache store', function () {
    $key = 'infrastructure-test:redis-cache';

    cache()->store('redis')->put($key, 'ok', 10);

    expect(cache()->store('redis')->get($key))->toBe('ok');

    cache()->store('redis')->forget($key);
})->skip(fn () => ! testRedisIsReachable(), 'Requer Redis acessível (ambiente Docker).');

function testRedisIsReachable(): bool
{
    try {
        Redis::connection()->ping();

        return true;
    } catch (Throwable) {
        return false;
    }
}
