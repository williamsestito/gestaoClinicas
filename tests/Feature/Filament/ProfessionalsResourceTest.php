<?php

declare(strict_types=1);

use App\Enums\RecordStatus;
use App\Filament\Resources\Professionals\Pages\ListProfessionals;
use App\Models\Organization;
use App\Models\Professional;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

it('blocks a non-platform-admin from accessing the professionals resource', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/admin/professionals')->assertForbidden();
});

it('lists professionals across organizations for the platform admin, identifying the clinic', function () {
    $admin = User::factory()->platformAdmin()->create();
    $organization = Organization::factory()->create(['name' => 'Clínica Alfa']);
    $professional = Professional::factory()->for($organization)->create();

    Livewire::actingAs($admin)
        ->test(ListProfessionals::class)
        ->assertCanSeeTableRecords([$professional])
        ->assertTableColumnStateSet('organization.name', 'Clínica Alfa', $professional);
});

it('masks the professional document in the table', function () {
    $admin = User::factory()->platformAdmin()->create();
    $professional = Professional::factory()->create(['document' => '52998224725']);

    Livewire::actingAs($admin)
        ->test(ListProfessionals::class)
        ->assertTableColumnFormattedStateSet('document', '***.***.***-25', $professional);
});

it('never shows the raw document on the professional view page', function () {
    $admin = User::factory()->platformAdmin()->create();
    $professional = Professional::factory()->create(['document' => '52998224725']);

    $this->actingAs($admin)
        ->get("/admin/professionals/{$professional->getRouteKey()}")
        ->assertOk()
        ->assertSee('***.***.***-25')
        ->assertDontSee('52998224725');
});

it('activates a professional through the domain action', function () {
    $admin = User::factory()->platformAdmin()->create();
    $professional = Professional::factory()->create(['status' => RecordStatus::Inactive]);

    Livewire::actingAs($admin)
        ->test(ListProfessionals::class)
        ->callAction(TestAction::make('activate')->table($professional));

    expect($professional->fresh()->status)->toBe(RecordStatus::Active);
});

it('soft-deletes and restores a professional through the domain actions, never affecting the linked user', function () {
    $admin = User::factory()->platformAdmin()->create();
    $linkedUser = User::factory()->create();
    $professional = Professional::factory()->create(['user_id' => $linkedUser->id]);

    Livewire::actingAs($admin)
        ->test(ListProfessionals::class)
        ->callAction(TestAction::make('delete')->table($professional));

    expect($professional->fresh()->trashed())->toBeTrue();
    expect($linkedUser->fresh())->not->toBeNull();

    Livewire::actingAs($admin)
        ->test(ListProfessionals::class)
        ->callAction(TestAction::make('restore')->table($professional->fresh()));

    $professional->refresh();
    expect($professional->trashed())->toBeFalse()
        ->and($professional->status)->toBe(RecordStatus::Inactive);
});
