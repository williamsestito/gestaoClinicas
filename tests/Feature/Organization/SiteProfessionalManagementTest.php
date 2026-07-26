<?php

declare(strict_types=1);

use App\Actions\Site\SiteCollectionItemAction;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\SiteProfessional;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

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

it('blocks a non-owner without site.update from managing professionals', function () {
    actingOwnerWithActiveContext();
    $organization = Organization::find(session('active_organization_id'));
    $member = User::factory()->create();
    OrganizationMembership::factory()->for($organization)->for($member)->create();

    $this->actingAs($member)->post('/settings/site/professionals', [
        'name' => 'Não autorizado',
    ])->assertForbidden();
});
