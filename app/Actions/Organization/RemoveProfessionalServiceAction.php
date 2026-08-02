<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Models\ProfessionalService;
use App\Support\Auditing\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Exclusão lógica do vínculo — nunca exclui o serviço nem o profissional.
 * Os vínculos de unidade selecionada (professional_service_unit) também são
 * excluídos logicamente junto, pois só fazem sentido associados a este
 * vínculo.
 */
class RemoveProfessionalServiceAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(ProfessionalService $link): void
    {
        DB::transaction(function () use ($link) {
            $link->unitLinks()->get()->each->delete();
            $link->delete();
        });

        $this->auditLogger->log(
            AuditAction::Deleted,
            auditable: $link,
            before: ['status' => $link->status->value],
            organization: $link->organization,
        );
    }
}
