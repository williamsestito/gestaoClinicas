<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Enums\InvitationStatus;
use App\Models\Invitation;
use App\Support\Auditing\AuditLogger;

class CancelInvitationAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(Invitation $invitation): void
    {
        $invitation->update(['status' => InvitationStatus::Cancelled]);

        $this->auditLogger->log(
            AuditAction::Deleted,
            auditable: $invitation,
            before: ['email' => $invitation->email],
            organization: $invitation->organization,
        );
    }
}
