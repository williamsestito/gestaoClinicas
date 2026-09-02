<?php

declare(strict_types=1);

namespace App\Actions\Site;

use App\Enums\AuditAction;
use App\Models\Organization;
use App\Models\SiteService;
use App\Support\Auditing\AuditLogger;

/**
 * Remove o vínculo com o serviço operacional, preservando integralmente o
 * conteúdo promocional — o registro volta a funcionar de forma
 * independente.
 */
class UnlinkSiteServiceAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(SiteService $siteService, Organization $organization): SiteService
    {
        $before = ['service_id' => $siteService->service_id];

        $siteService->service_id = null;
        $siteService->save();

        $this->auditLogger->log(
            AuditAction::Unlinked,
            auditable: $siteService,
            before: $before,
            after: ['service_id' => null],
            organization: $organization,
        );

        return $siteService;
    }
}
