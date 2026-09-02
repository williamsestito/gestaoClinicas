<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Models\Service;
use App\Support\Auditing\AuditLogger;
use Illuminate\Validation\ValidationException;

/**
 * Exclusão lógica — nunca física. Bloqueada quando existem profissionais
 * ativamente vinculados ao serviço: excluir sem desvincular deixaria essas
 * telas referenciando um serviço excluído. Os vínculos próprios do serviço
 * (especialidades, unidades) não bloqueiam a exclusão — são apenas
 * preservados junto com o histórico.
 */
class DeleteServiceAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(Service $service): void
    {
        if ($service->professionalLinks()->count() > 0) {
            throw ValidationException::withMessages([
                'service' => 'Não é possível excluir este serviço porque ele está vinculado a profissionais. Remova os vínculos antes de excluir.',
            ]);
        }

        $service->delete();

        $this->auditLogger->log(
            AuditAction::Deleted,
            auditable: $service,
            before: ['status' => $service->status->value],
            organization: $service->organization,
        );
    }
}
