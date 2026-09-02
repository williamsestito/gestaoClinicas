<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\PatientEmergencyContactFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Contato de emergência do paciente (RN-003: todo paciente precisa de ao
 * menos um, ativo).
 *
 * @property string $id
 * @property string $organization_id
 * @property string $patient_id
 * @property string $name
 * @property string $relationship
 * @property string $phone_primary
 * @property string|null $phone_secondary
 */
class PatientEmergencyContact extends Model
{
    /** @use HasFactory<PatientEmergencyContactFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'patient_id',
        'name',
        'relationship',
        'phone_primary',
        'phone_secondary',
    ];

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
}
