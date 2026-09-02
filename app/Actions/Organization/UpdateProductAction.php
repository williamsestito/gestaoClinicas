<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Models\Product;
use App\Support\Auditing\AuditLogger;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

class UpdateProductAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /**
     * @param  array{name: string, code: string, barcode: ?string, unit_of_measure: string, cost_cents: ?int, margin_percentage: ?int, price_cents: ?int, max_discount_percentage: ?int, internal_notes: ?string}  $attributes
     */
    public function handle(Product $product, array $attributes): Product
    {
        $before = $product->only(array_keys($attributes));

        try {
            $product->update($attributes);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'code' => 'Já existe um produto com este código nesta clínica.',
            ]);
        }

        $this->auditLogger->log(
            AuditAction::Updated,
            auditable: $product,
            before: $before,
            after: $product->only(array_keys($attributes)),
            organization: $product->organization,
        );

        return $product;
    }
}
