<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ModuleKey;
use Database\Factories\OrganizationModuleFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $organization_id
 * @property ModuleKey $module_key
 * @property bool $is_enabled
 * @property Carbon|null $enabled_at
 * @property Carbon|null $disabled_at
 */
class OrganizationModule extends Model
{
    /** @use HasFactory<OrganizationModuleFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'organization_id', 'module_key', 'is_enabled', 'enabled_at', 'disabled_at',
    ];

    protected function casts(): array
    {
        return [
            'module_key' => ModuleKey::class,
            'is_enabled' => 'boolean',
            'enabled_at' => 'datetime',
            'disabled_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
