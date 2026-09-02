<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Enums\RecordStatus;
use App\Models\Organization;
use App\Models\Product;
use App\Support\Auditing\AuditLogger;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

class CreateProductAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /**
     * @param  array{name: string, code: string, barcode: ?string, unit_of_measure: string, cost_cents: ?int, margin_percentage: ?int, price_cents: ?int, max_discount_percentage: ?int, internal_notes: ?string}  $attributes
     */
    public function handle(Organization $organization, array $attributes): Product
    {
        try {
            $product = $organization->products()->create([
                ...$attributes,
                'status' => RecordStatus::Active,
            ]);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'code' => 'Já existe um produto com este código nesta clínica.',
            ]);
        }

        $this->auditLogger->log(
            AuditAction::Created,
            auditable: $product,
            after: [
                'name' => $product->name,
                'code' => $product->code,
                'status' => $product->status->value,
            ],
            organization: $organization,
        );

        return $product;
    }
}
