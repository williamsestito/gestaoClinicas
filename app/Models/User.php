<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $phone
 * @property string|null $cpf dígitos apenas, sem máscara (ver App\Support\Documents\Document)
 * @property string|null $photo_path
 * @property string|null $address_postal_code
 * @property string|null $address_street
 * @property string|null $address_number
 * @property string|null $address_complement
 * @property string|null $address_neighborhood
 * @property string|null $address_city
 * @property string|null $address_state
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property bool $is_active
 * @property bool $is_platform_admin
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $last_login_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'name', 'email', 'password', 'phone', 'cpf', 'photo_path',
    'address_postal_code', 'address_street', 'address_number', 'address_complement',
    'address_neighborhood', 'address_city', 'address_state',
])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token', 'photo_path'])]
class User extends Authenticatable implements FilamentUser, MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'is_active' => 'boolean',
            'is_platform_admin' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    /**
     * Somente usuarios ativos, verificados e marcados como administradores
     * da plataforma podem acessar qualquer painel Filament.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_active
            && $this->is_platform_admin
            && $this->hasVerifiedEmail();
    }

    /** @return HasMany<OrganizationMembership, $this> */
    public function organizationMemberships(): HasMany
    {
        return $this->hasMany(OrganizationMembership::class);
    }

    /**
     * Cadastros operacionais de profissional vinculados a este usuário.
     * Vínculo meramente informativo — nunca concede acesso/permissões por
     * si só (ver App\Support\Authorization\PermissionChecker).
     *
     * @return HasMany<Professional, $this>
     */
    public function professionals(): HasMany
    {
        return $this->hasMany(Professional::class);
    }
}
