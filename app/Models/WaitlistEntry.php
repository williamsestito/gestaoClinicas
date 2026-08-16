<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\WaitlistEntryStatus;
use Database\Factories\WaitlistEntryFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Entrada na lista de espera (Etapa 3.3 do roadmap) — registro manual,
 * convertido à mão pela recepção em um Appointment real quando um horário
 * abre. `professional_id` nulo é uma opção válida ("qualquer profissional
 * disponível").
 *
 * @property string $id
 * @property string $organization_id
 * @property string $unit_id
 * @property string|null $professional_id
 * @property string $service_id
 * @property string $patient_id
 * @property Carbon|null $preferred_date
 * @property string|null $notes
 * @property WaitlistEntryStatus $status
 * @property string|null $appointment_id
 */
class WaitlistEntry extends Model
{
    /** @use HasFactory<WaitlistEntryFactory> */
    use HasFactory, HasUlids;

    protected $table = 'appointment_waitlist_entries';

    protected $fillable = [
        'organization_id',
        'unit_id',
        'professional_id',
        'service_id',
        'patient_id',
        'preferred_date',
        'notes',
        'status',
        'appointment_id',
    ];

    protected function casts(): array
    {
        return [
            'preferred_date' => 'date',
            'status' => WaitlistEntryStatus::class,
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

    /** @return BelongsTo<Service, $this> */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /** @return BelongsTo<Patient, $this> */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /** @return BelongsTo<Appointment, $this> */
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }
}
