<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Enums\RecordStatus;
use App\Models\Professional;
use App\Models\ProfessionalRegistration;
use App\Support\Auditing\AuditLogger;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

class CreateProfessionalRegistrationAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /** @param array{council: string, registration_type: ?string, registration_number: string, state: ?string, issued_at: ?string, expires_at: ?string, internal_notes: ?string} $attributes */
    public function handle(Professional $professional, array $attributes): ProfessionalRegistration
    {
        try {
            $registration = $professional->registrations()->create([
                'organization_id' => $professional->organization_id,
                'council' => $attributes['council'],
                'registration_type' => $attributes['registration_type'],
                'registration_number' => $attributes['registration_number'],
                'state' => $attributes['state'],
                'issued_at' => $attributes['issued_at'],
                'expires_at' => $attributes['expires_at'],
                'internal_notes' => $attributes['internal_notes'],
                'status' => RecordStatus::Active,
                'is_primary' => false,
            ]);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'registration_number' => 'Já existe um vínculo ativo com este registro.',
            ]);
        }

        $this->auditLogger->log(
            AuditAction::Created,
            auditable: $registration,
            after: ['council' => $registration->council, 'registration_number' => $registration->registration_number, 'status' => $registration->status->value],
            organization: $professional->organization,
        );

        return $registration;
    }
}
