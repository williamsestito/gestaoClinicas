<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Enums\RecordStatus;
use App\Models\ProfessionalTimeBlock;
use App\Support\Auditing\AuditLogger;
use App\Support\Availability\TimeBlockOverlapGuard;

/**
 * Restaura sempre inativo — reativação é uma decisão explícita separada
 * (App\Actions\Organization\ActivateProfessionalTimeBlockAction), que já
 * revalida sobreposição.
 */
class RestoreProfessionalTimeBlockAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(ProfessionalTimeBlock $timeBlock): ProfessionalTimeBlock
    {
        $professional = $timeBlock->professional;

        TimeBlockOverlapGuard::assertNoConflict(
            $professional,
            $timeBlock->scope,
            $timeBlock->unit_id,
            $timeBlock->starts_at,
            $timeBlock->ends_at,
            excludingId: $timeBlock->id,
        );

        $timeBlock->restore();
        $timeBlock->update(['status' => RecordStatus::Inactive]);

        $this->auditLogger->log(
            AuditAction::Restored,
            auditable: $timeBlock,
            after: ['status' => $timeBlock->status->value],
            organization: $professional->organization,
        );

        return $timeBlock;
    }
}
