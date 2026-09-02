<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Enums\SaleStatus;
use App\Models\Sale;
use App\Models\User;
use App\Support\Auditing\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Substitui por completo os itens de uma venda ainda não confirmada — o
 * carrinho é sempre reenviado inteiro pelo frontend, nunca um PATCH
 * parcial de um item isolado (evita ficar reconciliando adições/remoções
 * item a item).
 */
class UpdateSaleDraftAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly CreateSaleAction $createSaleAction,
    ) {}

    /**
     * @param  array{unit_id: string, legal_entity_id: string, patient_id: string, professional_id: ?string, appointment_id: ?string}  $attributes
     * @param  array<int, array{item_type: string, service_id: ?string, product_id: ?string, session_count: ?int, quantity: int, discount_percentage: int, unit_price_cents: ?int}>  $items
     */
    public function handle(Sale $sale, User $editor, array $attributes, array $items): Sale
    {
        if (! in_array($sale->status, [SaleStatus::Draft, SaleStatus::PendingApproval], true)) {
            throw ValidationException::withMessages([
                'sale' => 'Só é possível editar uma venda enquanto ela não for confirmada ou cancelada.',
            ]);
        }

        if ($items === []) {
            throw ValidationException::withMessages([
                'items' => 'A venda precisa de pelo menos um item.',
            ]);
        }

        $this->createSaleAction->assertPatientAccessible($editor, $sale->organization_id, $attributes['patient_id'], $attributes['professional_id']);

        $before = ['total_cents' => $sale->total_cents, 'item_count' => $sale->items()->count()];

        DB::transaction(function () use ($sale, $attributes, $items) {
            $sale->update(['status' => SaleStatus::Draft, ...$attributes]);
            $sale->items()->delete();

            foreach ($items as $item) {
                $this->createSaleAction->createItem($sale, $item);
            }

            $this->createSaleAction->recalculateTotals($sale);
        });

        $sale->refresh();

        $this->auditLogger->log(
            AuditAction::Updated,
            auditable: $sale,
            before: $before,
            after: ['total_cents' => $sale->total_cents, 'item_count' => $sale->items()->count()],
            organization: $sale->organization,
        );

        return $sale;
    }
}
