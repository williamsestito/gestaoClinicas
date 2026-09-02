<?php

declare(strict_types=1);

use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\SiteSetting;
use App\Models\User;
use App\Support\Site\LandingSections;

it('lets the owner update the order and active flags of the landing sections', function () {
    $user = actingOwnerWithActiveContext();
    $siteSetting = SiteSetting::factory()->create();

    $sections = collect(LandingSections::TYPES)
        ->map(fn (string $type) => ['type' => $type, 'active' => $type !== 'faq'])
        ->reverse()
        ->values()
        ->all();

    $this->actingAs($user)->put('/settings/site/sections', [
        'sections' => $sections,
    ])->assertRedirect();

    $stored = $siteSetting->fresh()->sections_config;
    expect($stored[0]['type'])->toBe(LandingSections::TYPES[array_key_last(LandingSections::TYPES)])
        ->and(collect($stored)->firstWhere('type', 'faq')['active'])->toBeFalse();
});

it('rejects an unknown section type', function () {
    $user = actingOwnerWithActiveContext();

    $this->actingAs($user)->put('/settings/site/sections', [
        'sections' => [['type' => 'not-a-real-section', 'active' => true]],
    ])->assertSessionHasErrors('sections.0.type');
});

it('blocks a non-owner without site.update from reordering sections', function () {
    actingOwnerWithActiveContext();
    $organization = Organization::find(session('active_organization_id'));
    $member = User::factory()->create();
    OrganizationMembership::factory()->for($organization)->for($member)->create();

    $this->actingAs($member)->put('/settings/site/sections', [
        'sections' => [['type' => 'hero', 'active' => true]],
    ])->assertForbidden();
});
