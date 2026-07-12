<?php

declare(strict_types=1);

use App\Models\User;

it('redirects guests trying to access the admin panel', function () {
    $this->get('/admin')->assertRedirect('/admin/login');
});

it('denies access to a regular verified user', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/admin')->assertForbidden();
});

it('denies access to an inactive platform admin', function () {
    $user = User::factory()->platformAdmin()->inactive()->create();

    $this->actingAs($user)->get('/admin')->assertForbidden();
});

it('denies access to an unverified platform admin', function () {
    $user = User::factory()->platformAdmin()->unverified()->create();

    $this->actingAs($user)->get('/admin')->assertForbidden();
});

it('allows access to an active, verified platform admin', function () {
    $user = User::factory()->platformAdmin()->create();

    $this->actingAs($user)->get('/admin')->assertOk();
});
