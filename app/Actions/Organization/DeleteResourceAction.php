<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AppointmentStatus;
use App\Enums\AuditAction;
use App\Models\SharedResource;
use App\Support\Auditing\AuditLogger;
use Illuminate\Validation\ValidationException;

/**
 * Exclusão lógica (SoftDeletes) — nunca física. Bloqueada quando existem
 * agendamentos não finais vinculados: excluir o recurso deixaria esses
 * agendamentos referenciando um recurso excluído, um estado incoerente.
 */
class DeleteResourceAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(SharedResource $resource): void
    {
        $finalStatuses = array_filter(
            AppointmentStatus::cases(),
            fn (AppointmentStatus $status) => $status->isFinal(),
        );

        $linkedNonFinal = $resource->appointments()
            ->whereNotIn('appointments.status', array_map(fn (AppointmentStatus $status) => $status->value, $finalStatuses))
            ->count();

        if ($linkedNonFinal > 0) {
            throw ValidationException::withMessages([
                'resource' => 'Não é possível excluir este recurso porque ele está vinculado a agendamentos ainda não finalizados.',
            ]);
        }

        $resource->delete();

        $this->auditLogger->log(
            AuditAction::Deleted,
            auditable: $resource,
            before: ['status' => $resource->status->value],
            organization: $resource->organization,
            unit: $resource->unit,
        );
    }
}
