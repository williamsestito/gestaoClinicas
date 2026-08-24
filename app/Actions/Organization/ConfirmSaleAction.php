<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Enums\SaleItemType;
use App\Enums\SaleStatus;
use App\Models\Sale;
use App\Support\Auditing\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConfirmSaleAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly CreateSessionPackageAction $createSessionPackageAction,
    ) {}

    public function handle(Sale $sale): Sale
    {
        if ($sale->status === SaleStatus::Confirmed || $sale->status === SaleStatus::Cancelled) {
            throw ValidationException::withMessages([
                'sale' => 'Esta venda já foi confirmada ou cancelada.',
            ]);
        }

        if ($sale->hasPendingApprovalItems()) {
            throw ValidationException::withMessages([
                'sale' => 'Não é possível confirmar: há itens aguardando aprovação de desconto.',
            ]);
        }

        DB::transaction(function () use ($sale) {
            $sale->update(['status' => SaleStatus::Confirmed]);

            foreach ($sale->items()->where('item_type', SaleItemType::ServicePackage)->get() as $item) {
                $this->createSessionPackageAction->handle($sale->patient, [
                    'service_id' => $item->service_id,
                    'origin_sale_item_id' => $item->id,
                    'total_sessions' => $item->session_count,
                ]);
            }
        });

        $this->auditLogger->log(
            AuditAction::Confirmed,
            auditable: $sale,
            after: ['status' => $sale->status->value, 'total_cents' => $sale->total_cents],
            organization: $sale->organization,
            unit: $sale->unit,
        );

        return $sale;
    }
}
