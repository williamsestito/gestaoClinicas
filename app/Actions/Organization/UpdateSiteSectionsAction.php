<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Models\Organization;
use App\Models\SiteSetting;
use App\Support\Auditing\AuditLogger;
use App\Support\Site\LandingSections;

/**
 * Atualiza a ordem e a ativação das seções da landing pública. Sempre
 * normaliza a entrada com LandingSections::normalize — nunca grava um tipo
 * de seção desconhecido, nunca perde um tipo conhecido que faltar.
 */
class UpdateSiteSectionsAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /**
     * @param  list<array{type: string, active: bool}>  $sections
     */
    public function handle(?SiteSetting $siteSetting, array $sections, ?Organization $organization = null): SiteSetting
    {
        $record = $siteSetting ?? new SiteSetting(['title' => $organization?->name ?: 'Minha Clínica']);
        $before = $record->sections_config;

        $record->sections_config = LandingSections::normalize($sections);
        $record->save();

        $this->auditLogger->log(
            AuditAction::Updated,
            auditable: $record,
            before: ['sections_config' => $before],
            after: ['sections_config' => $record->sections_config],
            organization: $organization,
        );

        return $record;
    }
}
