<?php

declare(strict_types=1);

use App\Models\User;

it('runs the application under the pt_BR locale by default', function () {
    expect(app()->getLocale())->toBe('pt_BR');
});

it('translates validation messages using friendly, non-technical attribute names', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/onboarding/organization', []);

    $response->assertSessionHasErrors(['organization_name', 'document', 'legal_name', 'unit_name']);

    $errors = $response->getSession()->get('errors')->getBag('default');

    expect($errors->first('organization_name'))
        ->toContain('nome da clínica')
        ->not->toContain('organization_name')
        ->not->toContain('organization name');

    expect($errors->first('unit_name'))
        ->toContain('nome da unidade')
        ->not->toContain('unit_name');
});
