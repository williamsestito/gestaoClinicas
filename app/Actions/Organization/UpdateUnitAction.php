<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Models\Unit;
use App\Support\Auditing\AuditLogger;

class UpdateUnitAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /** @param array<string, mixed> $attributes */
    public function handle(Unit $unit, array $attributes): Unit
    {
        $allowed = collect($attributes)->only([
            'name', 'phone', 'whatsapp', 'email', 'timezone',
        ])->all();

        $before = $unit->only(array_keys($allowed));

        $unit->update($allowed);

        $this->auditLogger->log(
            AuditAction::Updated,
            auditable: $unit,
            before: $before,
            after: $unit->only(array_keys($allowed)),
            organization: $unit->organization,
            unit: $unit,
        );

        return $unit;
    }
}
