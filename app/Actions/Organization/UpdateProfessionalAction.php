<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Models\Professional;
use App\Support\Auditing\AuditLogger;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

class UpdateProfessionalAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /** @param array<string, mixed> $attributes */
    public function handle(Professional $professional, array $attributes): Professional
    {
        $before = $professional->only(array_keys($attributes));

        try {
            $professional->update($attributes);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'document' => 'Já existe um profissional com este documento nesta clínica.',
            ]);
        }

        $this->auditLogger->log(
            AuditAction::Updated,
            auditable: $professional,
            before: $before,
            after: $professional->only(array_keys($attributes)),
            organization: $professional->organization,
        );

        return $professional;
    }
}
