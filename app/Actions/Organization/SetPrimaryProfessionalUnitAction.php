<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Models\ProfessionalUnit;
use App\Support\Auditing\AuditLogger;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Troca a unidade principal de um profissional dentro de uma transação:
 * remove o indicador da anterior (se houver) e define a nova. O índice
 * único parcial `professional_unit_one_primary_per_professional` é a
 * defesa final contra corrida entre requisições concorrentes.
 */
class SetPrimaryProfessionalUnitAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(ProfessionalUnit $link): ProfessionalUnit
    {
        try {
            DB::transaction(function () use ($link) {
                $link->professional->unitLinks()
                    ->where('is_primary', true)
                    ->where('id', '!=', $link->id)
                    ->update(['is_primary' => false]);

                $link->update(['is_primary' => true]);
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'unit' => 'Não foi possível definir a unidade principal — tente novamente.',
            ]);
        }

        $this->auditLogger->log(
            AuditAction::PrimaryProfessionalUnitChanged,
            auditable: $link->professional,
            after: ['unit_id' => $link->unit_id],
            organization: $link->organization,
        );

        return $link->fresh();
    }
}
