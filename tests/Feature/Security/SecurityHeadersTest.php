<?php

declare(strict_types=1);

it('applies baseline security headers on a public route', function () {
    $response = $this->get('/');

    $response->assertHeader('X-Content-Type-Options', 'nosniff');
    $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    $response->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');
});

it('applies baseline security headers on an authenticated route', function () {
    $user = actingOwnerWithActiveContext();

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertHeader('X-Content-Type-Options', 'nosniff');
    $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
});

it('never sends HSTS outside of production, even over a "secure" test request', function () {
    $response = $this->get('/', ['HTTPS' => 'on']);

    $response->assertHeaderMissing('Strict-Transport-Security');
});

it('never sends the report-only CSP outside of production', function () {
    $response = $this->get('/');

    $response->assertHeaderMissing('Content-Security-Policy-Report-Only');
});
