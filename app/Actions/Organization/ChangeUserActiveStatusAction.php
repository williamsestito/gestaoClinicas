<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Models\OrganizationMembership;
use App\Support\Auditing\AuditLogger;
use App\Support\Tenancy\OwnershipGuard;
use Illuminate\Validation\ValidationException;

/**
 * Ativa/inativa o acesso do usuário à plataforma (`users.is_active` —
 * bloqueia login em qualquer organização, é um dado global do usuário).
 * Nunca permite inativar o único proprietário ativo da organização.
 * A sessão do usuário inativado é encerrada no próximo request dele por
 * EnsureUserIsActive — não há nada para invalidar aqui.
 */
class ChangeUserActiveStatusAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(OrganizationMembership $membership, bool $active): OrganizationMembership
    {
        if (! $active && OwnershipGuard::isSoleActiveOwner($membership)) {
            throw ValidationException::withMessages([
                'user' => 'Não é possível inativar o único proprietário ativo da organização.',
            ]);
        }

        $user = $membership->user;
        $before = ['is_active' => $user->is_active];

        // is_active nao esta em $fillable de propósito (flag sensivel de
        // seguranca) — forceFill e o unico ponto autorizado a alterá-la.
        $user->forceFill(['is_active' => $active])->save();

        $this->auditLogger->log(
            AuditAction::Updated,
            auditable: $user,
            before: $before,
            after: ['is_active' => $active],
            organization: $membership->organization,
        );

        return $membership;
    }
}
