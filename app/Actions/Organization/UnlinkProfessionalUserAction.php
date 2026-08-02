<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Models\Professional;
use App\Support\Auditing\AuditLogger;

/**
 * Remove o vínculo opcional entre profissional e usuário. Nunca apaga nem
 * desativa o `User`, nunca remove memberships — apenas limpa a referência
 * no cadastro do profissional.
 */
class UnlinkProfessionalUserAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(Professional $professional): Professional
    {
        $previousUserId = $professional->user_id;

        $professional->update(['user_id' => null]);

        $this->auditLogger->log(
            AuditAction::Updated,
            auditable: $professional,
            before: ['user_id' => $previousUserId],
            after: ['user_id' => null],
            organization: $professional->organization,
        );

        return $professional;
    }
}
