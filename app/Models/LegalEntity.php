<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LegalEntityType;
use App\Enums\RecordStatus;
use Database\Factories\LegalEntityFactory;
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
 * @property LegalEntityType $type
 * @property string $document
 * @property bool $is_primary
 * @property RecordStatus $status
 * @property Carbon|null $deleted_at
 */
class LegalEntity extends Model
{
    /** @use HasFactory<LegalEntityFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'type',
        'document',
        'legal_name',
        'trade_name',
        'state_registration',
        'municipal_registration',
        'email',
        'phone',
        'is_primary',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'type' => LegalEntityType::class,
            'status' => RecordStatus::class,
            'is_primary' => 'boolean',
        ];
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return MorphOne<Address, $this> */
    public function address(): MorphOne
    {
        return $this->morphOne(Address::class, 'addressable');
    }

    /** @return HasMany<Unit, $this> */
    public function units(): HasMany
    {
        return $this->hasMany(Unit::class);
    }
}
