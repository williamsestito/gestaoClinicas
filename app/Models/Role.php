<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SystemRole;
use Database\Factories\RoleFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Papel atribuível a um vínculo de organização (OrganizationMembership).
 * Papéis de sistema (`is_system = true`) são criados automaticamente por
 * organização (ver RolesAndPermissionsSeeder / App\Enums\SystemRole) e
 * nunca podem ser excluídos — apenas papéis personalizados podem.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property bool $is_system
 */
class Role extends Model
{
    /** @use HasFactory<RoleFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'organization_id',
        'name',
        'slug',
        'description',
        'is_system',
    ];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
        ];
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsToMany<Permission, $this> */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class);
    }

    /** @return HasMany<OrganizationMembership, $this> */
    public function organizationMemberships(): HasMany
    {
        return $this->hasMany(OrganizationMembership::class);
    }

    /**
     * O papel "Proprietário" nunca concede acesso por si só — o acesso
     * total vem de `organization_membership.is_owner`. Reatribuir este
     * papel a outro vínculo daria a ele todas as permissões
     * (`SystemRole::Owner->defaultPermissions()` retorna todas) sem
     * nunca tocar `is_owner`, contornando a proteção do último
     * proprietário — por isso nunca é atribuível diretamente (ver
     * App\Rules\NotOwnerRoleRule).
     */
    public function isOwnerRole(): bool
    {
        return $this->is_system && $this->slug === SystemRole::Owner->value;
    }
}
