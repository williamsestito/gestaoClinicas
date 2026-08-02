<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ProfessionalTimeBlockScope;
use App\Enums\ProfessionalTimeBlockTemporalStatus;
use App\Enums\ProfessionalTimeBlockType;
use App\Enums\RecordStatus;
use Database\Factories\ProfessionalTimeBlockFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Ausência, folga ou bloqueio do profissional — exceção datada que reduz a
 * disponibilidade regular (nunca cria disponibilidade adicional).
 * `starts_at`/`ends_at` são instantes reais em UTC, diferente de
 * App\Models\ProfessionalWorkingHour (hora civil recorrente). Escopo
 * sempre explícito via `scope`: `AllUnits` implica `unit_id` nulo,
 * `SpecificUnit` implica `unit_id` preenchido — garantido por CHECK.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $professional_id
 * @property string|null $unit_id
 * @property ProfessionalTimeBlockType $type
 * @property ProfessionalTimeBlockScope $scope
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property bool $is_all_day
 * @property string|null $reason
 * @property string|null $internal_notes
 * @property RecordStatus $status
 * @property Carbon|null $deleted_at
 * @property-read Unit|null $unit
 */
class ProfessionalTimeBlock extends Model
{
    /** @use HasFactory<ProfessionalTimeBlockFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'professional_id',
        'unit_id',
        'type',
        'scope',
        'starts_at',
        'ends_at',
        'is_all_day',
        'reason',
        'internal_notes',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'type' => ProfessionalTimeBlockType::class,
            'scope' => ProfessionalTimeBlockScope::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_all_day' => 'boolean',
            'status' => RecordStatus::class,
        ];
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<Professional, $this> */
    public function professional(): BelongsTo
    {
        return $this->belongsTo(Professional::class);
    }

    /** @return BelongsTo<Unit, $this> */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function coversUnit(string $unitId): bool
    {
        return $this->scope === ProfessionalTimeBlockScope::AllUnits || $this->unit_id === $unitId;
    }

    public function temporalStatus(): ProfessionalTimeBlockTemporalStatus
    {
        if ($this->trashed()) {
            return ProfessionalTimeBlockTemporalStatus::Deleted;
        }

        if ($this->status === RecordStatus::Inactive) {
            return ProfessionalTimeBlockTemporalStatus::Inactive;
        }

        $now = Carbon::now();

        if ($this->starts_at->gt($now)) {
            return ProfessionalTimeBlockTemporalStatus::Future;
        }

        if ($this->ends_at->lte($now)) {
            return ProfessionalTimeBlockTemporalStatus::Ended;
        }

        return ProfessionalTimeBlockTemporalStatus::Ongoing;
    }
}
