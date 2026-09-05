<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ModuleKey;
use App\Enums\OrganizationMembershipStatus;
use App\Enums\OrganizationStatus;
use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property string $id
 * @property string $name
 * @property string $slug
 * @property OrganizationStatus $status
 * @property string $default_timezone
 * @property string $default_currency
 * @property string $locale
 * @property bool $allow_appointment_overlap
 */
class Organization extends Model
{
    /** @use HasFactory<OrganizationFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'name',
        'slug',
        'status',
        'default_timezone',
        'default_currency',
        'locale',
        'primary_color',
        'secondary_color',
        'logo_path',
        'allow_appointment_overlap',
    ];

    protected function casts(): array
    {
        return [
            'status' => OrganizationStatus::class,
            'allow_appointment_overlap' => 'boolean',
        ];
    }

    /** @return HasMany<LegalEntity, $this> */
    public function legalEntities(): HasMany
    {
        return $this->hasMany(LegalEntity::class);
    }

    /** @return HasOne<LegalEntity, $this> */
    public function primaryLegalEntity(): HasOne
    {
        return $this->hasOne(LegalEntity::class)->where('is_primary', true);
    }

    /** @return HasMany<Unit, $this> */
    public function units(): HasMany
    {
        return $this->hasMany(Unit::class);
    }

    /** @return HasOne<Unit, $this> */
    public function headquarters(): HasOne
    {
        return $this->hasOne(Unit::class)->where('is_headquarters', true);
    }

    /** @return HasMany<OrganizationMembership, $this> */
    public function memberships(): HasMany
    {
        return $this->hasMany(OrganizationMembership::class);
    }

    /** @return HasMany<Role, $this> */
    public function roles(): HasMany
    {
        return $this->hasMany(Role::class);
    }

    /** @return HasMany<Invitation, $this> */
    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class);
    }

    /** @return HasMany<Specialty, $this> */
    public function specialties(): HasMany
    {
        return $this->hasMany(Specialty::class);
    }

    /** @return HasMany<Service, $this> */
    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    /** @return HasMany<SharedResource, $this> */
    public function resources(): HasMany
    {
        return $this->hasMany(SharedResource::class);
    }

    /** @return HasMany<Product, $this> */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /** @return HasMany<Sale, $this> */
    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    /** @return HasMany<Professional, $this> */
    public function professionals(): HasMany
    {
        return $this->hasMany(Professional::class);
    }

    /** @return HasMany<Patient, $this> */
    public function patients(): HasMany
    {
        return $this->hasMany(Patient::class);
    }

    /** @return HasMany<Appointment, $this> */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    /** @return HasMany<PatientUser, $this> */
    public function patientUsers(): HasMany
    {
        return $this->hasMany(PatientUser::class);
    }

    /** @return HasMany<OrganizationModule, $this> */
    public function modules(): HasMany
    {
        return $this->hasMany(OrganizationModule::class);
    }

    /**
     * Organizações sem nenhum owner ativo — usado pelo platform admin para
     * localizar e recuperar organizações órfãs (ver
     * App\Actions\Organization\SetOrganizationOwnerAction). Um owner só
     * conta se o vínculo estiver ativo e o próprio usuário também estiver
     * ativo (um usuário desativado não deveria "esconder" a necessidade de
     * um novo administrador).
     *
     * @param  Builder<Organization>  $query
     * @return Builder<Organization>
     */
    public function scopeWithoutActiveOwner(Builder $query): Builder
    {
        return $query->whereDoesntHave('memberships', function (Builder $membershipQuery) {
            $membershipQuery
                ->where('is_owner', true)
                ->where('status', OrganizationMembershipStatus::Active)
                ->whereHas('user', fn (Builder $userQuery) => $userQuery->where('is_active', true));
        });
    }

    /**
     * `Core` está sempre habilitado e não tem linha correspondente em
     * `organization_modules` — os demais módulos são habilitados/
     * desabilitados explicitamente pelo proprietário (ver
     * docs/roadmap.md, Etapa 1).
     */
    public function hasModule(ModuleKey $key): bool
    {
        if ($key->isCore()) {
            return true;
        }

        return $this->modules()
            ->where('module_key', $key)
            ->where('is_enabled', true)
            ->exists();
    }
}
