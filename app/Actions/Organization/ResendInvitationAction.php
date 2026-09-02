<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Enums\InvitationStatus;
use App\Models\Invitation;
use App\Notifications\OrganizationInvitationNotification;
use App\Support\Auditing\AuditLogger;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Gera um novo token (o anterior deixa de funcionar) e reenvia o e-mail.
 * Só permitido para convites ainda pendentes ou expirados — convites
 * aceitos/cancelados precisam de um novo convite, não de reenvio.
 */
class ResendInvitationAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(Invitation $invitation): string
    {
        if ($invitation->status === InvitationStatus::Accepted || $invitation->status === InvitationStatus::Cancelled) {
            throw ValidationException::withMessages([
                'invitation' => 'Este convite não pode mais ser reenviado.',
            ]);
        }

        $token = Str::random(40);

        $invitation->update([
            'token_hash' => hash('sha256', $token),
            'status' => InvitationStatus::Pending,
            'expires_at' => now()->addDays(7),
        ]);

        $this->auditLogger->log(
            AuditAction::Updated,
            auditable: $invitation,
            after: ['email' => $invitation->email, 'resent' => true],
            organization: $invitation->organization,
        );

        Notification::route('mail', $invitation->email)
            ->notify(new OrganizationInvitationNotification($invitation, $token));

        return $token;
    }
}
