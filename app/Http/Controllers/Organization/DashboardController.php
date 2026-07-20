<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organization;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\LegalEntity;
use App\Models\Organization;
use App\Models\SiteSetting;
use App\Support\Tenancy\TenantContext;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(TenantContext $tenant): Response
    {
        $organization = $tenant->organization();
        $primaryLegalEntity = $organization?->primaryLegalEntity()->first();
        $siteSetting = SiteSetting::query()->first();

        $usersCount = 0;
        $activeUsersCount = 0;
        $inactiveUsersCount = 0;

        if ($organization) {
            $usersCount = $organization->memberships()->count();
            $activeUsersCount = $organization->memberships()
                ->whereHas('user', fn ($query) => $query->where('is_active', true))
                ->count();
            $inactiveUsersCount = $usersCount - $activeUsersCount;
        }

        $recentActivity = $organization
            ? AuditLog::query()
                ->where('organization_id', $organization->id)
                ->with('actor:id,name')
                ->latest('created_at')
                ->limit(5)
                ->get(['id', 'actor_user_id', 'action', 'auditable_type', 'created_at'])
                ->map(fn (AuditLog $log) => [
                    'id' => $log->id,
                    'actor' => $log->actor ? $log->actor->name : 'Sistema',
                    'action' => $log->action->label(),
                    'entity' => class_basename((string) $log->auditable_type),
                    'created_at' => $log->created_at->toIso8601String(),
                ])
            : collect();

        $domainConfigured = $siteSetting !== null && filled($siteSetting->official_domain);
        $seoConfigured = $siteSetting !== null && filled($siteSetting->meta_title) && filled($siteSetting->meta_description);

        return Inertia::render('Dashboard', [
            'organizationName' => $organization?->name,
            'unitsCount' => $organization?->units()->count() ?? 0,
            'usersCount' => $usersCount,
            'activeUsersCount' => $activeUsersCount,
            'inactiveUsersCount' => $inactiveUsersCount,
            'legalEntitiesCount' => $organization?->legalEntities()->count() ?? 0,
            'primaryLegalEntity' => $primaryLegalEntity ? [
                'legal_name' => $primaryLegalEntity->legal_name,
                'trade_name' => $primaryLegalEntity->trade_name,
            ] : null,
            'domainConfigured' => $domainConfigured,
            'seoConfigured' => $seoConfigured,
            'recentActivity' => $recentActivity,
            'pendingSetupItems' => $this->pendingSetupItems($organization, $primaryLegalEntity, $siteSetting),
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function pendingSetupItems(
        ?Organization $organization,
        ?LegalEntity $primaryLegalEntity,
        ?SiteSetting $siteSetting,
    ): array {
        $items = [];

        if (! $primaryLegalEntity) {
            $items[] = 'Cadastre uma entidade legal principal.';
        }

        if ($organization && ! $organization->headquarters()->exists()) {
            $items[] = 'Defina uma unidade matriz.';
        }

        if (! $siteSetting?->official_domain) {
            $items[] = 'Configure o domínio oficial do site.';
        }

        if (! $siteSetting || blank($siteSetting->meta_title) || blank($siteSetting->meta_description)) {
            $items[] = 'Complete os metadados de SEO da página pública.';
        }

        return $items;
    }
}
