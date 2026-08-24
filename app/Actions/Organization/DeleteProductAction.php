<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Models\Product;
use App\Support\Auditing\AuditLogger;
use Illuminate\Validation\ValidationException;

/**
 * Exclusão lógica — nunca física. Bloqueada quando o produto já foi
 * vendido: excluir sem preservar o vínculo deixaria vendas históricas
 * referenciando um produto excluído sem explicação.
 */
class DeleteProductAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(Product $product): void
    {
        if ($product->saleItems()->count() > 0) {
            throw ValidationException::withMessages([
                'product' => 'Não é possível excluir este produto porque ele já foi vendido. Inative-o em vez de excluir.',
            ]);
        }

        $product->delete();

        $this->auditLogger->log(
            AuditAction::Deleted,
            auditable: $product,
            before: ['status' => $product->status->value],
            organization: $product->organization,
        );
    }
}
