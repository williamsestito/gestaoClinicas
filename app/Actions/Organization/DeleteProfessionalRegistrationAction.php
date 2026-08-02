<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Enums\RecordStatus;
use App\Models\ProfessionalRegistration;
use App\Support\Auditing\AuditLogger;
use Illuminate\Validation\ValidationException;

class DeleteProfessionalRegistrationAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(ProfessionalRegistration $registration): void
    {
        if ($registration->is_primary && $this->hasOtherActiveRegistrations($registration)) {
            throw ValidationException::withMessages([
                'registration' => 'Não é possível excluir o registro principal sem definir um substituto.',
            ]);
        }

        $registration->delete();

        $this->auditLogger->log(
            AuditAction::Deleted,
            auditable: $registration,
            before: ['status' => $registration->status->value, 'is_primary' => $registration->is_primary],
            organization: $registration->organization,
        );
    }

    private function hasOtherActiveRegistrations(ProfessionalRegistration $registration): bool
    {
        return $registration->professional->registrations()
            ->where('id', '!=', $registration->id)
            ->where('status', RecordStatus::Active)
            ->exists();
    }
}
