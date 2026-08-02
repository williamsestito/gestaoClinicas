<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Enums\RecordStatus;
use App\Models\Professional;
use App\Models\ProfessionalUnit;
use App\Support\Auditing\AuditLogger;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

class AssignProfessionalToUnitAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /** @param array<string, mixed> $attributes */
    public function handle(Professional $professional, array $attributes): ProfessionalUnit
    {
        $unitId = (string) $attributes['unit_id'];

        try {
            $link = $professional->unitLinks()->create([
                'organization_id' => $professional->organization_id,
                'unit_id' => $unitId,
                'is_primary' => false,
                'status' => RecordStatus::Active,
                'starts_on' => $attributes['starts_on'] ?? null,
                'ends_on' => $attributes['ends_on'] ?? null,
            ]);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'unit_id' => 'Já existe um vínculo ativo com esta unidade.',
            ]);
        }

        $this->auditLogger->log(
            AuditAction::Created,
            auditable: $link,
            after: ['professional_id' => $professional->id, 'unit_id' => $unitId, 'status' => $link->status->value],
            organization: $professional->organization,
        );

        return $link;
    }
}
