<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Models\Specialty;
use App\Support\Auditing\AuditLogger;
use Illuminate\Validation\ValidationException;

/**
 * Exclusão lógica (SoftDeletes) — nunca física. Bloqueada quando existem
 * vínculos ativos com profissionais ou serviços: excluir sem desvincular
 * deixaria essas telas referenciando uma especialidade excluída, um estado
 * incoerente. O administrador precisa desvincular antes.
 */
class DeleteSpecialtyAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(Specialty $specialty): void
    {
        $linkedProfessionals = $specialty->professionalLinks()->count();
        $linkedServices = $specialty->serviceLinks()->count();

        if ($linkedProfessionals > 0 || $linkedServices > 0) {
            throw ValidationException::withMessages([
                'specialty' => 'Não é possível excluir esta especialidade porque ela está vinculada a profissionais ou serviços. Remova os vínculos antes de excluir.',
            ]);
        }

        $specialty->delete();

        $this->auditLogger->log(
            AuditAction::Deleted,
            auditable: $specialty,
            before: ['status' => $specialty->status->value],
            organization: $specialty->organization,
        );
    }
}
