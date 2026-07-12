<?php

declare(strict_types=1);

use App\Models\User;

it('connects to the test database and performs a real read/write operation', function () {
    expect(config('database.default'))->toBe('pgsql');

    $user = User::factory()->create(['email' => 'db-check@example.com']);

    expect(User::query()->where('email', 'db-check@example.com')->first()?->id)->toBe($user->id);
});
