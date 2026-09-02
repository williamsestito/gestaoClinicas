<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Models\ProfessionalRegistration;
use App\Support\Auditing\AuditLogger;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SetPrimaryProfessionalRegistrationAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(ProfessionalRegistration $registration): ProfessionalRegistration
    {
        try {
            DB::transaction(function () use ($registration) {
                $registration->professional->registrations()
                    ->where('is_primary', true)
                    ->where('id', '!=', $registration->id)
                    ->update(['is_primary' => false]);

                $registration->update(['is_primary' => true]);
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'registration' => 'Não foi possível definir o registro principal — tente novamente.',
            ]);
        }

        $this->auditLogger->log(
            AuditAction::PrimaryProfessionalRegistrationChanged,
            auditable: $registration->professional,
            after: ['professional_registration_id' => $registration->id],
            organization: $registration->organization,
        );

        return $registration->fresh();
    }
}
