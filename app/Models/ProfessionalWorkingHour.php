<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ProfessionalUnitVigencyStatus;
use App\Enums\RecordStatus;
use App\Enums\Weekday;
use Database\Factories\ProfessionalWorkingHourFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Intervalo de jornada semanal recorrente, sempre relacionado ao vínculo
 * profissional-unidade (App\Models\ProfessionalUnit) — que já garante
 * profissional, unidade, clínica e vigência do vínculo em si. `starts_at`/
 * `ends_at` são hora civil local da unidade (mesma convenção de
 * App\Models\UnitOpeningHour): nunca um instante UTC fixo, pois
 * representam uma regra recorrente, não um evento datado. Reutiliza
 * App\Enums\ProfessionalUnitVigencyStatus para a situação de vigência —
 * mesmo conceito de três estados (Agendado/Vigente/Encerrado), sem criar
 * um enum equivalente duplicado.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $professional_unit_id
 * @property Weekday $weekday
 * @property string $starts_at hora civil local, formato H:i:s
 * @property string $ends_at hora civil local, formato H:i:s
 * @property Carbon|null $effective_from
 * @property Carbon|null $effective_until
 * @property RecordStatus $status
 * @property Carbon|null $deleted_at
 */
class ProfessionalWorkingHour extends Model
{
    /** @use HasFactory<ProfessionalWorkingHourFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'professional_unit_id',
        'weekday',
        'starts_at',
        'ends_at',
        'effective_from',
        'effective_until',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'weekday' => Weekday::class,
            'effective_from' => 'date',
            'effective_until' => 'date',
            'status' => RecordStatus::class,
        ];
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<ProfessionalUnit, $this> */
    public function professionalUnit(): BelongsTo
    {
        return $this->belongsTo(ProfessionalUnit::class);
    }

    public function vigencyStatus(): ProfessionalUnitVigencyStatus
    {
        $today = Carbon::today();

        if ($this->effective_from !== null && $this->effective_from->gt($today)) {
            return ProfessionalUnitVigencyStatus::Scheduled;
        }

        if ($this->effective_until !== null && $this->effective_until->lt($today)) {
            return ProfessionalUnitVigencyStatus::Ended;
        }

        return ProfessionalUnitVigencyStatus::InEffect;
    }
}
