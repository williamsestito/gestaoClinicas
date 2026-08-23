<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MedicalRecordStatus;
use Database\Factories\MedicalRecordFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Prontuário clínico de um atendimento (Etapa 4 do roadmap, Seção 10 do
 * documento de visão) — um por `Appointment` (`appointment_id` único).
 * Nunca usa SoftDeletes nem tem rota de exclusão: um registro finalizado é
 * permanente (RN-007), correções entram por `MedicalRecordAddendum`. Acesso
 * é controlado por `App\Policies\MedicalRecordPolicy`, que deliberadamente
 * NÃO usa `App\Support\Authorization\PermissionChecker` para a permissão
 * ampla (`medical-records.manage`) — proprietário e administrador da
 * plataforma não têm acesso clínico automático (RN-015/RN-016), ao
 * contrário de todo o resto do sistema.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $unit_id
 * @property string $patient_id
 * @property string $professional_id
 * @property string $appointment_id
 * @property MedicalRecordStatus $status
 * @property string|null $anamnesis
 * @property string|null $preexisting_conditions
 * @property string|null $allergies
 * @property string|null $current_medications
 * @property string|null $contraindications
 * @property string|null $evaluation
 * @property string|null $treatment_plan
 * @property string|null $procedures_performed
 * @property string|null $evolution_notes
 * @property string|null $prescriptions
 * @property string|null $referrals
 * @property array<string, mixed>|null $specialty_data
 * @property bool $has_return_right
 * @property int|null $return_window_days
 * @property Carbon|null $finalized_at
 * @property Carbon|null $released_to_patient_at
 * @property Carbon $created_at
 */
class MedicalRecord extends Model
{
    /** @use HasFactory<MedicalRecordFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'organization_id',
        'unit_id',
        'patient_id',
        'professional_id',
        'appointment_id',
        'status',
        'anamnesis',
        'preexisting_conditions',
        'allergies',
        'current_medications',
        'contraindications',
        'evaluation',
        'treatment_plan',
        'procedures_performed',
        'evolution_notes',
        'prescriptions',
        'referrals',
        'specialty_data',
        'has_return_right',
        'return_window_days',
        'finalized_at',
        'released_to_patient_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => MedicalRecordStatus::class,
            'specialty_data' => 'array',
            'has_return_right' => 'boolean',
            'finalized_at' => 'datetime',
            'released_to_patient_at' => 'datetime',
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

    /** @return BelongsTo<Patient, $this> */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /** @return BelongsTo<Professional, $this> */
    public function professional(): BelongsTo
    {
        return $this->belongsTo(Professional::class);
    }

    /** @return BelongsTo<Appointment, $this> */
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    /** @return HasMany<MedicalRecordAddendum, $this> */
    public function addenda(): HasMany
    {
        return $this->hasMany(MedicalRecordAddendum::class)->oldest();
    }

    /** @return HasMany<MedicalRecordFile, $this> */
    public function files(): HasMany
    {
        return $this->hasMany(MedicalRecordFile::class)->latest();
    }
}
