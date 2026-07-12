<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OrganizationStatus;
use Database\Factories\OrganizationFactory;
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
    ];

    protected function casts(): array
    {
        return [
            'status' => OrganizationStatus::class,
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
}
