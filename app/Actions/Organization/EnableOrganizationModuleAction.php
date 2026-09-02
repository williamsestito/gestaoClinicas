<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Enums\ModuleKey;
use App\Models\Organization;
use App\Models\OrganizationModule;
use App\Support\Auditing\AuditLogger;

class EnableOrganizationModuleAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(Organization $organization, ModuleKey $key): OrganizationModule
    {
        $module = OrganizationModule::query()->firstOrNew([
            'organization_id' => $organization->id,
            'module_key' => $key->value,
        ]);

        $wasEnabled = (bool) $module->is_enabled;

        // `update()` faz no-op em um model ainda não persistido (retorna
        // `false` sem salvar quando `$this->exists` é falso) — como
        // `firstOrNew` pode retornar um model novo, `fill()->save()` é o
        // único jeito de cobrir tanto a criação quanto a atualização.
        $module->fill([
            'is_enabled' => true,
            'enabled_at' => now(),
        ])->save();

        if (! $wasEnabled) {
            $this->auditLogger->log(
                AuditAction::Activated,
                auditable: $module,
                before: ['is_enabled' => $wasEnabled],
                after: ['is_enabled' => true],
                organization: $organization,
            );
        }

        return $module;
    }
}
