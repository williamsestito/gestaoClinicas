<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Enums\RecordStatus;
use App\Models\Professional;
use App\Models\ProfessionalSpecialty;
use App\Support\Auditing\AuditLogger;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

class AssignSpecialtyToProfessionalAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(Professional $professional, string $specialtyId): ProfessionalSpecialty
    {
        try {
            $link = $professional->specialtyLinks()->create([
                'organization_id' => $professional->organization_id,
                'specialty_id' => $specialtyId,
                'is_primary' => false,
                'status' => RecordStatus::Active,
            ]);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'specialty_id' => 'Já existe um vínculo ativo com esta especialidade.',
            ]);
        }

        $this->auditLogger->log(
            AuditAction::Created,
            auditable: $link,
            after: ['professional_id' => $professional->id, 'specialty_id' => $specialtyId, 'status' => $link->status->value],
            organization: $professional->organization,
        );

        return $link;
    }
}
