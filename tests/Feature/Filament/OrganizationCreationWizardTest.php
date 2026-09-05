<?php

declare(strict_types=1);

use App\Filament\Resources\Organizations\Pages\CreateOrganization;
use App\Models\LegalEntity;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

it('lets a platform admin create a real production organization through the wizard, inviting the administrator by e-mail', function () {
    Notification::fake();
    $admin = User::factory()->platformAdmin()->create();

    Livewire::actingAs($admin)
        ->test(CreateOrganization::class)
        ->fillForm([
            'organization_name' => 'Clínica Aurora',
            'legal_entity_type' => 'company',
            'document' => '11222333000181',
            'legal_name' => 'Clínica Aurora Ltda.',
            'trade_name' => 'Clínica Aurora',
            'unit_name' => 'Matriz',
            'address' => [
                'postal_code' => '01310100',
                'street' => 'Av. Paulista',
                'number' => '1000',
                'neighborhood' => 'Bela Vista',
                'city' => 'São Paulo',
                'state' => 'SP',
            ],
            'owner_mode' => 'invite',
            'invite_name' => 'Ana Souza',
            'invite_email' => 'ana@example.com',
        ])
        ->call('create')
        ->assertNotified()
        ->assertRedirect();

    $organization = Organization::query()->where('name', 'Clínica Aurora')->firstOrFail();
    expect(LegalEntity::query()->where('organization_id', $organization->id)->exists())->toBeTrue()
        ->and(Unit::query()->where('organization_id', $organization->id)->where('is_headquarters', true)->exists())->toBeTrue()
        ->and(Role::query()->where('organization_id', $organization->id)->count())->toBe(7);
});

it('rejects a non-platform-admin from reaching the organization creation page at all', function () {
    $organizationOwner = User::factory()->create();

    $this->actingAs($organizationOwner)
        ->get('/admin/organizations/create')
        ->assertForbidden();
});

it('never grants is_platform_admin to the administrator vinculado to the created organization', function () {
    $admin = User::factory()->platformAdmin()->create();
    $existingUser = User::factory()->create();

    Livewire::actingAs($admin)
        ->test(CreateOrganization::class)
        ->fillForm([
            'organization_name' => 'Clínica Boreal',
            'legal_entity_type' => 'individual',
            'document' => '52998224725',
            'legal_name' => 'Clínica Boreal',
            'unit_name' => 'Matriz',
            'address' => [
                'postal_code' => '01310100',
                'street' => 'Av. Paulista',
                'number' => '1000',
                'neighborhood' => 'Bela Vista',
                'city' => 'São Paulo',
                'state' => 'SP',
            ],
            'owner_mode' => 'existing',
            'existing_owner_user_id' => $existingUser->id,
        ])
        ->call('create')
        ->assertNotified()
        ->assertRedirect();

    $organization = Organization::query()->where('name', 'Clínica Boreal')->firstOrFail();
    $membership = OrganizationMembership::query()
        ->where('organization_id', $organization->id)
        ->where('user_id', $existingUser->id)
        ->firstOrFail();

    expect($membership->is_owner)->toBeTrue();
    expect($existingUser->fresh()->is_platform_admin)->toBeFalse();
});
