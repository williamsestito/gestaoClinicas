<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Enums\SaleStatus;
use App\Models\Sale;
use App\Support\Auditing\AuditLogger;
use Illuminate\Validation\ValidationException;

/**
 * Cancela uma venda confirmada — nunca exclui (RN-009/RN-017). Não
 * reverte automaticamente um `SessionPackage` criado a partir de um item
 * desta venda; o estorno de verdade (financeiro) fica para a Etapa 6 —
 * ver docs/modules/sales.md.
 */
class CancelSaleAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(Sale $sale, string $reason): Sale
    {
        if ($sale->status !== SaleStatus::Confirmed) {
            throw ValidationException::withMessages([
                'sale' => 'Só é possível cancelar uma venda confirmada.',
            ]);
        }

        $sale->update([
            'status' => SaleStatus::Cancelled,
            'cancellation_reason' => $reason,
        ]);

        $this->auditLogger->log(
            AuditAction::Cancelled,
            auditable: $sale,
            before: ['status' => SaleStatus::Confirmed->value],
            after: ['status' => $sale->status->value, 'cancellation_reason' => $reason],
            organization: $sale->organization,
            unit: $sale->unit,
        );

        return $sale;
    }
}
