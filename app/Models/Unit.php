<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RecordStatus;
use Database\Factories\UnitFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $organization_id
 * @property string $legal_entity_id
 * @property string $name
 * @property string $code
 * @property string $slug
 * @property RecordStatus $status
 * @property bool $is_headquarters
 * @property string $timezone
 * @property Carbon|null $deleted_at
 */
class Unit extends Model
{
    /** @use HasFactory<UnitFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'legal_entity_id',
        'name',
        'code',
        'slug',
        'status',
        'is_headquarters',
        'timezone',
        'email',
        'phone',
        'whatsapp',
    ];

    protected function casts(): array
    {
        return [
            'status' => RecordStatus::class,
            'is_headquarters' => 'boolean',
        ];
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<LegalEntity, $this> */
    public function legalEntity(): BelongsTo
    {
        return $this->belongsTo(LegalEntity::class);
    }

    /** @return MorphOne<Address, $this> */
    public function address(): MorphOne
    {
        return $this->morphOne(Address::class, 'addressable');
    }

    /** @return HasMany<UnitOpeningHour, $this> */
    public function openingHours(): HasMany
    {
        return $this->hasMany(UnitOpeningHour::class)->orderBy('day_of_week')->orderBy('sort_order');
    }

    /** @return HasMany<UnitMembership, $this> */
    public function memberships(): HasMany
    {
        return $this->hasMany(UnitMembership::class);
    }

    /**
     * Profissionais que ATUAM nesta unidade (App\Models\ProfessionalUnit) —
     * não confundir com `memberships()`, que são usuários com ACESSO ao
     * sistema nesta unidade.
     *
     * @return HasMany<ProfessionalUnit, $this>
     */
    public function professionalLinks(): HasMany
    {
        return $this->hasMany(ProfessionalUnit::class);
    }
}
