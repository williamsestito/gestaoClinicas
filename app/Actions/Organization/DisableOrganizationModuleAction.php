<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Enums\ModuleKey;
use App\Models\Organization;
use App\Models\OrganizationModule;
use App\Support\Auditing\AuditLogger;

/**
 * Desabilitar um módulo nunca apaga a linha de `organization_modules` nem
 * dados criados enquanto o módulo esteve ativo — só impede que ele volte a
 * ser usado em telas/formulários futuros que checarem `hasModule()`.
 */
class DisableOrganizationModuleAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(Organization $organization, ModuleKey $key): OrganizationModule
    {
        $module = OrganizationModule::query()->firstOrNew([
            'organization_id' => $organization->id,
            'module_key' => $key->value,
        ]);

        $wasEnabled = (bool) $module->is_enabled;

        // Ver comentário equivalente em EnableOrganizationModuleAction sobre
        // por que `fill()->save()` é usado em vez de `update()`.
        $module->fill([
            'is_enabled' => false,
            'disabled_at' => now(),
        ])->save();

        if ($wasEnabled) {
            $this->auditLogger->log(
                AuditAction::Deactivated,
                auditable: $module,
                before: ['is_enabled' => $wasEnabled],
                after: ['is_enabled' => false],
                organization: $organization,
            );
        }

        return $module;
    }
}
