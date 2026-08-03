<?php

declare(strict_types=1);

use App\Enums\RecordStatus;
use App\Filament\Resources\Specialties\Pages\ListSpecialties;
use App\Models\Organization;
use App\Models\Professional;
use App\Models\ProfessionalSpecialty;
use App\Models\Specialty;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

it('blocks a non-platform-admin from accessing the specialties resource', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/admin/specialties')->assertForbidden();
});

it('lists specialties across organizations for the platform admin, identifying the clinic', function () {
    $admin = User::factory()->platformAdmin()->create();
    $organization = Organization::factory()->create(['name' => 'Clínica Alfa']);
    $specialty = Specialty::factory()->for($organization)->create();

    Livewire::actingAs($admin)
        ->test(ListSpecialties::class)
        ->assertCanSeeTableRecords([$specialty])
        ->assertTableColumnStateSet('organization.name', 'Clínica Alfa', $specialty);
});

it('activates a specialty through the domain action, never a raw update', function () {
    $admin = User::factory()->platformAdmin()->create();
    $specialty = Specialty::factory()->create(['status' => RecordStatus::Inactive]);

    Livewire::actingAs($admin)
        ->test(ListSpecialties::class)
        ->callAction(TestAction::make('activate')->table($specialty));

    expect($specialty->fresh()->status)->toBe(RecordStatus::Active);
});

it('blocks deleting a specialty that still has linked professionals, surfacing the domain error', function () {
    $admin = User::factory()->platformAdmin()->create();
    $organization = Organization::factory()->create();
    $specialty = Specialty::factory()->for($organization)->create();
    $professional = Professional::factory()->for($organization)->create();
    ProfessionalSpecialty::factory()->for($organization)->for($specialty)->for($professional)->create();

    Livewire::actingAs($admin)
        ->test(ListSpecialties::class)
        ->callAction(TestAction::make('delete')->table($specialty));

    expect($specialty->fresh()->trashed())->toBeFalse();
});

it('soft-deletes and restores a specialty through the domain actions', function () {
    $admin = User::factory()->platformAdmin()->create();
    $specialty = Specialty::factory()->create();

    Livewire::actingAs($admin)
        ->test(ListSpecialties::class)
        ->callAction(TestAction::make('delete')->table($specialty));

    expect($specialty->fresh()->trashed())->toBeTrue();

    Livewire::actingAs($admin)
        ->test(ListSpecialties::class)
        ->callAction(TestAction::make('restore')->table($specialty->fresh()));

    $specialty->refresh();
    expect($specialty->trashed())->toBeFalse()
        ->and($specialty->status)->toBe(RecordStatus::Inactive);
});
