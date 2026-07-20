<?php

declare(strict_types=1);

use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\SiteService;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('lets the owner create a service, converts the price to cents and stores the image', function () {
    Storage::fake('public');
    $user = actingOwnerWithActiveContext();

    $this->actingAs($user)->post('/settings/site/services', [
        'name' => 'Limpeza de pele',
        'short_description' => 'Limpeza profunda',
        'starting_price' => 120.50,
        'duration_minutes' => 60,
        'image' => UploadedFile::fake()->image('servico.jpg'),
    ])->assertRedirect();

    $service = SiteService::query()->where('name', 'Limpeza de pele')->firstOrFail();
    expect($service->starting_price_cents)->toBe(12050)
        ->and($service->image_path)->not->toBeNull();
    Storage::disk('public')->assertExists($service->image_path);
});

it('replaces the previous image and deletes the old file when updating', function () {
    Storage::fake('public');
    $user = actingOwnerWithActiveContext();
    $service = SiteService::factory()->create(['image_path' => 'site-services/old.jpg']);
    Storage::disk('public')->put('site-services/old.jpg', 'fake-content');

    $this->actingAs($user)->put("/settings/site/services/{$service->id}", [
        'name' => $service->name,
        'image' => UploadedFile::fake()->image('novo.jpg'),
    ])->assertRedirect();

    $service->refresh();
    Storage::disk('public')->assertMissing('site-services/old.jpg');
    Storage::disk('public')->assertExists($service->image_path);
});

it('rejects a negative starting price', function () {
    $user = actingOwnerWithActiveContext();

    $this->actingAs($user)->post('/settings/site/services', [
        'name' => 'Serviço inválido',
        'starting_price' => -10,
    ])->assertSessionHasErrors('starting_price');
});

it('blocks a non-owner without site.update from managing services', function () {
    actingOwnerWithActiveContext();
    $organization = Organization::find(session('active_organization_id'));
    $member = User::factory()->create();
    OrganizationMembership::factory()->for($organization)->for($member)->create();

    $this->actingAs($member)->post('/settings/site/services', [
        'name' => 'Não autorizado',
    ])->assertForbidden();
});
