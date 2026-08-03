<?php

declare(strict_types=1);

namespace App\Actions\Site;

use App\Enums\AuditAction;
use App\Models\Organization;
use App\Models\SiteProfessional;
use App\Support\Auditing\AuditLogger;

/**
 * Remove o vínculo com o cadastro operacional, preservando integralmente o
 * conteúdo promocional (nome, foto, biografia, redes sociais, ordem,
 * status de publicação) — o registro volta a funcionar de forma
 * independente, exatamente como antes de qualquer vínculo existir.
 */
class UnlinkSiteProfessionalAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(SiteProfessional $siteProfessional, Organization $organization): SiteProfessional
    {
        $before = ['professional_id' => $siteProfessional->professional_id];

        $siteProfessional->professional_id = null;
        $siteProfessional->save();

        $this->auditLogger->log(
            AuditAction::Unlinked,
            auditable: $siteProfessional,
            before: $before,
            after: ['professional_id' => null],
            organization: $organization,
        );

        return $siteProfessional;
    }
}
