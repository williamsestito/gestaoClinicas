<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Enums\RecordStatus;
use App\Models\Product;
use App\Support\Auditing\AuditLogger;

class ActivateProductAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(Product $product): Product
    {
        $previousStatus = $product->status;

        $product->update(['status' => RecordStatus::Active]);

        $this->auditLogger->log(
            AuditAction::Activated,
            auditable: $product,
            before: ['status' => $previousStatus->value],
            after: ['status' => $product->status->value],
            organization: $product->organization,
        );

        return $product;
    }
}
