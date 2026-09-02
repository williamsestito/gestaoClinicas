<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Models\Unit;
use App\Support\Auditing\AuditLogger;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

class RestoreUnitAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(Unit $unit): void
    {
        try {
            $unit->restore();
        } catch (QueryException $exception) {
            if (str_contains($exception->getMessage(), 'units_one_headquarters_per_org')) {
                throw ValidationException::withMessages([
                    'unit' => 'Não é possível restaurar: já existe uma unidade matriz ativa nesta organização.',
                ]);
            }

            throw $exception;
        }

        $this->auditLogger->log(
            AuditAction::Restored,
            auditable: $unit,
            after: ['status' => $unit->status->value],
            organization: $unit->organization,
            unit: $unit,
        );
    }
}
