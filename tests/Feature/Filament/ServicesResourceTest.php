<?php

declare(strict_types=1);

use App\Enums\RecordStatus;
use App\Filament\Resources\Services\Pages\EditService;
use App\Filament\Resources\Services\Pages\ListServices;
use App\Models\Organization;
use App\Models\Professional;
use App\Models\ProfessionalService;
use App\Models\Service;
use App\Models\Specialty;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

it('blocks a non-platform-admin from accessing the services resource', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/admin/services')->assertForbidden();
});

it('lists services across organizations for the platform admin, identifying the clinic', function () {
    $admin = User::factory()->platformAdmin()->create();
    $organization = Organization::factory()->create(['name' => 'Clínica Alfa']);
    $service = Service::factory()->for($organization)->create();

    Livewire::actingAs($admin)
        ->test(ListServices::class)
        ->assertCanSeeTableRecords([$service])
        ->assertTableColumnStateSet('organization.name', 'Clínica Alfa', $service);
});

it('activates a service through the domain action', function () {
    $admin = User::factory()->platformAdmin()->create();
    $service = Service::factory()->create(['status' => RecordStatus::Inactive]);

    Livewire::actingAs($admin)
        ->test(ListServices::class)
        ->callAction(TestAction::make('activate')->table($service));

    expect($service->fresh()->status)->toBe(RecordStatus::Active);
});

it('blocks deleting a service that still has linked professionals, surfacing the domain error', function () {
    $admin = User::factory()->platformAdmin()->create();
    $organization = Organization::factory()->create();
    $service = Service::factory()->for($organization)->create();
    $professional = Professional::factory()->for($organization)->create();
    ProfessionalService::factory()->for($organization)->for($service)->for($professional)->create();

    Livewire::actingAs($admin)
        ->test(ListServices::class)
        ->callAction(TestAction::make('delete')->table($service));

    expect($service->fresh()->trashed())->toBeFalse();
});

it('blocks saving a tampered specialty id from another organization through the edit form', function () {
    $admin = User::factory()->platformAdmin()->create();
    $organization = Organization::factory()->create();
    $service = Service::factory()->for($organization)->create();
    $foreignSpecialty = Specialty::factory()->for(Organization::factory()->create())->create();

    Livewire::actingAs($admin)
        ->test(EditService::class, ['record' => $service->getRouteKey()])
        ->fillForm([
            'name' => $service->name,
            'code' => $service->code,
            'default_duration_minutes' => $service->default_duration_minutes,
            'unit_availability_scope' => 'all_units',
            'specialty_ids' => [$foreignSpecialty->id],
        ])
        ->call('save')
        ->assertHasFormErrors();

    expect($service->specialtyLinks()->count())->toBe(0);
});

it('soft-deletes and restores a service through the domain actions', function () {
    $admin = User::factory()->platformAdmin()->create();
    $service = Service::factory()->create();

    Livewire::actingAs($admin)
        ->test(ListServices::class)
        ->callAction(TestAction::make('delete')->table($service));

    expect($service->fresh()->trashed())->toBeTrue();

    Livewire::actingAs($admin)
        ->test(ListServices::class)
        ->callAction(TestAction::make('restore')->table($service->fresh()));

    $service->refresh();
    expect($service->trashed())->toBeFalse()
        ->and($service->status)->toBe(RecordStatus::Inactive);
});
