<?php

declare(strict_types=1);

namespace App\Actions\Account;

use App\Enums\AuditAction;
use App\Enums\OrganizationMembershipStatus;
use App\Models\User;
use App\Support\Auditing\AuditLogger;
use App\Support\Tenancy\OwnershipGuard;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Encerra o acesso do usuário à plataforma sem excluir fisicamente seus
 * dados: desativa a conta (is_active = false), preservando vínculos,
 * histórico e registros para integridade referencial e auditoria. Nunca
 * permitido quando o usuário é o único proprietário ativo de alguma
 * organização — a transferência de propriedade ou a inativação da
 * organização deve acontecer primeiro.
 */
class RequestAccountClosureAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(User $user): void
    {
        $isSoleActiveOwner = $user->organizationMemberships()
            ->where('status', OrganizationMembershipStatus::Active)
            ->where('is_owner', true)
            ->get()
            ->contains(fn ($membership) => OwnershipGuard::isSoleActiveOwner($membership));

        if ($isSoleActiveOwner) {
            throw ValidationException::withMessages([
                'password' => 'Não é possível encerrar a conta: você é o(a) único(a) proprietário(a) ativo(a) de uma clínica. Transfira a propriedade ou inative a clínica antes de continuar.',
            ]);
        }

        DB::transaction(function () use ($user) {
            $user->forceFill(['is_active' => false])->save();

            $this->auditLogger->log(
                AuditAction::Deactivated,
                auditable: $user,
                after: ['is_active' => false, 'reason' => 'account_closure_requested'],
            );
        });
    }
}
