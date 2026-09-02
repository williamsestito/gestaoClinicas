<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Models\PatientEmergencyContact;
use App\Support\Auditing\AuditLogger;
use Illuminate\Validation\ValidationException;

/**
 * RN-003: todo paciente precisa de ao menos um contato de emergência ativo
 * — bloqueia a remoção do último.
 */
class DeletePatientEmergencyContactAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(PatientEmergencyContact $contact): void
    {
        $hasOtherContact = $contact->patient->emergencyContacts()
            ->where('id', '!=', $contact->id)
            ->exists();

        if (! $hasOtherContact) {
            throw ValidationException::withMessages([
                'contact' => 'Não é possível remover o único contato de emergência do paciente.',
            ]);
        }

        $contact->delete();

        $this->auditLogger->log(
            AuditAction::Deleted,
            auditable: $contact,
            before: ['name' => $contact->name],
            organization: $contact->organization,
        );
    }
}
