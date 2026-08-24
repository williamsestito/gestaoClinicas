<?php

declare(strict_types=1);

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\Sale;
use App\Models\Service;

function createPendingApprovalSale(): array
{
    $setup = saleSetup();
    $service = Service::factory()->for($setup['organization'])->create(['default_price_cents' => 10000, 'max_discount_percentage' => 10]);

    test()->actingAs($setup['user'])->post('/settings/sales', [
        'patient_id' => $setup['patient']->id,
        'unit_id' => $setup['unit']->id,
        'legal_entity_id' => $setup['legalEntity']->id,
        'items' => [
            ['item_type' => 'service', 'service_id' => $service->id, 'quantity' => 1, 'discount_percentage' => 50],
        ],
    ]);

    $sale = Sale::query()->where('patient_id', $setup['patient']->id)->firstOrFail();

    return [...$setup, 'sale' => $sale, 'item' => $sale->items->first()];
}

it('rejects approval with the wrong password', function () {
    $setup = createPendingApprovalSale();

    $this->actingAs($setup['user'])->patch("/settings/sales/{$setup['sale']->id}/itens/{$setup['item']->id}/aprovar-desconto", [
        'justification' => 'Desconto combinado.',
        'password' => 'senha-errada',
    ])->assertSessionHasErrors('password');

    expect($setup['item']->fresh()->approved_at)->toBeNull();
});

it('requires a justification to approve a discount', function () {
    $setup = createPendingApprovalSale();

    $this->actingAs($setup['user'])->patch("/settings/sales/{$setup['sale']->id}/itens/{$setup['item']->id}/aprovar-desconto", [
        'justification' => '',
        'password' => 'password',
    ])->assertSessionHasErrors('justification');
});

it('approves the discount and logs requester, approver, values and justification', function () {
    $setup = createPendingApprovalSale();

    $this->actingAs($setup['user'])->patch("/settings/sales/{$setup['sale']->id}/itens/{$setup['item']->id}/aprovar-desconto", [
        'justification' => 'Cliente antigo, desconto autorizado pela gerência.',
        'password' => 'password',
    ])->assertRedirect();

    $item = $setup['item']->fresh();
    expect($item->approved_by)->toBe($setup['user']->id)
        ->and($item->approved_at)->not->toBeNull()
        ->and($item->approval_justification)->toBe('Cliente antigo, desconto autorizado pela gerência.')
        ->and($item->isPendingApproval())->toBeFalse();

    $log = AuditLog::query()->where('auditable_id', $item->id)->where('action', AuditAction::Approved)->firstOrFail();
    expect($log->after_data['requester_user_id'])->toBe($setup['sale']->created_by)
        ->and($log->after_data['approver_user_id'])->toBe($setup['user']->id)
        ->and($log->after_data['discount_percentage'])->toBe(50)
        ->and($log->after_data['justification'])->toBe('Cliente antigo, desconto autorizado pela gerência.');
});

it('cannot approve the same item twice', function () {
    $setup = createPendingApprovalSale();

    $this->actingAs($setup['user'])->patch("/settings/sales/{$setup['sale']->id}/itens/{$setup['item']->id}/aprovar-desconto", [
        'justification' => 'Primeira aprovação.',
        'password' => 'password',
    ])->assertRedirect();

    $this->actingAs($setup['user'])->patch("/settings/sales/{$setup['sale']->id}/itens/{$setup['item']->id}/aprovar-desconto", [
        'justification' => 'Segunda tentativa.',
        'password' => 'password',
    ])->assertSessionHasErrors('item');
});
