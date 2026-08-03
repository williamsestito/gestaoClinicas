<?php

declare(strict_types=1);

use App\Enums\AuditAction;
use App\Enums\RecordStatus;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Service;
use App\Models\SiteService;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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

it('rejects a service image format other than JPEG, PNG and WebP', function () {
    Storage::fake('public');
    $user = actingOwnerWithActiveContext();

    $this->actingAs($user)->post('/settings/site/services', [
        'name' => 'Imagem inválida',
        'image' => UploadedFile::fake()->image('servico.bmp'),
    ])->assertSessionHasErrors('image');

    expect(SiteService::query()->where('name', 'Imagem inválida')->exists())->toBeFalse();
});

it('removes the image file from storage when a service is deleted', function () {
    Storage::fake('public');
    $user = actingOwnerWithActiveContext();
    $service = SiteService::factory()->create(['image_path' => 'site-services/servico.jpg']);
    Storage::disk('public')->put('site-services/servico.jpg', 'fake-content');

    $this->actingAs($user)->delete("/settings/site/services/{$service->id}")->assertRedirect();

    Storage::disk('public')->assertMissing('site-services/servico.jpg');
});

it('rejects a negative starting price', function () {
    $user = actingOwnerWithActiveContext();

    $this->actingAs($user)->post('/settings/site/services', [
        'name' => 'Serviço inválido',
        'starting_price' => -10,
    ])->assertSessionHasErrors('starting_price');
});

it('links a site service to an operational service of the active organization', function () {
    $user = actingOwnerWithActiveContext();
    $organization = Organization::find(session('active_organization_id'));
    $siteService = SiteService::factory()->create();
    $service = Service::factory()->for($organization)->create(['status' => RecordStatus::Active]);

    $this->actingAs($user)->post("/settings/site/services/{$siteService->id}/link", [
        'service_id' => $service->id,
    ])->assertRedirect();

    expect($siteService->fresh()->service_id)->toBe($service->id)
        ->and(AuditLog::query()->where('action', AuditAction::Linked)->where('auditable_id', $siteService->id)->exists())->toBeTrue();
});

it('blocks linking a service that belongs to another organization', function () {
    $user = actingOwnerWithActiveContext();
    $siteService = SiteService::factory()->create();
    $otherOrganization = Organization::factory()->create();
    $foreignService = Service::factory()->for($otherOrganization)->create();

    $this->actingAs($user)->post("/settings/site/services/{$siteService->id}/link", [
        'service_id' => $foreignService->id,
    ])->assertNotFound();

    expect($siteService->fresh()->service_id)->toBeNull();
});

it('rejects linking a service id that does not exist', function () {
    $user = actingOwnerWithActiveContext();
    $siteService = SiteService::factory()->create();

    $this->actingAs($user)->post("/settings/site/services/{$siteService->id}/link", [
        'service_id' => (string) Str::ulid(),
    ])->assertSessionHasErrors('service_id');
});

it('unlinks a service while preserving the promotional content', function () {
    $user = actingOwnerWithActiveContext();
    $organization = Organization::find(session('active_organization_id'));
    $service = Service::factory()->for($organization)->create();
    $siteService = SiteService::factory()->create([
        'service_id' => $service->id,
        'name' => 'Serviço Preservado',
        'short_description' => 'Descrição original.',
    ]);

    $this->actingAs($user)->delete("/settings/site/services/{$siteService->id}/link")
        ->assertRedirect();

    $siteService->refresh();
    expect($siteService->service_id)->toBeNull()
        ->and($siteService->name)->toBe('Serviço Preservado')
        ->and($siteService->short_description)->toBe('Descrição original.')
        ->and(AuditLog::query()->where('action', AuditAction::Unlinked)->where('auditable_id', $siteService->id)->exists())->toBeTrue();
});

it('copies only the allowlisted public fields, requiring explicit opt-in for price', function () {
    $user = actingOwnerWithActiveContext();
    $organization = Organization::find(session('active_organization_id'));
    $service = Service::factory()->for($organization)->create([
        'name' => 'Consulta Pública',
        'description' => 'Descrição operacional completa.',
        'default_duration_minutes' => 45,
        'default_price_cents' => 25000,
        'internal_notes' => 'Nota interna sensível',
    ]);
    $siteService = SiteService::factory()->create([
        'service_id' => $service->id,
        'name' => 'Nome antigo',
        'starting_price_cents' => null,
    ]);

    $this->actingAs($user)->post("/settings/site/services/{$siteService->id}/copy-public-data", [
        'fields' => ['name', 'description', 'duration_minutes'],
    ])->assertRedirect();

    $siteService->refresh();
    expect($siteService->name)->toBe('Consulta Pública')
        ->and($siteService->description)->toBe('Descrição operacional completa.')
        ->and($siteService->duration_minutes)->toBe(45)
        ->and($siteService->starting_price_cents)->toBeNull();
});

it('copies the price only when explicitly included in the requested fields', function () {
    $user = actingOwnerWithActiveContext();
    $organization = Organization::find(session('active_organization_id'));
    $service = Service::factory()->for($organization)->create(['default_price_cents' => 15000]);
    $siteService = SiteService::factory()->create(['service_id' => $service->id, 'starting_price_cents' => null]);

    $this->actingAs($user)->post("/settings/site/services/{$siteService->id}/copy-public-data", [
        'fields' => ['starting_price_cents'],
    ])->assertRedirect();

    expect($siteService->fresh()->starting_price_cents)->toBe(15000);
});

it('rejects a copy field outside the allowlist', function () {
    $user = actingOwnerWithActiveContext();
    $organization = Organization::find(session('active_organization_id'));
    $service = Service::factory()->for($organization)->create();
    $siteService = SiteService::factory()->create(['service_id' => $service->id]);

    $this->actingAs($user)->post("/settings/site/services/{$siteService->id}/copy-public-data", [
        'fields' => ['internal_notes'],
    ])->assertSessionHasErrors('fields.0');
});

it('blocks a non-owner without site.update from linking a service', function () {
    actingOwnerWithActiveContext();
    $organization = Organization::find(session('active_organization_id'));
    $member = User::factory()->create();
    OrganizationMembership::factory()->for($organization)->for($member)->create();
    $siteService = SiteService::factory()->create();
    $service = Service::factory()->for($organization)->create();

    $this->actingAs($member)->post("/settings/site/services/{$siteService->id}/link", [
        'service_id' => $service->id,
    ])->assertForbidden();
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
