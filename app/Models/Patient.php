<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RecordStatus;
use Database\Factories\PatientFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Cadastro administrativo do paciente (RF-PAC-001). Responsáveis
 * (App\Models\PatientResponsible) e contatos de emergência
 * (App\Models\PatientEmergencyContact) são entidades dependentes simples,
 * não outro Patient — ver docs/modules/patients.md.
 *
 * @property string $id
 * @property string $organization_id
 * @property string|null $preferred_unit_id
 * @property string|null $primary_professional_id
 * @property string $name
 * @property string|null $preferred_name
 * @property string|null $document dígitos apenas, sem máscara (ver App\Support\Documents\Document)
 * @property Carbon $birth_date
 * @property string|null $phone
 * @property string|null $whatsapp
 * @property string|null $email
 * @property string|null $origin
 * @property string|null $photo_path
 * @property RecordStatus $status
 * @property Carbon|null $deleted_at
 */
class Patient extends Model
{
    /** @use HasFactory<PatientFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'preferred_unit_id',
        'primary_professional_id',
        'name',
        'preferred_name',
        'document',
        'birth_date',
        'phone',
        'whatsapp',
        'email',
        'origin',
        'photo_path',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => RecordStatus::class,
            'birth_date' => 'date',
        ];
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<Unit, $this> */
    public function preferredUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'preferred_unit_id');
    }

    /** @return BelongsTo<Professional, $this> */
    public function primaryProfessional(): BelongsTo
    {
        return $this->belongsTo(Professional::class, 'primary_professional_id');
    }

    /** @return MorphOne<Address, $this> */
    public function address(): MorphOne
    {
        return $this->morphOne(Address::class, 'addressable');
    }

    /** @return HasMany<PatientResponsible, $this> */
    public function responsibles(): HasMany
    {
        return $this->hasMany(PatientResponsible::class);
    }

    /** @return HasMany<PatientEmergencyContact, $this> */
    public function emergencyContacts(): HasMany
    {
        return $this->hasMany(PatientEmergencyContact::class);
    }

    /**
     * Vínculo com uma conta do portal do paciente (App\Models\PatientUser),
     * se este paciente tiver sido autocadastrado ou tiver sido depois
     * vinculado a uma conta — ver docs/modules/patient-portal.md. Usado
     * apenas como indicador informativo no cadastro administrativo; nunca
     * concede acesso por si só.
     *
     * @return HasOne<PatientUserLink, $this>
     */
    public function portalLink(): HasOne
    {
        return $this->hasOne(PatientUserLink::class);
    }

    /**
     * RN-004: paciente menor de 18 anos precisa de ao menos um responsável
     * legal ativo — ver App\Support\Patients\MinorGuardianGuard.
     */
    public function isMinor(): bool
    {
        return $this->birth_date->age < 18;
    }
}
