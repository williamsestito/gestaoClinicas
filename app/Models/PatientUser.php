<?php

declare(strict_types=1);

namespace App\Models;

use App\Notifications\ResetPatientPasswordNotification;
use App\Notifications\VerifyPatientEmailNotification;
use Database\Factories\PatientUserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Conta de autenticação do paciente/responsável (guard "patient"),
 * completamente separada de App\Models\User (staff, guard "web") — tabelas,
 * guard e provider próprios. Ver docs/modules/patient-portal.md para o
 * porquê dessa separação (colisão de e-mail único, herança de
 * comportamento do Fortify pensado para staff, confusão com TenantContext).
 *
 * Uma conta pode gerenciar vários pacientes (titular + dependentes) via
 * PatientUserLink — nunca acessa Patient diretamente por rota, sempre
 * através de patients()/links() (ver App\Http\Controllers\PatientPortal).
 */
class PatientUser extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<PatientUserFactory> */
    use HasFactory, HasUlids, Notifiable;

    protected $fillable = [
        'organization_id',
        'name',
        'email',
        'password',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return HasMany<PatientUserLink, $this> */
    public function links(): HasMany
    {
        return $this->hasMany(PatientUserLink::class);
    }

    /**
     * "patient_user_links" é uma tabela de junção de verdade (FKs para os
     * dois lados), não uma cadeia hasManyThrough (onde o "through" teria FK
     * só para o pai, e o relacionado FK para o "through") — por isso
     * belongsToMany, não hasManyThrough. Nunca usado para attach/detach
     * (não existe remoção de vínculo nesta etapa); somente leitura escopada
     * (ex.: findOrFail) — ver App\Http\Controllers\PatientPortal.
     *
     * @return BelongsToMany<Patient, $this>
     */
    public function patients(): BelongsToMany
    {
        return $this->belongsToMany(Patient::class, 'patient_user_links', 'patient_user_id', 'patient_id')
            ->wherePivotNull('deleted_at');
    }

    /**
     * As rotas nomeadas padrão do Laravel ("password.reset",
     * "verification.verify") já pertencem ao fluxo de staff (Fortify) —
     * sobrescrever aqui evita gerar links quebrados/errados para o paciente.
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPatientPasswordNotification($token));
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyPatientEmailNotification);
    }
}
