<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Enums\RecordStatus;
use App\Models\ProfessionalRegistration;
use App\Support\Auditing\AuditLogger;
use Illuminate\Validation\ValidationException;

/**
 * Restaura sempre inativo e nunca como principal — reativação e definição
 * de principal são decisões explícitas separadas.
 */
class RestoreProfessionalRegistrationAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(ProfessionalRegistration $registration): ProfessionalRegistration
    {
        $conflict = ProfessionalRegistration::query()
            ->where('organization_id', $registration->organization_id)
            ->where('council', $registration->council)
            ->where('registration_number', $registration->registration_number)
            ->where('state', $registration->state?->value)
            ->where('id', '!=', $registration->id)
            ->exists();

        if ($conflict) {
            throw ValidationException::withMessages([
                'registration' => 'Não foi possível restaurar porque já existe um registro ativo com os mesmos dados.',
            ]);
        }

        $registration->restore();
        $registration->update(['status' => RecordStatus::Inactive, 'is_primary' => false]);

        $this->auditLogger->log(
            AuditAction::Restored,
            auditable: $registration,
            after: ['status' => $registration->status->value],
            organization: $registration->organization,
        );

        return $registration;
    }
}
