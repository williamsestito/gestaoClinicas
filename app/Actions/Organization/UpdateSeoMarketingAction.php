<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Models\Organization;
use App\Models\SiteSetting;
use App\Support\Auditing\AuditLogger;

class UpdateSeoMarketingAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /** @param  array<string, mixed>  $data */
    public function handle(?SiteSetting $siteSetting, array $data, ?Organization $organization = null): SiteSetting
    {
        $record = $siteSetting ?? new SiteSetting;
        $before = $record->exists ? $record->only(array_keys($data)) : [];

        if (! $record->exists && blank($record->title)) {
            // "title" é obrigatório na tabela (usado pelo conteúdo do site).
            // Se SEO for configurado antes de "Site da clínica" existir,
            // usamos o nome da organização como valor inicial razoável —
            // o admin pode ajustá-lo depois na página de conteúdo.
            $record->title = $organization?->name ?: 'Minha Clínica';
        }

        $record->fill($data);
        $record->save();

        $this->auditLogger->log(
            AuditAction::Updated,
            auditable: $record,
            before: $before,
            after: $record->only(array_keys($data)),
            organization: $organization,
        );

        return $record;
    }
}
