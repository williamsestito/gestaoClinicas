<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\UnitOpeningHourFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $organization_id
 * @property string $unit_id
 * @property int $day_of_week 0 (domingo) a 6 (sábado)
 * @property string $opens_at
 * @property string $closes_at
 * @property int $sort_order
 * @property Carbon|null $deleted_at
 */
class UnitOpeningHour extends Model
{
    /** @use HasFactory<UnitOpeningHourFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'unit_id',
        'day_of_week',
        'opens_at',
        'closes_at',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'day_of_week' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<Unit, $this> */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}
