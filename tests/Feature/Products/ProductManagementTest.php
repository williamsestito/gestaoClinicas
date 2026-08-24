<?php

declare(strict_types=1);

use App\Enums\AuditAction;
use App\Enums\OrganizationMembershipStatus;
use App\Enums\PermissionKey;
use App\Enums\RecordStatus;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Patient;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;

it('creates a product for the active organization', function () {
    $user = actingOwnerWithActiveContext();

    $this->actingAs($user)->post('/settings/products', [
        'name' => 'Creme hidratante',
        'code' => 'prd-01',
        'unit_of_measure' => 'un',
        'cost' => 20.5,
        'margin_percentage' => 50,
        'price' => 40,
        'max_discount_percentage' => 10,
    ])->assertRedirect('/settings/products');

    $product = Product::query()->where('code', 'PRD-01')->firstOrFail();
    expect($product->name)->toBe('Creme hidratante')
        ->and($product->cost_cents)->toBe(2050)
        ->and($product->price_cents)->toBe(4000)
        ->and($product->max_discount_percentage)->toBe(10)
        ->and(AuditLog::query()->where('auditable_id', $product->id)->where('action', AuditAction::Created)->exists())->toBeTrue();
});

it('treats an empty price as null, never as zero', function () {
    $user = actingOwnerWithActiveContext();

    $this->actingAs($user)->post('/settings/products', [
        'name' => 'Sabonete', 'code' => 'SAB-01', 'unit_of_measure' => 'un',
    ])->assertRedirect('/settings/products');

    $product = Product::query()->where('code', 'SAB-01')->firstOrFail();
    expect($product->price_cents)->toBeNull();
});

it('rejects a duplicate code within the same clinic but allows it in another clinic', function () {
    $user = actingOwnerWithActiveContext();
    $organization = $user->organizationMemberships()->first()->organization;
    Product::factory()->for($organization)->create(['code' => 'PRD-01']);

    $this->actingAs($user)->post('/settings/products', [
        'name' => 'Outro', 'code' => 'PRD-01', 'unit_of_measure' => 'un',
    ])->assertSessionHasErrors('code');

    $otherOrganization = Organization::factory()->create();
    Product::factory()->for($otherOrganization)->create(['code' => 'PRD-01']);
    expect(Product::query()->where('code', 'PRD-01')->count())->toBe(2);
});

it('blocks a member without the manage permission from creating a product', function () {
    $user = actingOwnerWithActiveContext();
    $organization = $user->organizationMemberships()->first()->organization;

    $viewerUser = User::factory()->create();
    $viewerRole = Role::query()->create(['organization_id' => $organization->id, 'name' => 'Visualizador', 'slug' => 'visualizador-teste', 'is_system' => false]);
    OrganizationMembership::factory()->for($organization)->for($viewerUser)->create(['status' => OrganizationMembershipStatus::Active, 'role_id' => $viewerRole->id]);
    session(['active_organization_id' => $organization->id]);

    $this->actingAs($viewerUser)->post('/settings/products', [
        'name' => 'Produto', 'code' => 'PRD-02', 'unit_of_measure' => 'un',
    ])->assertForbidden();
});

it('allows a member with the products.manage permission to create a product', function () {
    $user = actingOwnerWithActiveContext();
    $organization = $user->organizationMemberships()->first()->organization;

    $permission = Permission::query()->firstOrCreate(['key' => PermissionKey::ProductsManage->value], ['group' => 'Produtos', 'label' => 'Gerenciar produtos']);
    $role = Role::query()->create(['organization_id' => $organization->id, 'name' => 'Estoque', 'slug' => 'estoque-teste', 'is_system' => false]);
    $role->permissions()->attach($permission->id);

    $managerUser = User::factory()->create();
    OrganizationMembership::factory()->for($organization)->for($managerUser)->create(['status' => OrganizationMembershipStatus::Active, 'role_id' => $role->id]);
    session(['active_organization_id' => $organization->id]);

    $this->actingAs($managerUser)->post('/settings/products', [
        'name' => 'Produto', 'code' => 'PRD-03', 'unit_of_measure' => 'un',
    ])->assertRedirect('/settings/products');
});

it('blocks access to a product belonging to another organization even with a matching-shaped session', function () {
    $user = actingOwnerWithActiveContext();
    $otherOrganization = Organization::factory()->create();
    $product = Product::factory()->for($otherOrganization)->create();

    session(['active_organization_id' => $otherOrganization->id]);

    $this->actingAs($user)->get("/settings/products/{$product->id}/edit")->assertNotFound();
});

it('deactivates and reactivates a product', function () {
    $user = actingOwnerWithActiveContext();
    $organization = $user->organizationMemberships()->first()->organization;
    $product = Product::factory()->for($organization)->create(['status' => RecordStatus::Active]);

    $this->actingAs($user)->patch("/settings/products/{$product->id}/deactivate")->assertRedirect();
    expect($product->refresh()->status)->toBe(RecordStatus::Inactive);

    $this->actingAs($user)->patch("/settings/products/{$product->id}/activate")->assertRedirect();
    expect($product->refresh()->status)->toBe(RecordStatus::Active);
});

it('blocks deleting a product that has already been sold, and allows deleting one that has not', function () {
    $user = actingOwnerWithActiveContext();
    $organization = $user->organizationMemberships()->first()->organization;
    $soldProduct = Product::factory()->for($organization)->create();
    $unsoldProduct = Product::factory()->for($organization)->create();

    $patient = Patient::factory()->for($organization)->create();
    $sale = Sale::factory()->create(['organization_id' => $organization->id, 'patient_id' => $patient->id]);
    SaleItem::factory()->create(['sale_id' => $sale->id, 'organization_id' => $organization->id, 'item_type' => 'product', 'service_id' => null, 'product_id' => $soldProduct->id]);

    $this->actingAs($user)->delete("/settings/products/{$soldProduct->id}")->assertSessionHasErrors('product');
    expect($soldProduct->fresh()->trashed())->toBeFalse();

    $this->actingAs($user)->delete("/settings/products/{$unsoldProduct->id}")->assertRedirect();
    expect($unsoldProduct->fresh()->trashed())->toBeTrue();
});
