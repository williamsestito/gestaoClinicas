<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Models\ProfessionalRegistration;
use App\Support\Auditing\AuditLogger;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

class UpdateProfessionalRegistrationAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /** @param array<string, mixed> $attributes */
    public function handle(ProfessionalRegistration $registration, array $attributes): ProfessionalRegistration
    {
        $before = $registration->only(array_keys($attributes));

        try {
            $registration->update($attributes);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'registration_number' => 'Já existe um vínculo ativo com este registro.',
            ]);
        }

        $this->auditLogger->log(
            AuditAction::Updated,
            auditable: $registration,
            before: $before,
            after: $registration->only(array_keys($attributes)),
            organization: $registration->organization,
        );

        return $registration;
    }
}
