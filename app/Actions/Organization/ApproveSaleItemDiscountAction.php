<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Models\SaleItem;
use App\Models\User;
use App\Support\Auditing\AuditLogger;
use Illuminate\Validation\ValidationException;

/**
 * RN-010/RN-011: aprovação feita pelo próprio usuário autorizador, na
 * própria sessão (a rota exige `RequirePassword` antes de chegar aqui —
 * ver routes/clinic.php). O log de auditoria registra solicitante e
 * aprovador separadamente, já que `AuditLogger` captura o aprovador como
 * ator automaticamente.
 */
class ApproveSaleItemDiscountAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(SaleItem $item, User $approver, string $justification): SaleItem
    {
        if (! $item->isPendingApproval()) {
            throw ValidationException::withMessages([
                'item' => 'Este item não está aguardando aprovação de desconto.',
            ]);
        }

        $item->update([
            'approved_by' => $approver->id,
            'approved_at' => now(),
            'approval_justification' => $justification,
        ]);

        $this->auditLogger->log(
            AuditAction::Approved,
            auditable: $item,
            after: [
                'requester_user_id' => $item->sale->created_by,
                'approver_user_id' => $approver->id,
                'original_price_cents' => $item->quantity * $item->unit_price_cents,
                'discount_percentage' => $item->discount_percentage,
                'final_price_cents' => $item->final_price_cents,
                'justification' => $justification,
            ],
            organization: $item->organization,
            unit: $item->sale->unit,
        );

        return $item;
    }
}
