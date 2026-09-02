<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Models\Patient;
use App\Support\Auditing\AuditLogger;

/**
 * Exclusão lógica (SoftDeletes) — nunca física. Diferente de
 * Specialty/Service, não há bloqueio por vínculo ativo: responsáveis e
 * contatos de emergência são entidades dependentes do próprio paciente
 * (não vínculos com outros cadastros fortes), então são preservados junto
 * com o histórico, sem impedir a exclusão.
 */
class DeletePatientAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(Patient $patient): void
    {
        // Também desfaz o vínculo de portal, se houver — sem isso, o
        // paciente some do cadastro administrativo mas a conta de portal
        // ficava travada (índice único "uma conta ativa por paciente"
        // nunca libera o slot, e o dashboard do portal quebrava ao tentar
        // acessar um Patient soft-deletado) — achado de security-review.
        $patient->portalLink?->delete();

        $patient->delete();

        $this->auditLogger->log(
            AuditAction::Deleted,
            auditable: $patient,
            before: ['status' => $patient->status->value],
            organization: $patient->organization,
        );
    }
}
