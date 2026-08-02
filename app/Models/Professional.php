<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RecordStatus;
use Database\Factories\ProfessionalFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Cadastro operacional de um profissional da clínica. Entidade própria —
 * NÃO é uma especialização de App\Models\User nem de
 * App\Models\SiteProfessional (vitrine pública). O vínculo com `User` é
 * opcional e nunca concede acesso ao sistema por si só: acesso continua
 * dependendo exclusivamente de OrganizationMembership/Role/PermissionChecker.
 *
 * @property string $id
 * @property string $organization_id
 * @property int|null $user_id
 * @property string $name
 * @property string|null $social_name
 * @property string $display_name
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $document dígitos apenas, sem máscara (ver App\Support\Documents\Document)
 * @property Carbon|null $birth_date
 * @property string|null $bio
 * @property string|null $photo_path
 * @property RecordStatus $status
 * @property Carbon|null $deleted_at
 */
class Professional extends Model
{
    /** @use HasFactory<ProfessionalFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'user_id',
        'name',
        'social_name',
        'display_name',
        'email',
        'phone',
        'document',
        'birth_date',
        'bio',
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

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<ProfessionalRegistration, $this> */
    public function registrations(): HasMany
    {
        return $this->hasMany(ProfessionalRegistration::class);
    }

    /** @return HasOne<ProfessionalRegistration, $this> */
    public function primaryRegistration(): HasOne
    {
        return $this->hasOne(ProfessionalRegistration::class)->where('is_primary', true);
    }

    /** @return HasMany<ProfessionalSpecialty, $this> */
    public function specialtyLinks(): HasMany
    {
        return $this->hasMany(ProfessionalSpecialty::class);
    }

    /** @return HasOne<ProfessionalSpecialty, $this> */
    public function primarySpecialtyLink(): HasOne
    {
        return $this->hasOne(ProfessionalSpecialty::class)->where('is_primary', true);
    }

    /** @return HasMany<ProfessionalUnit, $this> */
    public function unitLinks(): HasMany
    {
        return $this->hasMany(ProfessionalUnit::class);
    }

    /** @return HasOne<ProfessionalUnit, $this> */
    public function primaryUnitLink(): HasOne
    {
        return $this->hasOne(ProfessionalUnit::class)->where('is_primary', true);
    }

    /** @return HasMany<ProfessionalService, $this> */
    public function serviceLinks(): HasMany
    {
        return $this->hasMany(ProfessionalService::class);
    }

    /**
     * Unidades onde o profissional está ativo — base para a restrição de
     * compatibilidade de App\Models\ProfessionalService::compatibleUnitIds().
     * Independe de vigência (agendada/encerrada): considera apenas o
     * status do vínculo.
     *
     * @return Collection<int, string>
     */
    public function activeUnitIds(): Collection
    {
        return $this->unitLinks()->where('status', RecordStatus::Active)->pluck('unit_id');
    }
}
