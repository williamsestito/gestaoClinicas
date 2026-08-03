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
 * Cópia controlada e explícita de dados operacionais para o item
 * promocional já vinculado. Allowlist fixa (nome, descrição, duração e
 * preço público) — nunca copia código interno, observações internas,
 * buffers de agenda ou o escopo de disponibilidade por unidade. O preço
 * exige inclusão explícita em `$fields` — nunca é copiado apenas porque o
 * serviço operacional tem um preço padrão configurado.
 */
class CopyServicePublicDataAction
{
    /** @var list<string> */
    private const ALLOWED_FIELDS = ['name', 'description', 'duration_minutes', 'starting_price_cents'];

    public function __construct(private readonly AuditLogger $auditLogger) {}

    /** @param  list<string>  $fields */
    public function handle(SiteService $siteService, Service $service, Organization $organization, array $fields): SiteService
    {
        if ($siteService->service_id !== $service->id) {
            throw ValidationException::withMessages([
                'service_id' => 'A cópia só é permitida entre registros já vinculados.',
            ]);
        }

        if ($service->organization_id !== $organization->id) {
            throw ValidationException::withMessages([
                'service_id' => 'Este serviço não pertence à organização ativa.',
            ]);
        }

        $invalid = array_diff($fields, self::ALLOWED_FIELDS);

        if ($invalid !== []) {
            throw ValidationException::withMessages([
                'fields' => 'Campo não permitido para cópia: '.implode(', ', $invalid).'.',
            ]);
        }

        $sourceValues = [
            'name' => $service->name,
            'description' => $service->description,
            'duration_minutes' => $service->default_duration_minutes,
            'starting_price_cents' => $service->default_price_cents,
        ];

        $before = [];
        $after = [];

        foreach ($fields as $field) {
            $before[$field] = $siteService->getAttribute($field);
            $after[$field] = $sourceValues[$field];
        }

        $siteService->fill($after);
        $siteService->save();

        $this->auditLogger->log(
            AuditAction::Copied,
            auditable: $siteService,
            before: $before,
            after: $after,
            organization: $organization,
        );

        return $siteService;
    }
}
