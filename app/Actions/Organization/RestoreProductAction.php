<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Enums\RecordStatus;
use App\Models\Product;
use App\Support\Auditing\AuditLogger;
use Illuminate\Validation\ValidationException;

/** Restaura sempre com status inativo — reativação é uma decisão explícita separada. */
class RestoreProductAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(Product $product): Product
    {
        $conflict = Product::query()
            ->where('organization_id', $product->organization_id)
            ->where('id', '!=', $product->id)
            ->where('code', $product->code)
            ->exists();

        if ($conflict) {
            throw ValidationException::withMessages([
                'product' => 'Não foi possível restaurar porque já existe um registro ativo com os mesmos dados.',
            ]);
        }

        $product->restore();
        $product->update(['status' => RecordStatus::Inactive]);

        $this->auditLogger->log(
            AuditAction::Restored,
            auditable: $product,
            after: ['status' => $product->status->value],
            organization: $product->organization,
        );

        return $product;
    }
}
