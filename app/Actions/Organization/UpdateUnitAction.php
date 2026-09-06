<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Data\Organization\AddressData;
use App\Data\Organization\OpeningHourData;
use App\Enums\AuditAction;
use App\Models\Unit;
use App\Support\Auditing\AuditLogger;
use App\Support\OpeningHoursOverlapGuard;
use Illuminate\Support\Facades\DB;

class UpdateUnitAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<OpeningHourData>|null  $openingHours  null = não alterar os horários de funcionamento atuais.
     */
    public function handle(Unit $unit, array $attributes, ?AddressData $address = null, ?array $openingHours = null): Unit
    {
        if ($openingHours !== null) {
            OpeningHoursOverlapGuard::assertNoOverlap($openingHours);
        }

        $allowed = collect($attributes)->only([
            'name', 'phone', 'whatsapp', 'email', 'timezone',
        ])->all();

        $before = $unit->only(array_keys($allowed));

        return DB::transaction(function () use ($unit, $allowed, $before, $address, $openingHours) {
            $unit->update($allowed);

            if ($address !== null) {
                $unit->address()->updateOrCreate([], [
                    ...$address->toArray(),
                    'organization_id' => $unit->organization_id,
                ]);
            }

            if ($openingHours !== null) {
                $unit->openingHours()->delete();

                foreach ($openingHours as $hour) {
                    $unit->openingHours()->create([
                        ...$hour->toArray(),
                        'organization_id' => $unit->organization_id,
                    ]);
                }
            }

            $this->auditLogger->log(
                AuditAction::Updated,
                auditable: $unit,
                before: $before,
                after: $unit->only(array_keys($allowed)),
                organization: $unit->organization,
                unit: $unit,
            );

            return $unit;
        });
    }
}
