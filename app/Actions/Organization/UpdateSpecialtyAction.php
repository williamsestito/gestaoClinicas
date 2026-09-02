<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Models\Specialty;
use App\Support\Auditing\AuditLogger;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

class UpdateSpecialtyAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /** @param array<string, mixed> $attributes */
    public function handle(Specialty $specialty, array $attributes): Specialty
    {
        $allowed = [
            'name' => $attributes['name'],
            'code' => $attributes['code'] ?? null,
            'description' => $attributes['description'] ?? null,
            'display_order' => $attributes['display_order'] ?? $specialty->display_order,
        ];

        $before = $specialty->only(array_keys($allowed));

        try {
            $specialty->update($allowed);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'name' => 'Já existe uma especialidade com este nome ou código nesta clínica.',
            ]);
        }

        $this->auditLogger->log(
            AuditAction::Updated,
            auditable: $specialty,
            before: $before,
            after: $specialty->only(array_keys($allowed)),
            organization: $specialty->organization,
        );

        return $specialty;
    }
}
