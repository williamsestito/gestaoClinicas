<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Data\Organization\AddressData;
use App\Data\Organization\OpeningHourData;
use App\Enums\AuditAction;
use App\Enums\RecordStatus;
use App\Models\LegalEntity;
use App\Models\Organization;
use App\Models\Unit;
use App\Support\Auditing\AuditLogger;
use App\Support\OpeningHoursOverlapGuard;
use App\Support\SlugGenerator;

class CreateUnitAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /** @param list<OpeningHourData> $openingHours */
    public function handle(
        Organization $organization,
        LegalEntity $legalEntity,
        string $name,
        ?string $code,
        ?string $phone,
        ?string $whatsapp,
        AddressData $address,
        array $openingHours,
        bool $isHeadquarters = false,
    ): Unit {
        OpeningHoursOverlapGuard::assertNoOverlap($openingHours);

        $code ??= 'UN-'.str_pad((string) ($organization->units()->count() + 1), 4, '0', STR_PAD_LEFT);

        $unit = $organization->units()->create([
            'legal_entity_id' => $legalEntity->id,
            'name' => $name,
            'code' => $code,
            'slug' => SlugGenerator::unique(
                $name,
                fn (string $slug) => Unit::query()->where('organization_id', $organization->id)->where('slug', $slug),
            ),
            'status' => RecordStatus::Active,
            'is_headquarters' => $isHeadquarters,
            'timezone' => $organization->default_timezone,
            'phone' => $phone,
            'whatsapp' => $whatsapp,
        ]);

        $unit->address()->create([
            ...$address->toArray(),
            'organization_id' => $organization->id,
        ]);

        foreach ($openingHours as $hour) {
            $unit->openingHours()->create([
                ...$hour->toArray(),
                'organization_id' => $organization->id,
            ]);
        }

        $this->auditLogger->log(
            AuditAction::Created,
            auditable: $unit,
            after: $unit->only(['name', 'code', 'slug', 'is_headquarters']),
            organization: $organization,
            unit: $unit,
        );

        return $unit;
    }
}
