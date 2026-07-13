<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Data\Organization\AddressData;
use App\Data\Organization\OpeningHourData;
use App\Enums\AuditAction;
use App\Enums\RecordStatus;
use App\Models\LegalEntity;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Unit;
use App\Support\Auditing\AuditLogger;
use App\Support\OpeningHoursOverlapGuard;
use App\Support\SlugGenerator;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CreateUnitAction
{
    private const MAX_CODE_ATTEMPTS = 5;

    public function __construct(private readonly AuditLogger $auditLogger) {}

    /**
     * @param  list<OpeningHourData>  $openingHours
     * @param  OrganizationMembership|null  $grantAccessTo  Vínculo de organização de quem está criando a
     *                                                      unidade: recebe acesso (UnitMembership) imediato a ela.
     */
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
        ?OrganizationMembership $grantAccessTo = null,
    ): Unit {
        OpeningHoursOverlapGuard::assertNoOverlap($openingHours);

        return DB::transaction(function () use (
            $organization, $legalEntity, $name, $code, $phone, $whatsapp,
            $address, $openingHours, $isHeadquarters, $grantAccessTo,
        ) {
            $unit = $this->createUnitWithUniqueCode($organization, $legalEntity, $name, $code, $phone, $whatsapp, $isHeadquarters);

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

            if ($grantAccessTo) {
                $grantAccessTo->unitMemberships()->create([
                    'unit_id' => $unit->id,
                    'status' => RecordStatus::Active,
                    'is_manager' => true,
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
        });
    }

    private function createUnitWithUniqueCode(
        Organization $organization,
        LegalEntity $legalEntity,
        string $name,
        ?string $explicitCode,
        ?string $phone,
        ?string $whatsapp,
        bool $isHeadquarters,
    ): Unit {
        $attributes = [
            'legal_entity_id' => $legalEntity->id,
            'name' => $name,
            'slug' => SlugGenerator::unique(
                $name,
                fn (string $slug) => Unit::query()->where('organization_id', $organization->id)->where('slug', $slug),
            ),
            'status' => RecordStatus::Active,
            'is_headquarters' => $isHeadquarters,
            'timezone' => $organization->default_timezone,
            'phone' => $phone,
            'whatsapp' => $whatsapp,
        ];

        if ($explicitCode !== null) {
            return $organization->units()->create([...$attributes, 'code' => $explicitCode]);
        }

        $baseSequence = $organization->units()->count();

        for ($attempt = 1; $attempt <= self::MAX_CODE_ATTEMPTS; $attempt++) {
            $code = 'UN-'.str_pad((string) ($baseSequence + $attempt), 4, '0', STR_PAD_LEFT);

            try {
                return DB::transaction(fn () => $organization->units()->create([...$attributes, 'code' => $code]));
            } catch (QueryException $exception) {
                $isCodeConflict = str_contains($exception->getMessage(), 'units_organization_id_code_unique');

                if (! $isCodeConflict || $attempt === self::MAX_CODE_ATTEMPTS) {
                    throw $exception;
                }
            }
        }

        throw new RuntimeException('Não foi possível gerar um código único para a unidade.');
    }
}
