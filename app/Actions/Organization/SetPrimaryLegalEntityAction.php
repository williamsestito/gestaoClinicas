<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Enums\RecordStatus;
use App\Models\LegalEntity;
use App\Support\Auditing\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Troca a entidade legal principal da organização de forma atômica (a
 * antiga principal deixa de ser, a nova passa a ser), evitando violar o
 * índice único parcial de "uma principal por organização".
 */
class SetPrimaryLegalEntityAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(LegalEntity $legalEntity): LegalEntity
    {
        if ($legalEntity->status !== RecordStatus::Active) {
            throw ValidationException::withMessages([
                'legal_entity' => 'Não é possível definir uma entidade legal inativa como principal.',
            ]);
        }

        return DB::transaction(function () use ($legalEntity) {
            $legalEntity->organization
                ->legalEntities()
                ->where('is_primary', true)
                ->where('id', '!=', $legalEntity->id)
                ->update(['is_primary' => false]);

            $legalEntity->update(['is_primary' => true]);

            $this->auditLogger->log(
                AuditAction::PrimaryLegalEntityChanged,
                auditable: $legalEntity,
                after: ['legal_entity_id' => $legalEntity->id],
                organization: $legalEntity->organization,
            );

            return $legalEntity;
        });
    }
}
