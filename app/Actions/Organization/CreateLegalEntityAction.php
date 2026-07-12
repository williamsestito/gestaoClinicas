<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Data\Organization\AddressData;
use App\Enums\AuditAction;
use App\Enums\LegalEntityType;
use App\Enums\RecordStatus;
use App\Models\LegalEntity;
use App\Models\Organization;
use App\Support\Auditing\AuditLogger;

class CreateLegalEntityAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(
        Organization $organization,
        LegalEntityType $type,
        string $document,
        string $legalName,
        ?string $tradeName,
        AddressData $address,
        bool $isPrimary = false,
    ): LegalEntity {
        $legalEntity = $organization->legalEntities()->create([
            'type' => $type,
            'document' => $document,
            'legal_name' => $legalName,
            'trade_name' => $tradeName,
            'is_primary' => $isPrimary,
            'status' => RecordStatus::Active,
        ]);

        $legalEntity->address()->create([
            ...$address->toArray(),
            'organization_id' => $organization->id,
        ]);

        $this->auditLogger->log(
            AuditAction::Created,
            auditable: $legalEntity,
            after: [
                'type' => $type->value,
                'document' => $document,
                'legal_name' => $legalName,
                'is_primary' => $isPrimary,
            ],
            organization: $organization,
        );

        return $legalEntity;
    }
}
