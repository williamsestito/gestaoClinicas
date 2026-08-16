<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AppointmentStatus;
use App\Enums\RecordStatus;
use Database\Factories\SessionPackageFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Pacote de sessões (Etapa 3.3 do roadmap) — só contagem, sem preço/
 * pagamento (Comercial/Financeiro ainda não existem). Opcionalmente
 * escopado a um Service; quando `service_id` é nulo, o pacote é genérico
 * (qualquer serviço pode descontar dele).
 *
 * @property string $id
 * @property string $organization_id
 * @property string $patient_id
 * @property string|null $service_id
 * @property int $total_sessions
 * @property Carbon|null $expires_at
 * @property RecordStatus $status
 * @property Carbon|null $deleted_at
 */
class SessionPackage extends Model
{
    /** @use HasFactory<SessionPackageFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'patient_id',
        'service_id',
        'total_sessions',
        'expires_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'total_sessions' => 'integer',
            'expires_at' => 'date',
            'status' => RecordStatus::class,
        ];
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<Patient, $this> */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /** @return BelongsTo<Service, $this> */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /** @return HasMany<Appointment, $this> */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    /** Nunca persistido — sempre recalculado a partir dos agendamentos concluídos. */
    public function remainingSessions(): int
    {
        $consumed = $this->appointments()->where('status', AppointmentStatus::Completed)->count();

        return max($this->total_sessions - $consumed, 0);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
