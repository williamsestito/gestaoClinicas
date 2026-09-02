<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Enums\RecordStatus;
use App\Models\ProfessionalTimeBlock;
use App\Support\Auditing\AuditLogger;
use App\Support\Availability\TimeBlockOverlapGuard;
use Illuminate\Support\Facades\DB;

/**
 * Reativar um bloqueio pode reintroduzir um conflito criado enquanto ele
 * estava inativo — revalida sobreposição antes de ativar. Não exige que o
 * profissional esteja ativo: é possível preparar um bloqueio futuro antes
 * mesmo do profissional ser ativado operacionalmente.
 */
class ActivateProfessionalTimeBlockAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(ProfessionalTimeBlock $timeBlock): ProfessionalTimeBlock
    {
        $professional = $timeBlock->professional;

        DB::transaction(function () use ($timeBlock, $professional) {
            TimeBlockOverlapGuard::assertNoConflict(
                $professional,
                $timeBlock->scope,
                $timeBlock->unit_id,
                $timeBlock->starts_at,
                $timeBlock->ends_at,
                excludingId: $timeBlock->id,
            );

            $timeBlock->update(['status' => RecordStatus::Active]);
        });

        $this->auditLogger->log(
            AuditAction::Activated,
            auditable: $timeBlock,
            before: ['status' => RecordStatus::Inactive->value],
            after: ['status' => RecordStatus::Active->value],
            organization: $professional->organization,
        );

        return $timeBlock;
    }
}
