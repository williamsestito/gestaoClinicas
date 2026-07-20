<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\PermissionFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Catálogo global de permissões (não é escopado por organização — é o
 * mesmo conjunto de capacidades em qualquer instalação). Ver
 * App\Enums\PermissionKey para a lista fechada de chaves válidas.
 *
 * @property string $id
 * @property string $key
 * @property string $group
 * @property string $label
 * @property string|null $description
 */
class Permission extends Model
{
    /** @use HasFactory<PermissionFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'key',
        'group',
        'label',
        'description',
    ];

    /** @return BelongsToMany<Role, $this> */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }
}
