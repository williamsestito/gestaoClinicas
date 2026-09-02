<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Enums\RecordStatus;
use App\Models\Unit;
use App\Support\Auditing\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Troca a unidade matriz da organização de forma atômica (a antiga matriz
 * deixa de ser, a nova passa a ser), evitando violar o índice único
 * parcial de "uma matriz por organização".
 */
class SetHeadquartersUnitAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(Unit $unit): Unit
    {
        if ($unit->status !== RecordStatus::Active) {
            throw ValidationException::withMessages([
                'unit' => 'Não é possível definir uma unidade inativa como matriz.',
            ]);
        }

        return DB::transaction(function () use ($unit) {
            $unit->organization
                ->units()
                ->where('is_headquarters', true)
                ->where('id', '!=', $unit->id)
                ->update(['is_headquarters' => false]);

            $unit->update(['is_headquarters' => true]);

            $this->auditLogger->log(
                AuditAction::HeadquartersChanged,
                auditable: $unit,
                after: ['unit_id' => $unit->id],
                organization: $unit->organization,
                unit: $unit,
            );

            return $unit;
        });
    }
}
