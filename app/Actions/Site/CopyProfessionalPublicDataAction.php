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
 * Cópia controlada e explícita de dados operacionais para o conteúdo
 * promocional já vinculado — nunca automática, nunca em sincronização
 * contínua. Allowlist fixa (nome e biografia): nunca copia documento,
 * e-mail, telefone, número de registro, jornada ou ausências. O chamador
 * decide quais campos da allowlist efetivamente copiar (`$fields`),
 * permitindo que o administrador confirme campo a campo antes de
 * sobrescrever texto promocional existente.
 */
class CopyProfessionalPublicDataAction
{
    /** @var list<string> */
    private const ALLOWED_FIELDS = ['name', 'bio'];

    public function __construct(private readonly AuditLogger $auditLogger) {}

    /** @param  list<string>  $fields */
    public function handle(SiteProfessional $siteProfessional, Professional $professional, Organization $organization, array $fields): SiteProfessional
    {
        if ($siteProfessional->professional_id !== $professional->id) {
            throw ValidationException::withMessages([
                'professional_id' => 'A cópia só é permitida entre registros já vinculados.',
            ]);
        }

        if ($professional->organization_id !== $organization->id) {
            throw ValidationException::withMessages([
                'professional_id' => 'Este profissional não pertence à organização ativa.',
            ]);
        }

        $invalid = array_diff($fields, self::ALLOWED_FIELDS);

        if ($invalid !== []) {
            throw ValidationException::withMessages([
                'fields' => 'Campo não permitido para cópia: '.implode(', ', $invalid).'.',
            ]);
        }

        $sourceValues = [
            'name' => $professional->display_name,
            'bio' => $professional->bio,
        ];

        $before = [];
        $after = [];

        foreach ($fields as $field) {
            $before[$field] = $siteProfessional->getAttribute($field);
            $after[$field] = $sourceValues[$field];
        }

        $siteProfessional->fill($after);
        $siteProfessional->save();

        $this->auditLogger->log(
            AuditAction::Copied,
            auditable: $siteProfessional,
            before: $before,
            after: $after,
            organization: $organization,
        );

        return $siteProfessional;
    }
}
