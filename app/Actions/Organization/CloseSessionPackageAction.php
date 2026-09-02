<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Enums\RecordStatus;
use App\Models\SessionPackage;
use App\Support\Auditing\AuditLogger;
use Illuminate\Validation\ValidationException;

/**
 * Encerramento manual — nunca exclusão física. Um pacote encerrado não pode
 * mais ser escolhido para descontar sessão de um novo agendamento, mas
 * agendamentos já vinculados a ele preservam o vínculo.
 */
class CloseSessionPackageAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(SessionPackage $package): SessionPackage
    {
        if ($package->status === RecordStatus::Inactive) {
            throw ValidationException::withMessages([
                'session_package' => 'Este pacote já está encerrado.',
            ]);
        }

        $package->update(['status' => RecordStatus::Inactive]);

        $this->auditLogger->log(
            AuditAction::Deactivated,
            auditable: $package,
            before: ['status' => RecordStatus::Active->value],
            after: ['status' => RecordStatus::Inactive->value],
            organization: $package->organization,
        );

        return $package;
    }
}
