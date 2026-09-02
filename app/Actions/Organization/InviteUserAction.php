<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Enums\InvitationStatus;
use App\Models\Invitation;
use App\Models\Organization;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use App\Notifications\OrganizationInvitationNotification;
use App\Support\Auditing\AuditLogger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * Cria um convite (nunca um usuário com senha definida diretamente por um
 * administrador — ver regra de segurança do Bloco 3) e envia por e-mail.
 * O token é gerado aleatoriamente e só o hash é persistido; o valor bruto
 * só existe em memória durante esta chamada (e no e-mail enviado).
 *
 * Única exceção deliberada a esta regra: App\Actions\Organization\
 * CreateProfessionalAction, a pedido explícito do proprietário do produto,
 * ciente do trade-off — nenhum outro ponto de criação de usuário deve
 * replicar essa exceção sem a mesma decisão explícita.
 */
class InviteUserAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /**
     * @param  Collection<int, Unit>|array<int, Unit>  $units
     * @return array{invitation: Invitation, token: string}
     */
    public function handle(Organization $organization, User $invitedBy, string $email, ?Role $role, iterable $units = []): array
    {
        $token = Str::random(40);

        $invitation = Invitation::query()->create([
            'organization_id' => $organization->id,
            'email' => $email,
            'role_id' => $role?->id,
            'invited_by' => $invitedBy->id,
            'token_hash' => hash('sha256', $token),
            'status' => InvitationStatus::Pending,
            'expires_at' => now()->addDays(7),
        ]);

        $invitation->units()->sync(collect($units)->pluck('id'));

        $this->auditLogger->log(
            AuditAction::Created,
            auditable: $invitation,
            after: ['email' => $email, 'role_id' => $role?->id],
            organization: $organization,
        );

        Notification::route('mail', $email)
            ->notify(new OrganizationInvitationNotification($invitation, $token));

        return ['invitation' => $invitation, 'token' => $token];
    }
}
