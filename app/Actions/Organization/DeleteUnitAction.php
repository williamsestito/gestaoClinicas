<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Models\Unit;
use App\Support\Auditing\AuditLogger;
use Illuminate\Validation\ValidationException;

/**
 * Exclusão lógica (SoftDeletes) de uma unidade — nunca exclusão física. A
 * unidade matriz não pode ser excluída sem antes designar outra unidade
 * como matriz (ver SetHeadquartersUnitAction).
 */
class DeleteUnitAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(Unit $unit): void
    {
        if ($unit->is_headquarters) {
            throw ValidationException::withMessages([
                'unit' => 'Não é possível excluir a unidade matriz. Defina outra unidade como matriz antes de continuar.',
            ]);
        }

        $unit->delete();

        $this->auditLogger->log(
            AuditAction::Deleted,
            auditable: $unit,
            before: ['status' => $unit->status->value],
            organization: $unit->organization,
            unit: $unit,
        );
    }
}
