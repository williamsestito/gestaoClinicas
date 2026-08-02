<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Enums\RecordStatus;
use App\Models\ProfessionalRegistration;
use App\Support\Auditing\AuditLogger;
use Illuminate\Validation\ValidationException;

class DeactivateProfessionalRegistrationAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(ProfessionalRegistration $registration): ProfessionalRegistration
    {
        if ($registration->is_primary && $this->hasOtherActiveRegistrations($registration)) {
            throw ValidationException::withMessages([
                'registration' => 'Não é possível inativar o registro principal sem definir um substituto.',
            ]);
        }

        $previousStatus = $registration->status;

        $registration->update(['status' => RecordStatus::Inactive]);

        $this->auditLogger->log(
            AuditAction::Deactivated,
            auditable: $registration,
            before: ['status' => $previousStatus->value],
            after: ['status' => $registration->status->value],
            organization: $registration->organization,
        );

        return $registration;
    }

    private function hasOtherActiveRegistrations(ProfessionalRegistration $registration): bool
    {
        return $registration->professional->registrations()
            ->where('id', '!=', $registration->id)
            ->where('status', RecordStatus::Active)
            ->exists();
    }
}
