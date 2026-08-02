<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Models\Professional;
use App\Support\Auditing\AuditLogger;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

/**
 * Vincula um profissional a um usuário já existente. Nunca cria o usuário,
 * nunca envia convite, nunca altera senha/e-mail, nunca concede papel ou
 * membership — o vínculo é puramente informativo. Acesso ao sistema
 * continua dependendo exclusivamente de OrganizationMembership/Role (ver
 * App\Support\Authorization\PermissionChecker).
 */
class LinkProfessionalUserAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(Professional $professional, int $userId): Professional
    {
        $previousUserId = $professional->user_id;

        try {
            $professional->update(['user_id' => $userId]);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'user_id' => 'Este usuário já está vinculado a outro profissional nesta clínica.',
            ]);
        }

        $this->auditLogger->log(
            AuditAction::Updated,
            auditable: $professional,
            before: ['user_id' => $previousUserId],
            after: ['user_id' => $professional->user_id],
            organization: $professional->organization,
        );

        return $professional;
    }
}
