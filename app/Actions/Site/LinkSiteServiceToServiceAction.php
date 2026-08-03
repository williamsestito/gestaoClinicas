<?php

declare(strict_types=1);

namespace App\Actions\Site;

use App\Enums\AuditAction;
use App\Models\Organization;
use App\Models\Service;
use App\Models\SiteService;
use App\Support\Auditing\AuditLogger;
use Illuminate\Validation\ValidationException;

/**
 * Vincula um item promocional (`SiteService`, singleton — sem
 * `organization_id`, ver ADR-010) a um serviço operacional (`Service`,
 * multiempresa). Puramente informativo — não funde os cadastros, não copia
 * dados automaticamente. Como `site_services` não tem `organization_id`, o
 * bloqueio cross-tenant é revalidado aqui (não expressável como FK
 * composta).
 */
class LinkSiteServiceToServiceAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(SiteService $siteService, Service $service, Organization $organization): SiteService
    {
        if ($service->organization_id !== $organization->id) {
            throw ValidationException::withMessages([
                'service_id' => 'Este serviço não pertence à organização ativa.',
            ]);
        }

        if ($service->trashed()) {
            throw ValidationException::withMessages([
                'service_id' => 'Não é possível vincular um serviço excluído.',
            ]);
        }

        $before = ['service_id' => $siteService->service_id];

        $siteService->service_id = $service->id;
        $siteService->save();

        $this->auditLogger->log(
            AuditAction::Linked,
            auditable: $siteService,
            before: $before,
            after: ['service_id' => $service->id],
            organization: $organization,
        );

        return $siteService;
    }
}
