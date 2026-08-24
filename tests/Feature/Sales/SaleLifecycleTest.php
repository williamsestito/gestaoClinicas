<?php

declare(strict_types=1);

use App\Actions\Organization\SeedSystemRolesAction;
use App\Enums\OrganizationMembershipStatus;
use App\Enums\SaleStatus;
use App\Enums\SystemRole;
use App\Models\LegalEntity;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Patient;
use App\Models\Product;
use App\Models\Role;
use App\Models\Sale;
use App\Models\Service;
use App\Models\SessionPackage;
use App\Models\Unit;
use App\Models\UnitMembership;
use App\Models\User;

/**
 * @return array{organization: Organization, unit: Unit, legalEntity: LegalEntity, user: User, patient: Patient}
 */
function saleSetup(): array
{
    $organization = Organization::factory()->create();
    app(SeedSystemRolesAction::class)->handle($organization);

    $legalEntity = LegalEntity::factory()->primary()->for($organization)->create();
    $unit = Unit::factory()->headquarters()->for($organization)->for($legalEntity, 'legalEntity')->create();

    $role = Role::query()->where('organization_id', $organization->id)->where('slug', SystemRole::ClinicAdmin->value)->firstOrFail();
    $user = User::factory()->create();
    $membership = OrganizationMembership::factory()->for($organization)->for($user)->create([
        'status' => OrganizationMembershipStatus::Active,
        'role_id' => $role->id,
    ]);
    UnitMembership::factory()->for($membership, 'organizationMembership')->for($unit, 'unit')->create();

    session(['active_organization_id' => $organization->id, 'active_unit_id' => $unit->id]);

    $patient = Patient::factory()->for($organization)->create();

    return compact('organization', 'unit', 'legalEntity', 'user', 'patient');
}

it('creates a draft sale with a service item that has no discount, confirming it right away', function () {
    $setup = saleSetup();
    $service = Service::factory()->for($setup['organization'])->create(['default_price_cents' => 10000, 'max_discount_percentage' => 10]);

    $response = $this->actingAs($setup['user'])->post('/settings/sales', [
        'patient_id' => $setup['patient']->id,
        'unit_id' => $setup['unit']->id,
        'legal_entity_id' => $setup['legalEntity']->id,
        'items' => [
            ['item_type' => 'service', 'service_id' => $service->id, 'quantity' => 1, 'discount_percentage' => 0],
        ],
    ]);

    $sale = Sale::query()->where('patient_id', $setup['patient']->id)->firstOrFail();
    $response->assertRedirect("/settings/sales/{$sale->id}");
    expect($sale->status)->toBe(SaleStatus::Draft)
        ->and($sale->total_cents)->toBe(10000)
        ->and($sale->items()->count())->toBe(1);
});

it('ignores a client-supplied unit price when the item has a catalog price, closing a discount-approval bypass', function () {
    $setup = saleSetup();
    $service = Service::factory()->for($setup['organization'])->create(['default_price_cents' => 50000, 'max_discount_percentage' => 10]);

    $this->actingAs($setup['user'])->post('/settings/sales', [
        'patient_id' => $setup['patient']->id,
        'unit_id' => $setup['unit']->id,
        'legal_entity_id' => $setup['legalEntity']->id,
        'items' => [
            // Tenta sobrescrever o preço de R$ 500 para R$ 0,01 sem passar
            // pelo campo de desconto — o servidor deve ignorar isso e usar
            // sempre o preço de catálogo, mantendo requires_approval=false
            // só porque o desconto reportado (0%) está dentro do limite.
            ['item_type' => 'service', 'service_id' => $service->id, 'quantity' => 1, 'discount_percentage' => 0, 'unit_price' => 0.01],
        ],
    ]);

    $sale = Sale::query()->where('patient_id', $setup['patient']->id)->firstOrFail();
    $item = $sale->items->first();

    expect($item->unit_price_cents)->toBe(50000)
        ->and($item->final_price_cents)->toBe(50000)
        ->and($item->requires_approval)->toBeFalse()
        ->and($sale->total_cents)->toBe(50000);
});

it('marks the sale as pending approval when an item discount exceeds the configured limit', function () {
    $setup = saleSetup();
    $service = Service::factory()->for($setup['organization'])->create(['default_price_cents' => 10000, 'max_discount_percentage' => 10]);

    $this->actingAs($setup['user'])->post('/settings/sales', [
        'patient_id' => $setup['patient']->id,
        'unit_id' => $setup['unit']->id,
        'legal_entity_id' => $setup['legalEntity']->id,
        'items' => [
            ['item_type' => 'service', 'service_id' => $service->id, 'quantity' => 1, 'discount_percentage' => 50],
        ],
    ]);

    $sale = Sale::query()->where('patient_id', $setup['patient']->id)->firstOrFail();
    expect($sale->status)->toBe(SaleStatus::PendingApproval)
        ->and($sale->items->first()->requires_approval)->toBeTrue();
});

it('refuses to confirm a sale with a pending approval item, but confirms once approved', function () {
    $setup = saleSetup();
    $service = Service::factory()->for($setup['organization'])->create(['default_price_cents' => 10000, 'max_discount_percentage' => 10]);

    $this->actingAs($setup['user'])->post('/settings/sales', [
        'patient_id' => $setup['patient']->id,
        'unit_id' => $setup['unit']->id,
        'legal_entity_id' => $setup['legalEntity']->id,
        'items' => [
            ['item_type' => 'service', 'service_id' => $service->id, 'quantity' => 1, 'discount_percentage' => 50],
        ],
    ]);
    $sale = Sale::query()->where('patient_id', $setup['patient']->id)->firstOrFail();

    $this->actingAs($setup['user'])->patch("/settings/sales/{$sale->id}/confirmar")
        ->assertSessionHasErrors('sale');
    expect($sale->refresh()->status)->toBe(SaleStatus::PendingApproval);

    $item = $sale->items->first();
    $this->actingAs($setup['user'])->patch("/settings/sales/{$sale->id}/itens/{$item->id}/aprovar-desconto", [
        'justification' => 'Cliente fidelizado há anos.',
        'password' => 'password',
    ])->assertRedirect();

    $this->actingAs($setup['user'])->patch("/settings/sales/{$sale->id}/confirmar")
        ->assertRedirect();
    expect($sale->refresh()->status)->toBe(SaleStatus::Confirmed);
});

it('creates a real session package when a service_package item is confirmed, linking it back to the sale item', function () {
    $setup = saleSetup();
    $service = Service::factory()->for($setup['organization'])->create(['default_price_cents' => null, 'max_discount_percentage' => 0]);

    $this->actingAs($setup['user'])->post('/settings/sales', [
        'patient_id' => $setup['patient']->id,
        'unit_id' => $setup['unit']->id,
        'legal_entity_id' => $setup['legalEntity']->id,
        'items' => [
            ['item_type' => 'service_package', 'service_id' => $service->id, 'session_count' => 10, 'quantity' => 1, 'discount_percentage' => 0, 'unit_price' => 800],
        ],
    ]);
    $sale = Sale::query()->where('patient_id', $setup['patient']->id)->firstOrFail();

    $this->actingAs($setup['user'])->patch("/settings/sales/{$sale->id}/confirmar")->assertRedirect();

    $package = SessionPackage::query()->where('patient_id', $setup['patient']->id)->firstOrFail();
    expect($package->total_sessions)->toBe(10)
        ->and($package->service_id)->toBe($service->id)
        ->and($package->origin_sale_item_id)->toBe($sale->items->first()->id);
});

it('never deletes a sale on cancellation, only changes its status and records the reason', function () {
    $setup = saleSetup();
    $product = Product::factory()->for($setup['organization'])->create(['price_cents' => 5000, 'max_discount_percentage' => 0]);

    $this->actingAs($setup['user'])->post('/settings/sales', [
        'patient_id' => $setup['patient']->id,
        'unit_id' => $setup['unit']->id,
        'legal_entity_id' => $setup['legalEntity']->id,
        'items' => [
            ['item_type' => 'product', 'product_id' => $product->id, 'quantity' => 2, 'discount_percentage' => 0],
        ],
    ]);
    $sale = Sale::query()->where('patient_id', $setup['patient']->id)->firstOrFail();
    $this->actingAs($setup['user'])->patch("/settings/sales/{$sale->id}/confirmar")->assertRedirect();

    $this->actingAs($setup['user'])->patch("/settings/sales/{$sale->id}/cancelar", [
        'reason' => 'Paciente desistiu da compra.',
    ])->assertRedirect();

    expect($sale->refresh()->status)->toBe(SaleStatus::Cancelled)
        ->and($sale->cancellation_reason)->toBe('Paciente desistiu da compra.')
        ->and(Sale::query()->whereKey($sale->id)->exists())->toBeTrue()
        ->and($sale->items()->count())->toBe(1);
});

it('refuses to cancel a sale that is still a draft', function () {
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

    // SalePolicy::cancel() já exige status Confirmed antes mesmo de chegar
    // na Action — cancelar um rascunho é bloqueado na autorização (403),
    // não uma falha de validação.
    $this->actingAs($setup['user'])->patch("/settings/sales/{$sale->id}/cancelar", [
        'reason' => 'Teste',
    ])->assertForbidden();
});
