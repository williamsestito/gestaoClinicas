<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AppointmentStatus;
use Database\Factories\AppointmentFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Agendamento real (reserva confirmada), diferente de App\Models\AppointmentRequest
 * (lead/intenção de contato, sem checagem de conflito). `starts_at`/`ends_at`
 * são instantes reais em UTC — mesmo padrão de App\Models\ProfessionalTimeBlock,
 * nunca hora civil recorrente. Conflito verificado por
 * App\Support\Availability\AppointmentOverlapGuard antes de qualquer
 * criação/reagendamento.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $unit_id
 * @property string $professional_id
 * @property string $patient_id
 * @property string $service_id
 * @property string|null $session_package_id
 * @property string|null $recurrence_group_id
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property AppointmentStatus $status
 * @property string|null $cancellation_reason
 * @property Carbon|null $checked_in_at
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property string|null $notes
 * @property Carbon|null $deleted_at
 */
class Appointment extends Model
{
    /** @use HasFactory<AppointmentFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'unit_id',
        'professional_id',
        'patient_id',
        'service_id',
        'session_package_id',
        'recurrence_group_id',
        'starts_at',
        'ends_at',
        'status',
        'cancellation_reason',
        'checked_in_at',
        'started_at',
        'completed_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'status' => AppointmentStatus::class,
            'checked_in_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<Unit, $this> */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /** @return BelongsTo<Professional, $this> */
    public function professional(): BelongsTo
    {
        return $this->belongsTo(Professional::class);
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

    /** @return BelongsTo<SessionPackage, $this> */
    public function sessionPackage(): BelongsTo
    {
        return $this->belongsTo(SessionPackage::class);
    }

    /** @return BelongsToMany<SharedResource, $this> */
    public function resources(): BelongsToMany
    {
        // Chaves de pivô explícitas: o padrão do Eloquent as inferiria a
        // partir do nome da classe (`shared_resource_id`), mas a coluna
        // real da migration é `resource_id` (o model se chama SharedResource
        // só para evitar colisão com o pseudo-tipo `resource` do PHPDoc).
        return $this->belongsToMany(SharedResource::class, 'appointment_resource', 'appointment_id', 'resource_id')->withTimestamps();
    }

    /** @return HasOne<MedicalRecord, $this> */
    public function medicalRecord(): HasOne
    {
        return $this->hasOne(MedicalRecord::class);
    }
}
