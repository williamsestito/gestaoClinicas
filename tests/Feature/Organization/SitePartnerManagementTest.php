<?php

declare(strict_types=1);

use App\Enums\OrganizationMembershipStatus;
use App\Enums\SystemRole;
use App\Models\Role;
use App\Models\SitePartner;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('lets the owner create a partner without a logo', function () {
    $user = actingOwnerWithActiveContext();

    $this->actingAs($user)->post('/settings/site/partners', [
        'name' => 'Convênio Saúde Total',
    ])->assertRedirect();

    expect(SitePartner::query()->where('name', 'Convênio Saúde Total')->exists())->toBeTrue();
});

it('lets the owner create a partner with a logo', function () {
    Storage::fake('public');
    $user = actingOwnerWithActiveContext();

    $this->actingAs($user)->post('/settings/site/partners', [
        'name' => 'Convênio Bem Estar',
        'logo' => UploadedFile::fake()->image('logo.jpg'),
    ])->assertRedirect();

    $partner = SitePartner::query()->where('name', 'Convênio Bem Estar')->firstOrFail();
    Storage::disk('public')->assertExists($partner->logo_path);
});

it('rejects a malformed file disguised as a partner logo', function () {
    Storage::fake('public');
    $user = actingOwnerWithActiveContext();

    $this->actingAs($user)->post('/settings/site/partners', [
        'name' => 'Convênio Inválido',
        'logo' => UploadedFile::fake()->create('logo.jpg', 100, 'image/jpeg'),
    ])->assertSessionHasErrors('logo');

    expect(SitePartner::query()->where('name', 'Convênio Inválido')->exists())->toBeFalse();
});

it('rejects a url that is not http/https', function () {
    $user = actingOwnerWithActiveContext();

    $this->actingAs($user)->post('/settings/site/partners', [
        'name' => 'Convênio X',
        'url' => 'javascript:alert(1)',
    ])->assertSessionHasErrors('url');
});

it('updates an existing partner without requiring a new logo', function () {
    Storage::fake('public');
    $user = actingOwnerWithActiveContext();
    $partner = SitePartner::factory()->create();

    $this->actingAs($user)->put("/settings/site/partners/{$partner->id}", [
        'name' => 'Nome atualizado',
    ])->assertSessionHasNoErrors();

    expect($partner->fresh()->name)->toBe('Nome atualizado');
});

it('removes the logo file from storage when a partner is deleted', function () {
    Storage::fake('public');
    $user = actingOwnerWithActiveContext();
    $partner = SitePartner::factory()->create();
    $path = $partner->logo_path;
    Storage::disk('public')->put($path, 'fake-original-content');

    $this->actingAs($user)->delete("/settings/site/partners/{$partner->id}")->assertRedirect();

    Storage::disk('public')->assertMissing($path);
});

it('toggles a partner active/inactive', function () {
    $user = actingOwnerWithActiveContext();
    $partner = SitePartner::factory()->create(['is_active' => true]);

    $this->actingAs($user)->patch("/settings/site/partners/{$partner->id}/toggle")->assertRedirect();

    expect($partner->fresh()->is_active)->toBeFalse();
});

it('reorders partners', function () {
    $user = actingOwnerWithActiveContext();
    $first = SitePartner::factory()->create(['order' => 0]);
    $second = SitePartner::factory()->create(['order' => 1]);

    $this->actingAs($user)->patch('/settings/site/partners/reorder', [
        'ids' => [$second->id, $first->id],
    ])->assertRedirect();

    expect($first->fresh()->order)->toBe(1)
        ->and($second->fresh()->order)->toBe(0);
});

it('blocks a user without site.update permission from creating a partner', function () {
    $owner = actingOwnerWithActiveContext();
    $organization = $owner->organizationMemberships()->first()->organization;
    seedSystemRoles($organization);
    $auditorRole = Role::query()->where('organization_id', $organization->id)->where('slug', SystemRole::Auditor->value)->firstOrFail();

    $member = User::factory()->create();
    $organization->memberships()->create([
        'user_id' => $member->id,
        'role_id' => $auditorRole->id,
        'status' => OrganizationMembershipStatus::Active,
    ]);

    $this->actingAs($member)->post('/settings/site/partners', [
        'name' => 'Não autorizado',
    ])->assertForbidden();
});
