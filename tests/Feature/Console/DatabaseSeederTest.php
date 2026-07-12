<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Artisan;

it('does not create any default/insecure user', function () {
    Artisan::call('db:seed', ['--force' => true]);

    expect(User::query()->where('email', 'test@example.com')->exists())->toBeFalse();
    expect(User::query()->count())->toBe(0);
});
