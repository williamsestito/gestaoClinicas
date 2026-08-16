<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PatientUserLinkRole;
use Database\Factories\PatientUserLinkFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Vínculo entre uma conta de portal (PatientUser) e um paciente que ela
 * gerencia — "self" (a própria conta é o paciente) ou "dependent" (a conta
 * gerencia outra pessoa, ex.: um responsável). No máximo um vínculo "self"
 * ativo por conta, e no máximo uma conta ativa por paciente — garantido por
 * índices únicos parciais na migration (ver docs/modules/patient-portal.md).
 *
 * @property string $id
 * @property string $organization_id
 * @property string $patient_user_id
 * @property string $patient_id
 * @property PatientUserLinkRole $role
 */
class PatientUserLink extends Model
{
    /** @use HasFactory<PatientUserLinkFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'patient_user_id',
        'patient_id',
        'role',
    ];

    protected function casts(): array
    {
        return [
            'role' => PatientUserLinkRole::class,
        ];
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<PatientUser, $this> */
    public function patientUser(): BelongsTo
    {
        return $this->belongsTo(PatientUser::class);
    }

    /** @return BelongsTo<Patient, $this> */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }
}
