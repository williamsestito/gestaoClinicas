<?php

declare(strict_types=1);

namespace App\Actions\Site;

use App\Enums\AuditAction;
use App\Models\Organization;
use App\Models\Professional;
use App\Models\SiteProfessional;
use App\Support\Auditing\AuditLogger;
use Illuminate\Validation\ValidationException;

/**
 * Vincula uma ficha promocional (`SiteProfessional`, singleton — sem
 * `organization_id`, ver ADR-010) a um cadastro operacional
 * (`Professional`, multiempresa). O vínculo é puramente informativo: não
 * funde os dois cadastros, não copia dados automaticamente e não impede o
 * conteúdo público de continuar existindo se o vínculo for removido depois.
 *
 * Como `site_professionals` não tem `organization_id`, o bloqueio
 * cross-tenant não pode ser expresso como FK composta — é revalidado aqui,
 * exigindo que o profissional pertença à organização ativa informada.
 */
class LinkSiteProfessionalToProfessionalAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(SiteProfessional $siteProfessional, Professional $professional, Organization $organization): SiteProfessional
    {
        if ($professional->organization_id !== $organization->id) {
            throw ValidationException::withMessages([
                'professional_id' => 'Este profissional não pertence à organização ativa.',
            ]);
        }

        if ($professional->trashed()) {
            throw ValidationException::withMessages([
                'professional_id' => 'Não é possível vincular um profissional excluído.',
            ]);
        }

        $before = ['professional_id' => $siteProfessional->professional_id];

        $siteProfessional->professional_id = $professional->id;
        $siteProfessional->save();

        $this->auditLogger->log(
            AuditAction::Linked,
            auditable: $siteProfessional,
            before: $before,
            after: ['professional_id' => $professional->id],
            organization: $organization,
        );

        return $siteProfessional;
    }
}
