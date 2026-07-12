<?php

declare(strict_types=1);

it('has the queue configured to use Redis', function () {
    expect(config('queue.default'))->toBe('redis')
        ->and(config('queue.connections.redis.driver'))->toBe('redis');
});
