<?php

declare(strict_types=1);

use App\Actions\Organization\SeedSystemRolesAction;
use App\Enums\OrganizationMembershipStatus;
use App\Enums\RecordStatus;
use App\Enums\SystemRole;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Patient;
use App\Models\Product;
use App\Models\Professional;
use App\Models\Role;
use App\Models\Sale;
use App\Models\Service;
use App\Models\UnitMembership;
use App\Models\User;

it('lets reception create a sale but blocks approving a discount above the limit', function () {
    $setup = saleSetup();
    $receptionRole = Role::query()->where('organization_id', $setup['organization']->id)->where('slug', SystemRole::Reception->value)->firstOrFail();
    $receptionUser = User::factory()->create();
    $membership = OrganizationMembership::factory()->for($setup['organization'])->for($receptionUser)->create([
        'status' => OrganizationMembershipStatus::Active,
        'role_id' => $receptionRole->id,
    ]);
    UnitMembership::factory()->for($membership, 'organizationMembership')->for($setup['unit'], 'unit')->create();
    session(['active_organization_id' => $setup['organization']->id, 'active_unit_id' => $setup['unit']->id]);

    $service = Service::factory()->for($setup['organization'])->create(['default_price_cents' => 10000, 'max_discount_percentage' => 10]);

    $this->actingAs($receptionUser)->post('/settings/sales', [
        'patient_id' => $setup['patient']->id,
        'unit_id' => $setup['unit']->id,
        'legal_entity_id' => $setup['legalEntity']->id,
        'items' => [
            ['item_type' => 'service', 'service_id' => $service->id, 'quantity' => 1, 'discount_percentage' => 50],
        ],
    ])->assertRedirect();

    $sale = Sale::query()->where('patient_id', $setup['patient']->id)->firstOrFail();
    $item = $sale->items->first();

    $this->actingAs($receptionUser)->patch("/settings/sales/{$sale->id}/itens/{$item->id}/aprovar-desconto", [
        'justification' => 'Tentativa da recepção.',
        'password' => 'password',
    ])->assertForbidden();
});

it('lets a professional sell only to their own patients', function () {
    $setup = saleSetup();
    $professionalUser = User::factory()->create();
    $professionalRole = Role::query()->where('organization_id', $setup['organization']->id)->where('slug', SystemRole::Professional->value)->firstOrFail();
    $professional = Professional::factory()->for($setup['organization'])->create(['user_id' => $professionalUser->id, 'status' => RecordStatus::Active]);
    $membership = OrganizationMembership::factory()->for($setup['organization'])->for($professionalUser)->create([
        'status' => OrganizationMembershipStatus::Active,
        'role_id' => $professionalRole->id,
    ]);
    UnitMembership::factory()->for($membership, 'organizationMembership')->for($setup['unit'], 'unit')->create();

    $ownPatient = Patient::factory()->for($setup['organization'])->create(['primary_professional_id' => $professional->id]);
    $otherPatient = Patient::factory()->for($setup['organization'])->create();
    $service = Service::factory()->for($setup['organization'])->create(['default_price_cents' => 10000]);
    session(['active_organization_id' => $setup['organization']->id, 'active_unit_id' => $setup['unit']->id]);

    $this->actingAs($professionalUser)->post('/settings/sales', [
        'patient_id' => $ownPatient->id,
        'unit_id' => $setup['unit']->id,
        'legal_entity_id' => $setup['legalEntity']->id,
        'items' => [
            ['item_type' => 'service', 'service_id' => $service->id, 'quantity' => 1, 'discount_percentage' => 0],
        ],
    ])->assertRedirect();

    $this->actingAs($professionalUser)->post('/settings/sales', [
        'patient_id' => $otherPatient->id,
        'unit_id' => $setup['unit']->id,
        'legal_entity_id' => $setup['legalEntity']->id,
        'items' => [
            ['item_type' => 'service', 'service_id' => $service->id, 'quantity' => 1, 'discount_percentage' => 0],
        ],
    ])->assertSessionHasErrors('patient_id');
});

it('blocks a professional without the broad permission from attributing a sale to a colleague', function () {
    $setup = saleSetup();
    $professionalUser = User::factory()->create();
    $professionalRole = Role::query()->where('organization_id', $setup['organization']->id)->where('slug', SystemRole::Professional->value)->firstOrFail();
    $professional = Professional::factory()->for($setup['organization'])->create(['user_id' => $professionalUser->id, 'status' => RecordStatus::Active]);
    $colleague = Professional::factory()->for($setup['organization'])->create(['status' => RecordStatus::Active]);
    $membership = OrganizationMembership::factory()->for($setup['organization'])->for($professionalUser)->create([
        'status' => OrganizationMembershipStatus::Active,
        'role_id' => $professionalRole->id,
    ]);
    UnitMembership::factory()->for($membership, 'organizationMembership')->for($setup['unit'], 'unit')->create();

    $ownPatient = Patient::factory()->for($setup['organization'])->create(['primary_professional_id' => $professional->id]);
    $service = Service::factory()->for($setup['organization'])->create(['default_price_cents' => 10000]);
    session(['active_organization_id' => $setup['organization']->id, 'active_unit_id' => $setup['unit']->id]);

    $this->actingAs($professionalUser)->post('/settings/sales', [
        'patient_id' => $ownPatient->id,
        'unit_id' => $setup['unit']->id,
        'legal_entity_id' => $setup['legalEntity']->id,
        'professional_id' => $colleague->id,
        'items' => [
            ['item_type' => 'service', 'service_id' => $service->id, 'quantity' => 1, 'discount_percentage' => 0],
        ],
    ])->assertSessionHasErrors('professional_id');
});

it('blocks access to a sale from another organization via the middleware guard, even with a matching-shaped session', function () {
    $setup = saleSetup();
    $product = Product::factory()->for($setup['organization'])->create(['price_cents' => 5000]);

    $this->actingAs($setup['user'])->post('/settings/sales', [
        'patient_id' => $setup['patient']->id,
        'unit_id' => $setup['unit']->id,
        'legal_entity_id' => $setup['legalEntity']->id,
        'items' => [
            ['item_type' => 'product', 'product_id' => $product->id, 'quantity' => 1, 'discount_percentage' => 0],
        ],
    ]);
    $sale = Sale::query()->where('patient_id', $setup['patient']->id)->firstOrFail();

    $otherOrganization = Organization::factory()->create();
    app(SeedSystemRolesAction::class)->handle($otherOrganization);
    $otherRole = Role::query()->where('organization_id', $otherOrganization->id)->where('slug', SystemRole::ClinicAdmin->value)->firstOrFail();
    OrganizationMembership::factory()->for($otherOrganization)->for($setup['user'])->create([
        'status' => OrganizationMembershipStatus::Active,
        'role_id' => $otherRole->id,
    ]);
    session(['active_organization_id' => $otherOrganization->id]);

    $this->actingAs($setup['user'])->get("/settings/sales/{$sale->id}")->assertNotFound();
});
