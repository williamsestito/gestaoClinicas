<?php

declare(strict_types=1);

use App\Filament\Resources\LegalEntities\Pages\ListLegalEntities;
use App\Models\LegalEntity;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use Livewire\Livewire;

it('masks a CPF in the legal entities table', function () {
    $admin = User::factory()->platformAdmin()->create();
    $legalEntity = LegalEntity::factory()->create();
    $rawDocument = $legalEntity->document;

    Livewire::actingAs($admin)
        ->test(ListLegalEntities::class)
        ->assertTableColumnFormattedStateSet('document', '***.***.***-'.substr($rawDocument, -2), $legalEntity);
});

it('masks a CNPJ in the legal entities table', function () {
    $admin = User::factory()->platformAdmin()->create();
    $legalEntity = LegalEntity::factory()->company()->create();
    $rawDocument = $legalEntity->document;

    Livewire::actingAs($admin)
        ->test(ListLegalEntities::class)
        ->assertTableColumnFormattedStateSet('document', '**.***.***/****-'.substr($rawDocument, -2), $legalEntity);
});

it('masks the document on the legal entity view page and never shows it unmasked', function () {
    $admin = User::factory()->platformAdmin()->create();
    $organization = Organization::factory()->create();
    $legalEntity = LegalEntity::factory()->company()->for($organization)->create();
    // LegalEntityPolicy::view() requires an active organization membership,
    // which platform admins don't have by default — grant one for this test.
    OrganizationMembership::factory()->for($organization)->for($admin)->create();
    $rawDocument = $legalEntity->document;

    $this->actingAs($admin)
        ->get("/admin/legal-entities/{$legalEntity->getRouteKey()}")
        ->assertOk()
        ->assertSee('**.***.***/****-'.substr($rawDocument, -2))
        ->assertDontSee($rawDocument);
});
