<?php

declare(strict_types=1);

use App\Actions\Site\SiteCollectionItemAction;
use App\Enums\AuditAction;
use App\Enums\RecordStatus;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Professional;
use App\Models\SiteProfessional;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

it('lets the owner create and delete a professional', function () {
    $user = actingOwnerWithActiveContext();

    $this->actingAs($user)->post('/settings/site/professionals', [
        'name' => 'Dra. Camila Andrade',
        'role_title' => 'Dermatologista',
    ])->assertRedirect();

    $professional = SiteProfessional::query()->where('name', 'Dra. Camila Andrade')->firstOrFail();

    $this->actingAs($user)->delete("/settings/site/professionals/{$professional->id}")
        ->assertRedirect();

    expect(SiteProfessional::query()->find($professional->id))->toBeNull();
});

it('validates social links as urls', function () {
    $user = actingOwnerWithActiveContext();

    $this->actingAs($user)->post('/settings/site/professionals', [
        'name' => 'Profissional',
        'instagram_url' => 'not-a-url',
    ])->assertSessionHasErrors('instagram_url');
});

it('rejects a professional photo format other than JPEG, PNG and WebP', function () {
    Storage::fake('public');
    $user = actingOwnerWithActiveContext();

    $this->actingAs($user)->post('/settings/site/professionals', [
        'name' => 'Foto inválida',
        'photo' => UploadedFile::fake()->image('foto.gif'),
    ])->assertSessionHasErrors('photo');

    expect(SiteProfessional::query()->where('name', 'Foto inválida')->exists())->toBeFalse();
});

it('keeps the previous professional photo untouched when persisting the new one fails', function () {
    Storage::fake('public');
    $professional = SiteProfessional::factory()->create(['photo_path' => 'site-professionals/original.jpg']);
    Storage::disk('public')->put('site-professionals/original.jpg', 'fake-original-content');

    SiteProfessional::saving(function () {
        throw new RuntimeException('Simulated database failure.');
    });

    try {
        $action = app(SiteCollectionItemAction::class);
        $action->replaceImage($professional, 'photo_path', UploadedFile::fake()->image('nova.jpg'), 'site-professionals');

        $this->expectException(RuntimeException::class);
        $action->upsert($professional, ['name' => $professional->name]);
    } finally {
        SiteProfessional::unsetEventDispatcher();
    }

    expect($professional->fresh()->photo_path)->toBe('site-professionals/original.jpg');
    Storage::disk('public')->assertExists('site-professionals/original.jpg');
});

it('removes the photo file from storage when a professional is deleted', function () {
    Storage::fake('public');
    $user = actingOwnerWithActiveContext();
    $professional = SiteProfessional::factory()->create(['photo_path' => 'site-professionals/foto.jpg']);
    Storage::disk('public')->put('site-professionals/foto.jpg', 'fake-content');

    $this->actingAs($user)->delete("/settings/site/professionals/{$professional->id}")->assertRedirect();

    Storage::disk('public')->assertMissing('site-professionals/foto.jpg');
});

it('links a site professional to an operational professional of the active organization', function () {
    $user = actingOwnerWithActiveContext();
    $organization = Organization::find(session('active_organization_id'));
    $siteProfessional = SiteProfessional::factory()->create();
    $professional = Professional::factory()->for($organization)->create(['status' => RecordStatus::Active]);

    $this->actingAs($user)->post("/settings/site/professionals/{$siteProfessional->id}/link", [
        'professional_id' => $professional->id,
    ])->assertRedirect();

    expect($siteProfessional->fresh()->professional_id)->toBe($professional->id)
        ->and(AuditLog::query()->where('action', AuditAction::Linked)->where('auditable_id', $siteProfessional->id)->exists())->toBeTrue();
});

it('blocks linking a professional that belongs to another organization', function () {
    $user = actingOwnerWithActiveContext();
    $siteProfessional = SiteProfessional::factory()->create();
    $otherOrganization = Organization::factory()->create();
    $foreignProfessional = Professional::factory()->for($otherOrganization)->create();

    $this->actingAs($user)->post("/settings/site/professionals/{$siteProfessional->id}/link", [
        'professional_id' => $foreignProfessional->id,
    ])->assertNotFound();

    expect($siteProfessional->fresh()->professional_id)->toBeNull();
});

it('rejects linking a professional id that does not exist', function () {
    $user = actingOwnerWithActiveContext();
    $siteProfessional = SiteProfessional::factory()->create();

    $this->actingAs($user)->post("/settings/site/professionals/{$siteProfessional->id}/link", [
        'professional_id' => (string) Str::ulid(),
    ])->assertSessionHasErrors('professional_id');
});

it('unlinks a professional while preserving the promotional content', function () {
    $user = actingOwnerWithActiveContext();
    $organization = Organization::find(session('active_organization_id'));
    $professional = Professional::factory()->for($organization)->create();
    $siteProfessional = SiteProfessional::factory()->create([
        'professional_id' => $professional->id,
        'name' => 'Dra. Preservada',
        'bio' => 'Biografia original.',
    ]);

    $this->actingAs($user)->delete("/settings/site/professionals/{$siteProfessional->id}/link")
        ->assertRedirect();

    $siteProfessional->refresh();
    expect($siteProfessional->professional_id)->toBeNull()
        ->and($siteProfessional->name)->toBe('Dra. Preservada')
        ->and($siteProfessional->bio)->toBe('Biografia original.')
        ->and(AuditLog::query()->where('action', AuditAction::Unlinked)->where('auditable_id', $siteProfessional->id)->exists())->toBeTrue();
});

it('copies only the allowlisted public fields and never internal data', function () {
    $user = actingOwnerWithActiveContext();
    $organization = Organization::find(session('active_organization_id'));
    $professional = Professional::factory()->for($organization)->create([
        'display_name' => 'Dr. João Nome Público',
        'bio' => 'Biografia operacional.',
        'email' => 'joao@interno.com',
        'phone' => '11999999999',
        'document' => '12345678900',
    ]);
    $siteProfessional = SiteProfessional::factory()->create([
        'professional_id' => $professional->id,
        'name' => 'Nome antigo',
        'bio' => 'Bio antiga',
        'professional_register' => 'CRM/SP 00000',
    ]);

    $this->actingAs($user)->post("/settings/site/professionals/{$siteProfessional->id}/copy-public-data", [
        'fields' => ['name', 'bio'],
    ])->assertRedirect();

    $siteProfessional->refresh();
    expect($siteProfessional->name)->toBe('Dr. João Nome Público')
        ->and($siteProfessional->bio)->toBe('Biografia operacional.')
        ->and($siteProfessional->professional_register)->toBe('CRM/SP 00000');
});

it('rejects a copy field outside the allowlist', function () {
    $user = actingOwnerWithActiveContext();
    $organization = Organization::find(session('active_organization_id'));
    $professional = Professional::factory()->for($organization)->create();
    $siteProfessional = SiteProfessional::factory()->create(['professional_id' => $professional->id]);

    $this->actingAs($user)->post("/settings/site/professionals/{$siteProfessional->id}/copy-public-data", [
        'fields' => ['document'],
    ])->assertSessionHasErrors('fields.0');
});

it('blocks copying data when the site professional is not linked', function () {
    $user = actingOwnerWithActiveContext();
    $siteProfessional = SiteProfessional::factory()->create(['professional_id' => null]);

    $this->actingAs($user)->post("/settings/site/professionals/{$siteProfessional->id}/copy-public-data", [
        'fields' => ['name'],
    ])->assertStatus(422);
});

it('blocks a non-owner without site.update from linking a professional', function () {
    actingOwnerWithActiveContext();
    $organization = Organization::find(session('active_organization_id'));
    $member = User::factory()->create();
    OrganizationMembership::factory()->for($organization)->for($member)->create();
    $siteProfessional = SiteProfessional::factory()->create();
    $professional = Professional::factory()->for($organization)->create();

    $this->actingAs($member)->post("/settings/site/professionals/{$siteProfessional->id}/link", [
        'professional_id' => $professional->id,
    ])->assertForbidden();
});

it('blocks a non-owner without site.update from managing professionals', function () {
    actingOwnerWithActiveContext();
    $organization = Organization::find(session('active_organization_id'));
    $member = User::factory()->create();
    OrganizationMembership::factory()->for($organization)->for($member)->create();

    $this->actingAs($member)->post('/settings/site/professionals', [
        'name' => 'Não autorizado',
    ])->assertForbidden();
});
